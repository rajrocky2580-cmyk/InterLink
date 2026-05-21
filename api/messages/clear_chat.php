<?php
// api/messages/clear_chat.php — Clear all messages in a conversation
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$pdo    = getDB();
$uid    = currentUserId();
$data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$convId = (int)($data['conversation_id'] ?? 0);

if (!$convId) {
    jsonResponse(['success' => false, 'error' => 'Missing conversation_id'], 400);
}

// Verify caller is a member of this conversation
if (!userBelongsToConversation($uid, $convId, $pdo)) {
    jsonResponse(['success' => false, 'error' => 'Access denied'], 403);
}

// Mark ALL messages in this conversation as deleted (for both sides)
// This mirrors "Delete for Everyone" on every message at once.
$stmt = $pdo->prepare("UPDATE messages SET is_deleted = 1 WHERE conversation_id = ?");
$stmt->execute([$convId]);

jsonResponse(['success' => true, 'cleared' => $stmt->rowCount()]);
