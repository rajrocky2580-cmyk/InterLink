<?php
// api/users/block.php — Block or unblock a user
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$pdo  = getDB();
$uid  = currentUserId();
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$targetId = (int)($data['user_id'] ?? 0);
$action   = $data['action'] ?? 'block'; // 'block' | 'unblock'

if (!$targetId || $targetId === $uid) {
    jsonResponse(['success' => false, 'error' => 'Invalid user'], 400);
}

// Auto-create table if it doesn't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS user_blocks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    blocker_id  INT NOT NULL,
    blocked_id  INT NOT NULL,
    created_at  DATETIME DEFAULT NOW(),
    UNIQUE KEY uq_block (blocker_id, blocked_id),
    KEY idx_blocker (blocker_id),
    KEY idx_blocked (blocked_id)
)");

if ($action === 'block') {
    $stmt = $pdo->prepare("INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?,?)");
    $stmt->execute([$uid, $targetId]);
    jsonResponse(['success' => true, 'message' => 'User blocked']);
} else {
    $stmt = $pdo->prepare("DELETE FROM user_blocks WHERE blocker_id=? AND blocked_id=?");
    $stmt->execute([$uid, $targetId]);
    jsonResponse(['success' => true, 'message' => 'User unblocked']);
}
