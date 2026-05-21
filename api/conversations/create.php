<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(['error' => 'Method not allowed'], 405); }

$pdo  = getDB();
$uid  = currentUserId();
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$type    = $data['type'] ?? 'private';
$members = array_map('intval', $data['members'] ?? []);
$name    = sanitize($data['name'] ?? '');

if (empty($members)) { jsonResponse(['error' => 'Members required'], 400); }

// For private chat: check if conversation already exists
if ($type === 'private') {
    $otherId = $members[0];
    $stmt = $pdo->prepare("
        SELECT c.conversation_id FROM conversations c
        JOIN conversation_members cm1 ON c.conversation_id = cm1.conversation_id AND cm1.user_id = ?
        JOIN conversation_members cm2 ON c.conversation_id = cm2.conversation_id AND cm2.user_id = ?
        WHERE c.type = 'private' LIMIT 1
    ");
    $stmt->execute([$uid, $otherId]);
    $existing = $stmt->fetch();
    if ($existing) {
        jsonResponse(['success' => true, 'conversation_id' => $existing['conversation_id']]);
    }
}

// Create new conversation
$pdo->prepare("INSERT INTO conversations (type, created_by) VALUES (?,?)")->execute([$type, $uid]);
$convId = $pdo->lastInsertId();

// Add creator
$pdo->prepare("INSERT INTO conversation_members (conversation_id, user_id, role) VALUES (?,?,?)")
    ->execute([$convId, $uid, $type === 'group' ? 'admin' : 'member']);

// Add other members
foreach ($members as $memberId) {
    if ($memberId !== $uid) {
        $pdo->prepare("INSERT IGNORE INTO conversation_members (conversation_id, user_id) VALUES (?,?)")
            ->execute([$convId, $memberId]);
    }
}

// Create group record
if ($type === 'group' && $name) {
    $pdo->prepare("INSERT INTO `groups` (conversation_id, name, created_by) VALUES (?,?,?)")
        ->execute([$convId, $name, $uid]);
}

jsonResponse(['success' => true, 'conversation_id' => $convId]);
