<?php
require __DIR__ . "/config.php";

header("Content-Type: application/json; charset=utf-8");

/* =========================================================
   METHOD + JSON + CSRF
========================================================= */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  bad("Method not allowed", 405);
}

require_json();
require_csrf();

/* =========================================================
   ADMIN AUTH
========================================================= */

$admin = require_admin();

/* =========================================================
   READ INPUT
========================================================= */

$data = read_json();
$userId = (int)($data["user_id"] ?? 0);

if ($userId <= 0) {
  bad("Invalid user.", 400);
}

/* Optional: prevent admin from unbanning themselves (usually harmless) */
if ($userId === (int)$admin["id"]) {
  bad("Operation not allowed.", 400);
}

/* =========================================================
   EXECUTE UNBAN
========================================================= */

$stmt = db()->prepare("
  UPDATE users
  SET is_banned = 0
  WHERE id = ?
");

$stmt->execute([$userId]);

/* Optional: log admin action */
db()->prepare("
  INSERT INTO admin_logs (admin_id, action, created_at)
  VALUES (?, ?, NOW())
")->execute([
  $admin["id"],
  "unban_user:" . $userId
]);

echo json_encode(["ok" => true]);
exit;