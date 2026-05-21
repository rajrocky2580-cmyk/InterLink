<?php
// Returns which of the current user's sent messages have been read by the recipient.
// Used by the client to upgrade grey ✓✓ ticks to red ✓✓ without re-fetching all messages.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$pdo    = getDB();
$uid    = currentUserId();
$convId = (int)($_GET['conversation_id'] ?? 0);

if (!$convId || !userBelongsToConversation($uid, $convId, $pdo)) {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

// Return all message_ids that I sent AND that have been read by at least one other person
$stmt = $pdo->prepare("
    SELECT DISTINCT m.message_id
    FROM messages m
    JOIN message_status ms ON ms.message_id = m.message_id
    WHERE m.conversation_id = ?
      AND m.sender_id       = ?
      AND ms.user_id       != ?
      AND ms.read_at IS NOT NULL
");
$stmt->execute([$convId, $uid, $uid]);
$readIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

jsonResponse(['read_message_ids' => array_map('intval', $readIds)]);
