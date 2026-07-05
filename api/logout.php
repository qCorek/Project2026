<?php
require __DIR__ . "/config.php";

header("Content-Type: application/json; charset=utf-8");

/* =========================================================
   METHOD + CSRF
========================================================= */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  bad("Method not allowed", 405);
}

/* Since this is state-changing, require CSRF */
require_csrf();

/* =========================================================
   DESTROY SESSION
========================================================= */

/* Clear session data */
$_SESSION = [];

/* Delete session cookie */
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    [
      'expires'  => time() - 42000,
      'path'     => $params['path'],
      'domain'   => $params['domain'],
      'secure'   => $params['secure'],
      'httponly' => $params['httponly'],
      'samesite' => $params['samesite'] ?? 'Strict'
    ]
  );
}

/* Destroy server-side session */
session_destroy();

echo json_encode(["ok" => true]);
exit;