<?php
require __DIR__ . "/config.php";
if (!isset($_SESSION["user_id"])) {
  bad("Unauthorized", 401);
}

$stmt = db()->query("
  SELECT id, username, role, is_banned, created_at, credits
  FROM users
  ORDER BY id ASC
");

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$users = array_map(function($u) {
  $u["id"] = (int)$u["id"];
  $u["is_banned"] = (bool)$u["is_banned"];
  $u["credits"] = (int)$u["credits"];
  return $u;
}, $users);

echo json_encode([
  "ok" => true,
  "users" => $users
]);