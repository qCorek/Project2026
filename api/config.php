<?php
declare(strict_types=1);

/* =========================================================
   SECURITY HEADERS
========================================================= */

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none';");

/* =========================================================
   SESSION CONFIG
========================================================= */

$isHttps =
  (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
  (($_SERVER["SERVER_PORT"] ?? "") === "443");

ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.cookie_httponly", "1");

session_set_cookie_params([
  "httponly" => true,
  "samesite" => "Lax",
  "secure" => $isHttps,
  "path" => "/",
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/* =========================================================
   DATABASE
   For local XAMPP, root with empty password is common.
   For production, use a dedicated DB user.
========================================================= */

function db(): PDO {
  static $pdo = null;

  if ($pdo instanceof PDO) {
    return $pdo;
  }

  $host = "127.0.0.1";
  $dbname = "myapp";
  $user = "root";
  $pass = "";

  $pdo = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $user,
    $pass,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );

  return $pdo;
}

/* =========================================================
   WINDOWS / XAMPP PATH CONFIG
========================================================= */

/*
  ConfuserEx is bundled with the project here:

    <project root>\my-app\worker

  This folder should contain:

    Confuser.CLI.exe
    Confuser.Core.dll
    Confuser.Protections.dll
    dnlib.dll
    and the rest of the ConfuserEx files
*/

define("API_ROOT", __DIR__);
define("CONFUSEREX_DIR", API_ROOT . DIRECTORY_SEPARATOR . "worker");
define("CONFUSEREX_CLI", CONFUSEREX_DIR . DIRECTORY_SEPARATOR . "Confuser.CLI.exe");

/*
  Temporary jobs should NOT be inside htdocs.

  Create this folder manually if needed:

    C:\xampp\private\jobs
*/

define("JOB_BASE_DIR", "C:\\xampp\\private\\jobs");

/*
  Compatibility alias.

  If your compile.php uses WORKER_PATH, this keeps it working.
*/

define("WORKER_PATH", CONFUSEREX_CLI);

/* =========================================================
   PATH HELPERS
========================================================= */

function app_path(string ...$parts): string {
  $clean = [];

  foreach ($parts as $part) {
    $part = trim($part, "\\/");
    if ($part !== "") {
      $clean[] = $part;
    }
  }

  return implode(DIRECTORY_SEPARATOR, $clean);
}

function ensure_dir(string $dir): void {
  if (is_dir($dir)) {
    return;
  }

  if (!mkdir($dir, 0770, true) && !is_dir($dir)) {
    bad("Server storage unavailable", 500);
  }
}

function ensure_confuserex_ready(): void {
  if (!is_dir(CONFUSEREX_DIR)) {
    bad("ConfuserEx folder not found", 500);
  }

  if (!is_file(CONFUSEREX_CLI)) {
    bad("Confuser.CLI.exe not found", 500);
  }

  if (!is_readable(CONFUSEREX_CLI)) {
    bad("Confuser.CLI.exe is not readable", 500);
  }

  ensure_dir(JOB_BASE_DIR);

  if (!is_writable(JOB_BASE_DIR)) {
    bad("Jobs folder is not writable", 500);
  }
}

/* =========================================================
   JSON HELPERS
========================================================= */

function read_json(): array {
  $raw = file_get_contents("php://input");
  $data = json_decode($raw ?: "{}", true);

  return is_array($data) ? $data : [];
}

function require_json(): void {
  if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {
    $contentType = $_SERVER["CONTENT_TYPE"] ?? "";

    if (stripos($contentType, "application/json") !== 0) {
      bad("Invalid request format", 400);
    }
  }
}

function bad(string $msg, int $code = 400): void {
  http_response_code($code);
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode([
    "ok" => false,
    "error" => $msg,
  ]);
  exit;
}

/* =========================================================
   CSRF PROTECTION
========================================================= */

function csrf_token(): string {
  if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
  }

  return $_SESSION["csrf_token"];
}

function require_csrf(): void {
  $token =
    $_SERVER["HTTP_X_CSRF_TOKEN"] ??
    $_SERVER["X_CSRF_TOKEN"] ??
    "";

  if (
    empty($_SESSION["csrf_token"]) ||
    empty($token) ||
    !hash_equals($_SESSION["csrf_token"], $token)
  ) {
    bad("Invalid CSRF token", 403);
  }
}

/* =========================================================
   AUTH HELPERS
========================================================= */

function current_user(): array {
  if (!isset($_SESSION["user_id"])) {
    bad("Unauthorized", 401);
  }

  $stmt = db()->prepare("
    SELECT id, username, role, is_banned, credits
    FROM users
    WHERE id = ?
    LIMIT 1
  ");

  $stmt->execute([$_SESSION["user_id"]]);
  $user = $stmt->fetch();

  if (!$user) {
    bad("Unauthorized", 401);
  }

  if ((int)$user["is_banned"] === 1) {
    bad("Account suspended", 403);
  }

  return $user;
}

function require_admin(): array {
  $user = current_user();

  if (($user["role"] ?? "") !== "admin") {
    bad("Forbidden", 403);
  }

  return $user;
}
