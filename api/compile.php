<?php
declare(strict_types=1);

require __DIR__ . "/config.php";

set_time_limit(240);
header("X-Content-Type-Options: nosniff");

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
  bad("Method not allowed", 405);
}

require_csrf();
ensure_confuserex_ready();

/* ================= HELPERS ================= */

function ce_path(string ...$parts): string {
  $clean = [];

  foreach ($parts as $part) {
    $part = trim($part, "\\/");
    if ($part !== "") {
      $clean[] = $part;
    }
  }

  return implode(DIRECTORY_SEPARATOR, $clean);
}

function ce_rrmdir(string $dir): void {
  if (!is_dir($dir)) return;

  $items = scandir($dir);
  if ($items === false) return;

  foreach ($items as $item) {
    if ($item === "." || $item === "..") continue;

    $path = $dir . DIRECTORY_SEPARATOR . $item;

    if (is_dir($path) && !is_link($path)) {
      ce_rrmdir($path);
    } else {
      @unlink($path);
    }
  }

  @rmdir($dir);
}

function ce_xml_attr(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, "UTF-8");
}

function ce_post_bool(string $key, bool $default = false): bool {
  if (!isset($_POST[$key])) {
    return $default;
  }

  return filter_var($_POST[$key], FILTER_VALIDATE_BOOLEAN);
}

function ce_safe_download_name(string $originalName, string $ext): string {
  $base = pathinfo($originalName, PATHINFO_FILENAME);
  $base = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $base);
  $base = trim((string)$base, "._-");

  if ($base === "") {
    $base = "protected";
  }

  return $base . "-protected." . $ext;
}

function ce_append_capped(string &$target, string $chunk, int $max = 20000): void {
  if ($chunk === "") return;

  $target .= $chunk;

  if (strlen($target) > $max) {
    $target = substr($target, -$max);
  }
}

function ce_run_command_capture(string $cmd, string $cwd, int $timeoutSeconds = 210): array {
  $descriptors = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"],
  ];

  $process = proc_open($cmd, $descriptors, $pipes, $cwd);

  if (!is_resource($process)) {
    return [
      "code" => -1,
      "stdout" => "",
      "stderr" => "Process failed to start",
      "timedOut" => false,
    ];
  }

  fclose($pipes[0]);

  stream_set_blocking($pipes[1], false);
  stream_set_blocking($pipes[2], false);

  $stdout = "";
  $stderr = "";
  $deadline = time() + $timeoutSeconds;
  $timedOut = false;
  $exitCode = null;

  while (true) {
    $outChunk = fread($pipes[1], 8192);
    $errChunk = fread($pipes[2], 8192);

    if ($outChunk !== false && $outChunk !== "") {
      ce_append_capped($stdout, $outChunk);
    }

    if ($errChunk !== false && $errChunk !== "") {
      ce_append_capped($stderr, $errChunk);
    }

    $status = proc_get_status($process);

    if (!$status["running"]) {
      if (isset($status["exitcode"]) && $status["exitcode"] !== -1) {
        $exitCode = (int)$status["exitcode"];
      }

      break;
    }

    if (time() > $deadline) {
      $timedOut = true;
      @proc_terminate($process);
      break;
    }

    usleep(100000);
  }

  $remainingOut = stream_get_contents($pipes[1]);
  $remainingErr = stream_get_contents($pipes[2]);

  if ($remainingOut !== false) {
    ce_append_capped($stdout, $remainingOut);
  }

  if ($remainingErr !== false) {
    ce_append_capped($stderr, $remainingErr);
  }

  fclose($pipes[1]);
  fclose($pipes[2]);

  $closedCode = proc_close($process);

  if ($exitCode === null) {
    $exitCode = $closedCode;
  }

  return [
    "code" => $timedOut ? -1 : $exitCode,
    "stdout" => $stdout,
    "stderr" => $stderr,
    "timedOut" => $timedOut,
  ];
}

function ce_find_output_file(string $outDir, string $expectedName, string $ext): ?string {
  $expected = ce_path($outDir, $expectedName);

  if (is_file($expected) && is_readable($expected)) {
    return $expected;
  }

  if (!is_dir($outDir)) {
    return null;
  }

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($outDir, FilesystemIterator::SKIP_DOTS)
  );

  foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) continue;

    $path = $fileInfo->getPathname();

    if (
      strtolower($fileInfo->getExtension()) === strtolower($ext) &&
      is_readable($path)
    ) {
      return $path;
    }
  }

  return null;
}

/* ================= AUTH ================= */

$u = current_user();
$uid = (int)$u["id"];
$role = (string)$u["role"];
$compileCost = 1;

/* ================= MAINTENANCE ================= */

$maint = db()
  ->query("SELECT value FROM app_settings WHERE name='maintenance'")
  ->fetchColumn();

