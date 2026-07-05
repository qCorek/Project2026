<?php
require __DIR__ . "/config.php";

header("Content-Type: application/json; charset=utf-8");

/* =========================================================
   NOT LOGGED IN
========================================================= */

if (!isset($_SESSION["user_id"])) {
  echo json_encode(["ok" => false]);
  exit;
}

/* =========================================================
   FETCH & VALIDATE USER (via helper)
========================================================= */

try {
  $user = current_user();
} catch (Throwable $e) {
  session_destroy();
  http_response_code(403);
  echo json_encode(["ok" => false]);
  exit;
}

/* =========================================================
   RETURN USER DATA + CSRF TOKEN
========================================================= */

echo json_encode([
  "ok" => true,
  "user" => [
    "id"       => (int)$user["id"],
    "username" => $user["username"],
    "role"     => $user["role"],
    "credits"  => (int)$user["credits"]
  ],
  "csrf_token" => csrf_token()
]);

exit;