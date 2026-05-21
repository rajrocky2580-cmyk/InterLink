<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$q   = sanitize($_GET['q'] ?? '');
$uid = currentUserId();

if (strlen($q) < 1) {
    jsonResponse(['users' => []]);
}

$pdo  = getDB();
$stmt = $pdo->prepare(
    "SELECT user_id, username, full_name, avatar_url, last_seen,
            (last_seen IS NOT NULL AND TIMESTAMPDIFF(SECOND, last_seen, NOW()) <= 120) AS is_online
     FROM users
     WHERE (username LIKE ? OR full_name LIKE ?)
       AND user_id != ?
       AND status = 'active'
     LIMIT 20"
);
$like = "%$q%";
$stmt->execute([$like, $like, $uid]);
$users = $stmt->fetchAll();

foreach ($users as &$u) {
    $u['avatar_url'] = BASE_URL . '/uploads/avatars/' . $u['avatar_url'];
}

jsonResponse(['users' => $users]);
