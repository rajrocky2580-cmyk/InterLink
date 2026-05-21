<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$pdo    = getDB();
$uid    = currentUserId();
$data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$convId = (int)($data['conversation_id'] ?? 0);

if (!$convId || !userBelongsToConversation($uid, $convId, $pdo)) {
    jsonResponse(['error'=>'Unauthorized'],403);
}

$pdo->prepare("
    UPDATE message_status ms
    JOIN messages m ON ms.message_id=m.message_id
    SET ms.read_at=NOW()
    WHERE m.conversation_id=? AND ms.user_id=? AND ms.read_at IS NULL
")->execute([$convId,$uid]);

jsonResponse(['success'=>true]);
