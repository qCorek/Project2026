<?php
require __DIR__ . "/config.php";

header("Content-Type: application/json; charset=utf-8");

/* =========================================================
   METHOD + JSON ENFORCEMENT
========================================================= */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  bad("Method not allowed", 405);
}

require_json();

/* =========================================================
   READ INPUT
========================================================= */

$data = read_json();

$username = trim((string)($data["username"] ?? ""));
$password = (string)($data["password"] ?? "");

if ($username === "" || $password === "") {
  usleep(200000);
  bad("Invalid credentials.", 401);
}

/* =========================================================
   FETCH USER FIRST (IMPORTANT)
========================================================= */

$stmt = db()->prepare("
  SELECT id, username, password_hash, role, is_banned
  FROM users
  WHERE username = ?
  LIMIT 1
");

$stmt->execute([$username]);
$user = $stmt->fetch();

/* =========================================================
   MAINTENANCE CHECK (AFTER FETCH)
========================================================= */

$maint = db()->query("
  SELECT value FROM app_settings WHERE name='maintenance'
")->fetchColumn();

if ($maint === '1') {
  if (!$user || $user["role"] !== "admin") {
    // stealth mode: don't reveal maintenance
    usleep(200000);
    bad("Maintenance enabled :C", 401);
  }
}

/* =========================================================
   AUTH CHECKS
========================================================= */

if (!$user) {
  usleep(200000);
  bad("Invalid credentials.", 401);
}

if ((int)$user["is_banned"] === 1) {
  bad("Account is suspended.", 403);
}

if (!password_verify($password, $user["password_hash"])) {
  usleep(200000);
  bad("Invalid credentials.", 401);
}

/* Optional: auto-upgrade weak hashes */
if (password_needs_rehash($user["password_hash"], PASSWORD_DEFAULT)) {
  $newHash = password_hash($password, PASSWORD_DEFAULT);
  db()->prepare("UPDATE users SET password_hash=? WHERE id=?")
    ->execute([$newHash, $user["id"]]);
}

/* =========================================================
   SUCCESS
========================================================= */

session_regenerate_id(true);

$_SESSION["user_id"]  = (int)$user["id"];
$_SESSION["username"] = (string)$user["username"];
$_SESSION["role"]     = (string)$user["role"];

/* Generate fresh CSRF token after login */
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

echo json_encode([
  "ok" => true,
  "user" => [
    "id"       => $_SESSION["user_id"],
    "username" => $_SESSION["username"],
    "role"     => $_SESSION["role"]
  ],
  "csrf_token" => $_SESSION['csrf_token']
]);

exit;