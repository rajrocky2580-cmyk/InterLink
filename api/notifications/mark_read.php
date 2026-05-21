<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$pdo  = getDB();
$uid  = currentUserId();
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$ids  = array_map('intval', $data['notification_ids'] ?? []);

if (empty($ids)) {
    // Mark all
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
} else {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE notification_id IN ($ph) AND user_id=?")
        ->execute([...$ids, $uid]);
}

jsonResponse(['success'=>true]);
