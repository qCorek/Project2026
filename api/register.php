<?php
require __DIR__ . "/config.php";

header("Content-Type: application/json; charset=utf-8");

/* =========================================================
   METHOD + JSON
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

/* =========================================================
   VALIDATION
========================================================= */

if ($username === "" || $password === "") {
  bad("Username and password required.", 400);
}

if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
  bad("Username must be 3-30 chars: letters/numbers/_", 400);
}

if (strlen($password) < 8) {
  bad("Password must be at least 8 characters.", 400);
}

/* =========================================================
   MAINTENANCE CHECK
========================================================= */

$maint = db()->query("
  SELECT value FROM app_settings WHERE name='maintenance'
")->fetchColumn();

if ($maint === '1') {
  bad("Registrations disabled (maintenance).", 503);
}

/* =========================================================
   CREATE USER
========================================================= */

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
  $stmt = db()->prepare("
    INSERT INTO users (username, password_hash, role, credits, is_banned)
    VALUES (?, ?, 'user', 999, 0)
  ");

  $stmt->execute([$username, $hash]);

  $newId = (int)db()->lastInsertId();

} catch (PDOException $e) {
  // Duplicate username (unique constraint)
  if ((int)$e->getCode() === 23000) {
    bad("Username already taken.", 409);
  }
  bad("Server error.", 500);
}

/* =========================================================
   AUTO LOGIN AFTER REGISTER
========================================================= */

session_regenerate_id(true);

$_SESSION["user_id"]  = $newId;
$_SESSION["username"] = $username;
$_SESSION["role"]     = "user";

/* Generate CSRF token immediately */
$_SESSION["csrf_token"] = bin2hex(random_bytes(32));

echo json_encode([
  "ok" => true,
  "user" => [
    "id" => $newId,
    "username" => $username,
    "role" => "user",
    "credits" => 0
  ],
  "csrf_token" => $_SESSION["csrf_token"]
]);

exit;