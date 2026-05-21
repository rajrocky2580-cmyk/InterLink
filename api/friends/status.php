<?php
// api/friends/status.php — Get friendship status between current user and a target
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();
header('Content-Type: application/json');

$pdo      = getDB();
$uid      = currentUserId();
$targetId = (int)($_GET['user_id'] ?? 0);

if (!$targetId || $targetId === $uid) {
    jsonResponse(['status' => 'self']);
}

$stmt = $pdo->prepare("
    SELECT status, requester_id FROM friendships
    WHERE (requester_id = ? AND addressee_id = ?)
       OR (requester_id = ? AND addressee_id = ?)
    LIMIT 1
");
$stmt->execute([$uid, $targetId, $targetId, $uid]);
$row = $stmt->fetch();

if (!$row) {
    jsonResponse(['status' => 'none']);
}

if ($row['status'] === 'accepted') {
    jsonResponse(['status' => 'friends']);
}

if ($row['status'] === 'pending') {
    if ($row['requester_id'] === $uid) {
        jsonResponse(['status' => 'pending_sent']);
    } else {
        jsonResponse(['status' => 'pending_received']);
    }
}

jsonResponse(['status' => $row['status']]);
