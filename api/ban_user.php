<?php
require __DIR__ . "/config.php";

header("Content-Type: application/json; charset=utf-8");

/* =========================================================
   REQUIRE JSON + CSRF + ADMIN
========================================================= */

require_json();
require_csrf();
$admin = require_admin();

/* =========================================================
   READ INPUT
========================================================= */

$data = read_json();
$userId = (int)($data["user_id"] ?? 0);

if ($userId <= 0) {
  bad("Invalid user.", 400);
}

/* Prevent banning yourself */
if ($userId === (int)$admin["id"]) {
  bad("You cannot ban yourself.", 400);
}

/* =========================================================
   EXECUTE BAN
========================================================= */

$stmt = db()->prepare("
  UPDATE users
  SET is_banned = 1
  WHERE id = ?
");

$stmt->execute([$userId]);

echo json_encode(["ok" => true]);
exit;