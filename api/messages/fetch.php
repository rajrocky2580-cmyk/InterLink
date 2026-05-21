<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$pdo    = getDB();
$uid    = currentUserId();
$convId = (int)($_GET['conversation_id'] ?? 0);
$after  = (int)($_GET['after'] ?? 0);
$limit  = min((int)($_GET['limit'] ?? 50), 100);

if (!$convId || !userBelongsToConversation($uid, $convId, $pdo)) {
    jsonResponse(['error' => 'Unauthorized'], 403);
}

$sql = "
    SELECT m.message_id, m.conversation_id, m.sender_id, m.message_type,
           m.content, m.reply_to, m.is_edited, m.is_deleted, m.sent_at,
           u.full_name AS sender_name, u.avatar_url AS sender_avatar,
           r.content AS reply_content, ru.full_name AS reply_sender
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    LEFT JOIN messages r ON m.reply_to = r.message_id
    LEFT JOIN users ru ON r.sender_id = ru.user_id
    WHERE m.conversation_id = ?
";
$params = [$convId];
if ($after > 0) { $sql .= " AND m.message_id > ?"; $params[] = $after; }
$sql .= " ORDER BY m.sent_at ASC LIMIT ?";
$params[] = $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Pre-fetch read statuses in one query for efficiency
$msgIds = array_column($messages, 'message_id');
$readMap = [];
if ($msgIds) {
    $placeholders = implode(',', array_fill(0, count($msgIds), '?'));
    $rsStmt = $pdo->prepare("
        SELECT message_id, MIN(read_at) AS read_at
        FROM message_status
        WHERE message_id IN ($placeholders) AND user_id != ? AND read_at IS NOT NULL
        GROUP BY message_id
    ");
    $rsStmt->execute([...$msgIds, $uid]);
    while ($row = $rsStmt->fetch()) {
        $readMap[(int)$row['message_id']] = $row['read_at'];
    }
}

foreach ($messages as &$msg) {
    $msg['sender_avatar'] = BASE_URL . '/uploads/avatars/' . $msg['sender_avatar'];
    $msg['is_mine']       = ((int)$msg['sender_id'] === $uid);
    $msg['time']          = formatMessageTime($msg['sent_at']);
    if ($msg['is_deleted']) { $msg['content'] = 'This message was deleted'; }
    // read_at: set only for my own messages — null means not yet read by recipient
    $msg['read_at'] = $msg['is_mine'] ? ($readMap[(int)$msg['message_id']] ?? null) : null;
}

jsonResponse(['messages' => $messages]);
