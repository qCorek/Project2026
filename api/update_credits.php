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

$userId  = (int)($data["user_id"] ?? 0);
$credits = (int)($data["credits"] ?? -1);

if ($userId <= 0) {
  bad("Invalid user ID", 400);
}

if ($credits < 0) {
  bad("Credits cannot be negative", 400);
}

/* =========================================================
   UPDATE CREDITS (WITH TRANSACTION)
========================================================= */

$pdo = db();
$pdo->beginTransaction();

try {

  // Ensure target user exists
  $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
  $stmt->execute([$userId]);

  if (!$stmt->fetch()) {
    $pdo->rollBack();
    bad("User not found", 404);
  }

  // Update credits
  $stmt = $pdo->prepare("
    UPDATE users
    SET credits = ?
    WHERE id = ?
  ");
  $stmt->execute([$credits, $userId]);

  // Log admin action (strongly recommended)
  $stmt = $pdo->prepare("
    INSERT INTO admin_logs (admin_id, action, created_at)
    VALUES (?, ?, NOW())
  ");
  $stmt->execute([
    $admin["id"],
    "set_credits:user={$userId}:credits={$credits}"
  ]);

  $pdo->commit();

} catch (Throwable $e) {
  $pdo->rollBack();
  bad("Server error", 500);
}

echo json_encode([
  "ok" => true,
  "user_id" => $userId,
  "credits" => $credits
]);

exit;