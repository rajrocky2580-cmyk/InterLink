<?php
// =========================================================
// POST /api/auth/send_otp.php
// Body: { "email": "user@example.com" }
// Generates a 6-digit OTP, saves to DB, and emails it.
// =========================================================
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$data  = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$email = strtolower(trim($data['email'] ?? ''));

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'error' => 'Please enter a valid email address.'], 400);
}

$pdo = getDB();

// Ensure password_resets table exists (wrapped in try/catch — FK may already exist)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        otp        CHAR(6) NOT NULL,
        expires_at DATETIME NOT NULL,
        used       TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_otp (otp)
    )");
} catch (PDOException $e) {
    // Table already exists or minor schema issue — safe to continue
    error_log('InterLink [setup]: password_resets table: ' . $e->getMessage());
}

// Look up user by email
$stmt = $pdo->prepare("SELECT user_id, full_name, email, status FROM users WHERE LOWER(email) = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Generic message to prevent email enumeration
    jsonResponse(['success' => true, 'message' => 'If that email is registered, an OTP has been sent.']);
}

if ($user['status'] !== 'active') {
    jsonResponse(['success' => false, 'error' => 'This account has been ' . $user['status'] . '.'], 403);
}

// Rate-limit: max 3 requests per 10 minutes per user
$stmt = $pdo->prepare("SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
$stmt->execute([$user['user_id']]);
if ((int)$stmt->fetchColumn() >= 3) {
    jsonResponse(['success' => false, 'error' => 'Too many OTP requests. Please wait 10 minutes and try again.'], 429);
}

// Generate 6-digit OTP (always padded to 6 chars to preserve leading zeros)
$otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Invalidate previous OTPs for this user
$pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0")->execute([$user['user_id']]);

// Store new OTP — use DATE_ADD(NOW(), ...) so MySQL's clock drives expiry,
// completely eliminating any PHP↔MySQL timezone mismatch.
$expirySeconds = OTP_EXPIRY_MINS * 60;
$pdo->prepare("INSERT INTO password_resets (user_id, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))")
    ->execute([$user['user_id'], $otp, $expirySeconds]);

// Detect if SMTP is still using placeholder / unconfigured values
$smtpUnconfigured = (
    str_contains(SMTP_USERNAME, 'your_gmail') ||
    str_contains(SMTP_PASSWORD, 'xxxx')       ||
    SMTP_USERNAME === ''
);

// Send email
if ($smtpUnconfigured) {
    // ── Dev / localhost mode ───────────────────────────────
    // SMTP not configured yet — skip sending and show OTP on screen
    error_log("InterLink [dev]: SMTP unconfigured, OTP for {$user['email']} is $otp");
    jsonResponse([
        'success'      => true,
        'email_sent'   => false,
        'dev_otp'      => $otp,
        'message'      => 'SMTP not configured — OTP shown on screen for testing.',
    ]);
}

try {
    $mailer = new Mailer();
    $html   = buildOtpEmail($otp, $user['full_name'], OTP_EXPIRY_MINS);
    $mailer->send($user['email'], $user['full_name'], 'InterLink — Your Password Reset OTP', $html);

    jsonResponse(['success' => true, 'email_sent' => true, 'message' => 'OTP sent! Check your inbox (and spam folder).']);
} catch (Exception $e) {
    // Email failed even though SMTP appears configured — still let the user proceed
    // by showing the OTP on screen, so login/reset flow is never fully blocked.
    error_log("InterLink [mailer]: " . $e->getMessage());
    jsonResponse([
        'success'    => true,
        'email_sent' => false,
        'dev_otp'    => $otp,
        'dev_error'  => $e->getMessage(),
        'message'    => 'Email delivery failed — OTP shown on screen.',
    ]);
}
