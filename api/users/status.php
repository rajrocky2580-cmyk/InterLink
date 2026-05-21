<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$pdo  = getDB();
$uid  = currentUserId();

// Support both fetch (application/json) and sendBeacon (text/plain)
$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true);
if (!is_array($data)) {
    // sendBeacon may send as text/plain — try parsing the raw body
    parse_str($raw, $data);
}
$status = ($data['status'] ?? 'online') === 'online' ? 1 : 0;

$pdo->prepare("UPDATE users SET is_online=?, last_seen=NOW() WHERE user_id=?")
    ->execute([$status, $uid]);

jsonResponse(['success' => true]);
