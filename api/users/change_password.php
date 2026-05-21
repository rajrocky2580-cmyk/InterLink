<?php
// =========================================================
// InterLink — Change Password API
// =========================================================
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(['error' => 'Method not allowed'], 405); }

$pdo  = getDB();
$uid  = currentUserId();
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$currentPw = $data['current_password'] ?? '';
$newPw     = $data['new_password'] ?? '';

if (!$currentPw || !$newPw) {
    jsonResponse(['success' => false, 'error' => 'Both current and new password are required.'], 400);
}

if (strlen($newPw) < 8) {
    jsonResponse(['success' => false, 'error' => 'New password must be at least 8 characters.'], 400);
}

// Verify current password
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

if (!$user || !password_verify($currentPw, $user['password_hash'])) {
    jsonResponse(['success' => false, 'error' => 'Current password is incorrect.'], 403);
}

// Update password
$hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
$pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")->execute([$hash, $uid]);

jsonResponse(['success' => true, 'message' => 'Password updated successfully.']);
