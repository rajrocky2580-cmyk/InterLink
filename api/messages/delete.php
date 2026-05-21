<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$pdo  = getDB();
$uid  = currentUserId();
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$msgId= (int)($data['message_id'] ?? 0);
$scope= $data['scope'] ?? 'me'; // 'me' or 'all'

$stmt = $pdo->prepare("SELECT * FROM messages WHERE message_id=?");
$stmt->execute([$msgId]);
$msg  = $stmt->fetch();
if (!$msg) { jsonResponse(['error'=>'Message not found'],404); }
if ((int)$msg['sender_id'] !== $uid) { jsonResponse(['error'=>'Unauthorized'],403); }

if ($scope === 'all') {
    $pdo->prepare("UPDATE messages SET is_deleted=1,content='' WHERE message_id=?")->execute([$msgId]);
} else {
    // For "delete for me" — just mark status (simplified: hide via client)
    $pdo->prepare("INSERT INTO message_status (message_id,user_id,read_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE read_at=NOW()")->execute([$msgId,$uid]);
}

jsonResponse(['success'=>true]);
