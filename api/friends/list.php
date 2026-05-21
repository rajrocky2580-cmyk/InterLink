<?php
// api/friends/list.php — Get friends list + pending requests
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();
header('Content-Type: application/json');

$pdo = getDB();
$uid = currentUserId();

// Friends (accepted)
$friends = $pdo->prepare("
    SELECT u.user_id, u.username, u.full_name, u.avatar_url,
           (u.last_seen IS NOT NULL AND TIMESTAMPDIFF(SECOND, u.last_seen, NOW()) <= 120) AS is_online,
           u.last_seen,
           f.created_at AS friends_since
    FROM friendships f
    JOIN users u ON u.user_id = CASE
        WHEN f.requester_id = ? THEN f.addressee_id
        ELSE f.requester_id
    END
    WHERE (f.requester_id = ? OR f.addressee_id = ?)
      AND f.status = 'accepted'
      AND u.status = 'active'
    ORDER BY is_online DESC, u.full_name ASC
");
$friends->execute([$uid, $uid, $uid]);
$friendList = $friends->fetchAll();

// Pending requests received (from others to me)
$received = $pdo->prepare("
    SELECT u.user_id, u.username, u.full_name, u.avatar_url, u.is_online,
           f.created_at
    FROM friendships f
    JOIN users u ON u.user_id = f.requester_id
    WHERE f.addressee_id = ? AND f.status = 'pending' AND u.status = 'active'
    ORDER BY f.created_at DESC
");
$received->execute([$uid]);
$pendingReceived = $received->fetchAll();

// Pending requests sent by me
$sent = $pdo->prepare("
    SELECT u.user_id, u.username, u.full_name, u.avatar_url,
           f.created_at
    FROM friendships f
    JOIN users u ON u.user_id = f.addressee_id
    WHERE f.requester_id = ? AND f.status = 'pending' AND u.status = 'active'
    ORDER BY f.created_at DESC
");
$sent->execute([$uid]);
$pendingSent = $sent->fetchAll();

// Format avatars
foreach ($friendList as &$f) {
    $f['avatar_url']    = BASE_URL . '/uploads/avatars/' . ($f['avatar_url'] ?? 'default.png');
    $f['is_online']     = (bool)$f['is_online'];
    $f['last_seen_fmt'] = $f['is_online'] ? null : ($f['last_seen'] ? formatLastSeen($f['last_seen']) : 'Never');
}
foreach ($pendingReceived as &$p) {
    $p['avatar_url'] = BASE_URL . '/uploads/avatars/' . ($p['avatar_url'] ?? 'default.png');
    $p['time_fmt'] = formatTime($p['created_at']);
}
foreach ($pendingSent as &$p) {
    $p['avatar_url'] = BASE_URL . '/uploads/avatars/' . ($p['avatar_url'] ?? 'default.png');
}

jsonResponse([
    'friends'          => $friendList,
    'pending_received' => $pendingReceived,
    'pending_sent'     => $pendingSent,
    'friend_count'     => count($friendList),
    'pending_count'    => count($pendingReceived),
]);
