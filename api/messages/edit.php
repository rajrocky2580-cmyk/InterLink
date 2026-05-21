<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$pdo  = getDB();
$uid  = currentUserId();
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$msgId  = (int)($data['message_id'] ?? 0);
$content= trim($data['content'] ?? '');

if (!$msgId || !$content) { jsonResponse(['error'=>'Missing fields'],400); }

$stmt = $pdo->prepare("SELECT * FROM messages WHERE message_id=?");
$stmt->execute([$msgId]);
$msg  = $stmt->fetch();
if (!$msg || (int)$msg['sender_id'] !== $uid) { jsonResponse(['error'=>'Unauthorized'],403); }

// Only allow edit within 5 minutes
if (time() - strtotime($msg['sent_at']) > 300) {
    jsonResponse(['error'=>'Edit window expired (5 minutes)'],403);
}

$pdo->prepare("UPDATE messages SET content=?,is_edited=1 WHERE message_id=?")->execute([$content,$msgId]);
jsonResponse(['success'=>true]);
