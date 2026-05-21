<?php
// api/friends/request.php — Send a friend request
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$toUserId = (int)($data['to_user_id'] ?? 0);
$myId     = currentUserId();

if (!$toUserId || $toUserId === $myId) {
    jsonResponse(['success' => false, 'error' => 'Invalid user'], 400);
}

$pdo = getDB();

// Check if user exists
$user = $pdo->prepare("SELECT user_id, full_name, username FROM users WHERE user_id = ? AND status = 'active'");
$user->execute([$toUserId]);
$targetUser = $user->fetch();

if (!$targetUser) {
    jsonResponse(['success' => false, 'error' => 'User not found'], 404);
}

// Check existing friendship
$existing = $pdo->prepare("
    SELECT id, status, requester_id FROM friendships
    WHERE (requester_id = ? AND addressee_id = ?)
       OR (requester_id = ? AND addressee_id = ?)
    LIMIT 1
");
$existing->execute([$myId, $toUserId, $toUserId, $myId]);
$row = $existing->fetch();

if ($row) {
    if ($row['status'] === 'accepted') {
        jsonResponse(['success' => false, 'error' => 'Already friends'], 409);
    }
    if ($row['status'] === 'pending' && $row['requester_id'] === $myId) {
        jsonResponse(['success' => false, 'error' => 'Request already sent'], 409);
    }
    if ($row['status'] === 'pending' && $row['requester_id'] === $toUserId) {
        // They already sent us a request — auto-accept it
        $pdo->prepare("UPDATE friendships SET status='accepted', updated_at=NOW() WHERE id=?")
            ->execute([$row['id']]);

        // Notify both
        $me = $pdo->prepare("SELECT full_name, username FROM users WHERE user_id=?")->execute([$myId]);
        $me = $pdo->query("SELECT full_name, username FROM users WHERE user_id=$myId")->fetch();
        $pdo->prepare("INSERT INTO notifications (user_id, type, reference_id, message) VALUES (?,?,?,?)")
            ->execute([$toUserId, 'friend_accepted', $myId, "{$me['full_name']} (@{$me['username']}) accepted your friend request!"]);

        jsonResponse(['success' => true, 'message' => 'Friend request accepted! You are now friends.', 'status' => 'accepted']);
    }
}

// Create request
$pdo->prepare("INSERT INTO friendships (requester_id, addressee_id, status) VALUES (?,?,'pending')")
    ->execute([$myId, $toUserId]);

// Notify the addressee
$me = $pdo->query("SELECT full_name, username FROM users WHERE user_id=$myId")->fetch();
$pdo->prepare("INSERT INTO notifications (user_id, type, reference_id, message) VALUES (?,?,?,?)")
    ->execute([$toUserId, 'friend_request', $myId, "{$me['full_name']} (@{$me['username']}) sent you a friend request!"]);

jsonResponse(['success' => true, 'message' => 'Friend request sent!', 'status' => 'pending']);
