<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$pdo = getDB();
$uid = currentUserId();

$sql = "
SELECT
    c.conversation_id,
    c.type,
    CASE
        WHEN c.type = 'private' THEN u.full_name
        ELSE g.name
    END AS display_name,
    CASE
        WHEN c.type = 'private' THEN u.username
        ELSE NULL
    END AS other_username,
    CASE
        WHEN c.type = 'private' THEN u.avatar_url
        ELSE g.avatar_url
    END AS avatar,
    CASE
        WHEN c.type = 'private' THEN (u.last_seen IS NOT NULL AND TIMESTAMPDIFF(SECOND, u.last_seen, NOW()) <= 120)
        ELSE 0
    END AS is_online,
    CASE
        WHEN c.type = 'private' THEN u.last_seen
        ELSE NULL
    END AS other_last_seen,
    CASE
        WHEN c.type = 'private' THEN u.user_id
        ELSE NULL
    END AS other_user_id,
    m.content AS last_message,
    m.message_type AS last_message_type,
    m.sent_at,
    (
        SELECT COUNT(*) FROM message_status ms2
        WHERE ms2.user_id = :uid2
          AND ms2.read_at IS NULL
          AND ms2.message_id IN (
              SELECT message_id FROM messages WHERE conversation_id = c.conversation_id
          )
    ) AS unread_count
FROM conversations c
JOIN conversation_members cm ON c.conversation_id = cm.conversation_id AND cm.user_id = :uid1 AND cm.left_at IS NULL
LEFT JOIN conversation_members cm2
    ON c.conversation_id = cm2.conversation_id AND cm2.user_id != :uid3 AND c.type = 'private' AND cm2.left_at IS NULL
LEFT JOIN users u ON cm2.user_id = u.user_id
LEFT JOIN `groups` g ON c.conversation_id = g.conversation_id
LEFT JOIN messages m ON m.message_id = (
    SELECT message_id FROM messages
    WHERE conversation_id = c.conversation_id
    ORDER BY sent_at DESC LIMIT 1
)
ORDER BY m.sent_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':uid1' => $uid, ':uid2' => $uid, ':uid3' => $uid]);
$convs = $stmt->fetchAll();

foreach ($convs as &$c) {
    $c['avatar']   = BASE_URL . '/uploads/avatars/' . ($c['avatar'] ?? 'default.png');
    $c['sent_at']  = $c['sent_at'] ? formatTime($c['sent_at']) : '';
    // Format last_seen like WhatsApp: "Last seen today at 3:45 PM" etc.
    $c['is_online'] = (bool)$c['is_online'];
    if (!empty($c['other_last_seen']) && !$c['is_online']) {
        $c['last_seen_fmt'] = formatLastSeen($c['other_last_seen']);
    } else {
        $c['last_seen_fmt'] = null;
    }
}

jsonResponse(['conversations' => $convs]);
