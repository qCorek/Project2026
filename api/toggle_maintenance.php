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
$enabled = !empty($data["enabled"]) ? 1 : 0;

/* =========================================================
   UPDATE SETTING
========================================================= */

$stmt = db()->prepare("
  UPDATE app_settings
  SET value = ?
  WHERE name = 'maintenance'
");

$stmt->execute([$enabled]);

/* Optional: log admin action */
db()->prepare("
  INSERT INTO admin_logs (admin_id, action, created_at)
  VALUES (?, ?, NOW())
")->execute([
  $admin["id"],
  $enabled ? "maintenance_enabled" : "maintenance_disabled"
]);

echo json_encode([
  "ok" => true,
  "enabled" => (bool)$enabled
]);

exit;