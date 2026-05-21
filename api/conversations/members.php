<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.full_name, u.avatar_url, u.is_online, u.last_seen,
               cm.role, cm.joined_at
        FROM conversation_members cm
        JOIN users u ON cm.user_id = u.user_id
        WHERE cm.conversation_id = ? AND cm.left_at IS NULL
        ORDER BY cm.role DESC, u.full_name ASC
    ");
    $stmt->execute([$convId]);
    $members = $stmt->fetchAll();
    foreach ($members as &$m) {
        $m['avatar_url'] = BASE_URL . '/uploads/avatars/' . $m['avatar_url'];
    }
    jsonResponse(['members' => $members]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check requester is admin
    $stmt = $pdo->prepare("SELECT role FROM conversation_members WHERE conversation_id=? AND user_id=?");
    $stmt->execute([$convId, $uid]);
    $myRole = $stmt->fetchColumn();
    if ($myRole !== 'admin') { jsonResponse(['error' => 'Only group admins can add members'], 403); }

    $data      = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $newUserId = (int)($data['user_id'] ?? 0);
    if (!$newUserId) { jsonResponse(['error' => 'user_id required'], 400); }

    $pdo->prepare("INSERT IGNORE INTO conversation_members (conversation_id, user_id) VALUES (?,?)")
        ->execute([$convId, $newUserId]);
    jsonResponse(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $targetId  = (int)($data['user_id'] ?? 0);
    // Can remove yourself, or admin can remove others
    $stmt = $pdo->prepare("SELECT role FROM conversation_members WHERE conversation_id=? AND user_id=?");
    $stmt->execute([$convId, $uid]);
    $myRole = $stmt->fetchColumn();
    if ($targetId !== $uid && $myRole !== 'admin') {
        jsonResponse(['error' => 'Unauthorized'], 403);
    }
    $pdo->prepare("UPDATE conversation_members SET left_at=NOW() WHERE conversation_id=? AND user_id=?")
        ->execute([$convId, $targetId]);
    jsonResponse(['success' => true]);
}