if ($maint === "1" && $role !== "admin") {
  bad("Maintenance mode enabled.", 503);
}

/* ================= FILE VALIDATION ================= */

if (!isset($_FILES["file"])) {
  bad("No file uploaded", 400);
}

$f = $_FILES["file"];

if (!isset($f["error"]) || is_array($f["error"])) {
  bad("Invalid upload", 400);
}

if ($f["error"] !== UPLOAD_ERR_OK) {
  $msg = match ($f["error"]) {
    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "File too large",
    UPLOAD_ERR_PARTIAL => "Upload incomplete",
    UPLOAD_ERR_NO_FILE => "No file uploaded",
    UPLOAD_ERR_NO_TMP_DIR => "Server misconfigured: no temp directory",
    UPLOAD_ERR_CANT_WRITE => "Server storage error",
    UPLOAD_ERR_EXTENSION => "Upload blocked by extension",
    default => "Upload failed",
  };

  bad($msg, 400);
}

$max = 100 * 1024 * 1024;
$size = (int)($f["size"] ?? 0);

if ($size <= 0 || $size > $max) {
  bad("File too large", 400);
}

$originalNameRaw = (string)($f["name"] ?? "");
$originalNameLower = strtolower($originalNameRaw);

if (str_ends_with($originalNameLower, ".exe")) {
  $ext = "exe";
} elseif (str_ends_with($originalNameLower, ".dll")) {
  $ext = "dll";
} else {
  bad("Invalid file type. Upload a .exe or .dll file.", 400);
}

if (!is_uploaded_file($f["tmp_name"])) {
  bad("Upload failed", 400);
}

$magic = @file_get_contents($f["tmp_name"], false, null, 0, 2);

if ($magic !== "MZ") {
  bad("Invalid PE file.", 400);
}

/* ================= OPTIONS ================= */

$renaming = ce_post_bool("renaming", true);
$stringEncryption = ce_post_bool("stringEncryption", true);
$controlFlow = ce_post_bool("controlFlow", true);
$resourceProtection = ce_post_bool("resourceProtection", false) || ce_post_bool("resources", false);
$referenceProxy = ce_post_bool("referenceProxy", false);
$antiTamper = ce_post_bool("antiTamper", false);

$protections = [];

if ($renaming) {
  $protections[] = '<protection id="rename" />';
}

if ($stringEncryption) {
  $protections[] = '<protection id="constants" />';
}

if ($controlFlow) {
  $protections[] = '<protection id="ctrl flow" />';
}

if ($resourceProtection) {
  $protections[] = '<protection id="resources" />';
}

if ($referenceProxy) {
  $protections[] = '<protection id="ref proxy" />';
}

if ($antiTamper) {
  $protections[] = '<protection id="anti tamper" />';
}

if (!$protections) {
  bad("Select at least one protection option.", 400);
}

/* ================= TEMP WORKSPACE ================= */

$baseTmpDir = JOB_BASE_DIR;

if (!is_dir($baseTmpDir) && !mkdir($baseTmpDir, 0770, true)) {
  bad("Server storage unavailable", 500);
}

if (!is_writable($baseTmpDir)) {
  bad("Jobs folder is not writable", 500);
}

$jobId = "confuserex_" . bin2hex(random_bytes(16));

$workDir = ce_path($baseTmpDir, $jobId);
$inputDir = ce_path($workDir, "input");
$outDir = ce_path($workDir, "output");

if (
  !mkdir($workDir, 0770, true) ||
  !mkdir($inputDir, 0770, true) ||
  !mkdir($outDir, 0770, true)
) {
  ce_rrmdir($workDir);
  bad("Server storage unavailable", 500);
}

$moduleName = "input." . $ext;
$savePath = ce_path($inputDir, $moduleName);

if (!move_uploaded_file($f["tmp_name"], $savePath)) {
  ce_rrmdir($workDir);
  bad("Upload failed", 400);
}

if (!is_readable($savePath)) {
  ce_rrmdir($workDir);
  bad("File unreadable", 400);
}

/* ================= GENERATE CONFUSEREX PROJECT ================= */

$protectionXml = implode("\n    ", $protections);

$projectXml =
'<?xml version="1.0" encoding="utf-8"?>
<project baseDir="' . ce_xml_attr($inputDir) . '" outputDir="' . ce_xml_attr($outDir) . '" xmlns="http://confuser.codeplex.com">
  <rule pattern="true" preset="none">
    ' . $protectionXml . '
  </rule>
  <module path="' . ce_xml_attr($moduleName) . '" />
</project>
';

$projectPath = ce_path($workDir, "project.crproj");

if (file_put_contents($projectPath, $projectXml, LOCK_EX) === false) {
  ce_rrmdir($workDir);
  bad("Could not create ConfuserEx project.", 500);
}

