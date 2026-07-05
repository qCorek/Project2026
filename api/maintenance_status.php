<?php
require __DIR__ . "/config.php";

header("Content-Type: application/json; charset=utf-8");

/* =========================================================
   METHOD CHECK (READ-ONLY ENDPOINT)
========================================================= */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  bad("Method not allowed", 405);
}

/* =========================================================
   AUTH
========================================================= */

current_user(); // ensures logged in + not banned

/* =========================================================
   FETCH MAINTENANCE STATUS
========================================================= */

$stmt = db()->prepare("
  SELECT value
  FROM app_settings
  WHERE name = 'maintenance'
  LIMIT 1
");

$stmt->execute();
$row = $stmt->fetch();

$enabled = false;

if ($row && isset($row["value"])) {
  $enabled = ((string)$row["value"] === '1');
}

echo json_encode([
  "ok" => true,
  "enabled" => $enabled
]);

exit;