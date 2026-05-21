<?php
// =========================================================
// POST /api/auth/verify_otp.php
// Body: { "email": "user@example.com", "otp": "123456" }
// Validates the OTP and returns a short-lived DB-stored reset token.
// =========================================================
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$data  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$email = strtolower(trim($data['email'] ?? ''));
$otp   = trim($data['otp'] ?? '');

if (!$email || !$otp) {
    jsonResponse(['success' => false, 'error' => 'Email and OTP are required.'], 400);
}
if (!preg_match('/^\d{1,6}$/', $otp)) {
    jsonResponse(['success' => false, 'error' => 'OTP must be exactly 6 digits.'], 400);
}

// Pad to 6 digits (preserves leading zeros stripped by number input on mobile)
$otp = str_pad($otp, 6, '0', STR_PAD_LEFT);

$pdo = getDB();

// Ensure reset_token column exists (safe to call on every request)
try {
    $pdo->exec("ALTER TABLE password_resets ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) DEFAULT NULL");
    $pdo->exec("ALTER TABLE password_resets ADD COLUMN IF NOT EXISTS token_expires DATETIME DEFAULT NULL");
} catch (PDOException $e) {
    // Column may already exist or DB doesn't support IF NOT EXISTS — ignore
}

// Find user
$stmt = $pdo->prepare("SELECT user_id FROM users WHERE LOWER(email) = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    jsonResponse(['success' => false, 'error' => 'Invalid email or OTP.'], 401);
}

// Find valid OTP — MySQL clock used for both storage and comparison (no timezone drift)
$stmt = $pdo->prepare(
    "SELECT id, used, (expires_at <= NOW()) AS is_expired
     FROM password_resets
     WHERE user_id = ? AND otp = ?
     ORDER BY id DESC LIMIT 1"
);
$stmt->execute([$user['user_id'], $otp]);
$row = $stmt->fetch();

if (!$row) {
    jsonResponse(['success' => false, 'error' => 'Incorrect OTP. Please check the code and try again.'], 401);
}
if ($row['used']) {
    jsonResponse(['success' => false, 'error' => 'This OTP has already been used. Please request a new one.'], 401);
}
if ($row['is_expired']) {
    jsonResponse(['success' => false, 'error' => 'OTP has expired. Please request a new one.'], 401);
}

// ── Generate a secure reset token stored in DB (no session needed) ──────────
// This token is returned to the browser and sent back with the password reset
// request — completely avoids AJAX session cookie timing issues.
$resetToken = bin2hex(random_bytes(32)); // 64 hex chars, cryptographically secure

// Mark OTP as used AND store the reset token (10-minute window to set new password)
$pdo->prepare(
    "UPDATE password_resets
     SET used = 1, reset_token = ?, token_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
     WHERE id = ?"
)->execute([$resetToken, $row['id']]);

jsonResponse([
    'success'      => true,
    'reset_token'  => $resetToken,   // sent to browser, returned with password reset request
    'message'      => 'OTP verified. You may now set a new password.',
]);
