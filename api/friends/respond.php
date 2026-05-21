<?php
// api/friends/respond.php — Accept or reject a friend request
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$data      = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$fromUser  = (int)($data['from_user_id'] ?? 0);
$action    = $data['action'] ?? ''; // 'accept' or 'reject'
$myId      = currentUserId();

if (!$fromUser || !in_array($action, ['accept', 'reject'])) {
    jsonResponse(['success' => false, 'error' => 'Invalid parameters'], 400);
}

$pdo = getDB();

// Find pending request sent to me
$stmt = $pdo->prepare("
    SELECT id FROM friendships
    WHERE requester_id = ? AND addressee_id = ? AND status = 'pending'
");
$stmt->execute([$fromUser, $myId]);
$row = $stmt->fetch();

if (!$row) {
    jsonResponse(['success' => false, 'error' => 'No pending request found'], 404);
}

if ($action === 'accept') {
    $pdo->prepare("UPDATE friendships SET status='accepted', updated_at=NOW() WHERE id=?")
        ->execute([$row['id']]);

    // Notify requester
    $me = $pdo->query("SELECT full_name, username FROM users WHERE user_id=$myId")->fetch();
    $pdo->prepare("INSERT INTO notifications (user_id, type, reference_id, message) VALUES (?,?,?,?)")
        ->execute([$fromUser, 'friend_accepted', $myId, "{$me['full_name']} (@{$me['username']}) accepted your friend request!"]);

    jsonResponse(['success' => true, 'message' => 'Friend request accepted!', 'status' => 'accepted']);
} else {
    // Reject — delete the request
    $pdo->prepare("DELETE FROM friendships WHERE id=?")->execute([$row['id']]);
    jsonResponse(['success' => true, 'message' => 'Friend request rejected', 'status' => 'none']);
}
