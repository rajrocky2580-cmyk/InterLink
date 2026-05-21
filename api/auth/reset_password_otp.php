<?php
// =========================================================
// POST /api/auth/reset_password_otp.php
// Body: { "reset_token": "...", "password": "...", "confirm": "..." }
// Validates the DB-stored reset token and updates the password.
// No session required — token was returned by verify_otp.php.
// =========================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$data        = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$resetToken  = trim($data['reset_token'] ?? '');
$password    = $data['password'] ?? '';
$confirm     = $data['confirm']  ?? '';

// Validate inputs
if (!$resetToken) {
    jsonResponse(['success' => false, 'error' => 'Reset token missing. Please start the password reset again.'], 400);
}
if (strlen($password) < 8) {
    jsonResponse(['success' => false, 'error' => 'Password must be at least 8 characters.'], 400);
}
if ($password !== $confirm) {
    jsonResponse(['success' => false, 'error' => 'Passwords do not match.'], 400);
}

$pdo = getDB();

// Look up the reset token — must be unused and not expired
$stmt = $pdo->prepare(
    "SELECT pr.id, pr.user_id, (pr.token_expires <= NOW()) AS is_expired
     FROM password_resets pr
     WHERE pr.reset_token = ?
     LIMIT 1"
);
$stmt->execute([$resetToken]);
$row = $stmt->fetch();

if (!$row) {
    jsonResponse(['success' => false, 'error' => 'Invalid reset token. Please go through the forgot password flow again.'], 403);
}
if ($row['is_expired']) {
    jsonResponse(['success' => false, 'error' => 'Reset link has expired. Please request a new OTP.'], 403);
}

$userId = (int)$row['user_id'];

// Update the password
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")->execute([$hash, $userId]);

// Invalidate the token so it can't be reused
$pdo->prepare("UPDATE password_resets SET reset_token = NULL, token_expires = NULL WHERE id = ?")
    ->execute([$row['id']]);

jsonResponse([
    'success'  => true,
    'message'  => 'Password updated successfully!',
    'redirect' => BASE_URL . '/index.php',
]);