/* ================= CREDIT ================= */

$charged = false;
$pdo = db();

if ($role !== "admin") {
  try {
    $pdo->beginTransaction();

    $st = $pdo->prepare("SELECT credits FROM users WHERE id = ? FOR UPDATE");
    $st->execute([$uid]);
    $credits = $st->fetchColumn();

    if ($credits === false) {
      $pdo->rollBack();
      ce_rrmdir($workDir);
      bad("User not found", 401);
    }

    $credits = (int)$credits;

    if ($credits < $compileCost) {
      $pdo->rollBack();
      ce_rrmdir($workDir);
      bad("Not enough credits", 403);
    }

    $pdo
      ->prepare("UPDATE users SET credits = credits - ? WHERE id = ?")
      ->execute([$compileCost, $uid]);

    $pdo->commit();
    $charged = true;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }

    ce_rrmdir($workDir);
    bad("Credit system failure", 500);
  }
}

$refund = function () use ($charged, $role, $compileCost, $uid): void {
  if ($charged && $role !== "admin") {
    db()
      ->prepare("UPDATE users SET credits = credits + ? WHERE id = ?")
      ->execute([$compileCost, $uid]);
  }
};

/* ================= RUN CONFUSEREX CLI ================= */

/*
  Windows/XAMPP command.

  CONFUSEREX_CLI:
    <project root>\my-app\worker\Confuser.CLI.exe

  CONFUSEREX_DIR:
    <project root>\my-app\worker

  Important:
  proc_open uses CONFUSEREX_DIR as the working directory,
  so Confuser.CLI.exe can find its DLL files.
*/

$cmd =
  escapeshellarg(CONFUSEREX_CLI) .
  " -n " .
  escapeshellarg($projectPath);

$result = ce_run_command_capture($cmd, CONFUSEREX_DIR, 210);

$returnCode = (int)$result["code"];
$stdout = (string)$result["stdout"];
$stderr = (string)$result["stderr"];
$timedOut = (bool)$result["timedOut"];

/* ================= VERIFY OUTPUT ================= */

$generatedPath = ce_find_output_file($outDir, $moduleName, $ext);
$downloadName = ce_safe_download_name($originalNameRaw, $ext);
$logCost = ($role === "admin") ? 0 : $compileCost;

if ($timedOut || $returnCode !== 0 || !$generatedPath) {
  $errorText = trim(
    "rc={$returnCode}\n" .
    "timedOut=" . ($timedOut ? "1" : "0") . "\n\n" .
    "COMMAND:\n" . $cmd . "\n\n" .
    "PROJECT:\n" . $projectPath . "\n\n" .
    "STDOUT:\n" . $stdout . "\n\n" .
    "STDERR:\n" . $stderr
  );

  db()
    ->prepare("
      INSERT INTO compile_logs
      (filename, status, user_id, cost, ok, error_text)
      VALUES (?, ?, ?, ?, 0, ?)
    ")
    ->execute([
      $downloadName,
      "failed",
      $uid,
      $logCost,
      substr($errorText, 0, 2000),
    ]);

  $refund();
  ce_rrmdir($workDir);

  if ($timedOut) {
    bad("Build timed out. Try fewer protections or a smaller assembly.", 500);
  }

  if ($returnCode !== 0) {
    bad("Build failed. ConfuserEx returned an error.", 500);
  }

  bad("Build failed. ConfuserEx did not produce an output file.", 500);
}

$output = @file_get_contents($generatedPath);

if ($output === false || strlen($output) === 0) {
  db()
    ->prepare("
      INSERT INTO compile_logs
      (filename, status, user_id, cost, ok, error_text)
      VALUES (?, ?, ?, ?, 0, ?)
    ")
    ->execute([
      $downloadName,
      "failed",
      $uid,
      $logCost,
      "Output file was empty or unreadable.",
    ]);

  $refund();
  ce_rrmdir($workDir);
  bad("Build failed. Output file was empty.", 500);
}

/* ================= SUCCESS LOG ================= */

db()
  ->prepare("
    INSERT INTO compile_logs
    (filename, status, user_id, cost, ok)
    VALUES (?, ?, ?, ?, 1)
  ")
  ->execute([
    $downloadName,
    "success",
    $uid,
    $logCost,
  ]);

/* ================= CLEANUP BEFORE DOWNLOAD ================= */

ce_rrmdir($workDir);

/* ================= DOWNLOAD ================= */

while (ob_get_level()) {
  ob_end_clean();
}

$safeHeaderName = str_replace(['\\', '/', '"', "\r", "\n"], "_", $downloadName);

header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"" . $safeHeaderName . "\"");
header("Content-Length: " . strlen($output));
header("Cache-Control: no-store");

echo $output;
exit;
