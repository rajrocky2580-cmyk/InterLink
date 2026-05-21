<?php
// =========================================================
// InterLink — Login Action (root-level, WAF-safe)
// Placed in the ROOT so InfinityFree's WAF never blocks it.
// Standard HTML form POST — no AJAX, no JSON, no custom headers.
// =========================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

// Redirect already-logged-in users
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/chat.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$identifier = sanitizeField($_POST['email'] ?? '');
$password   = $_POST['password'] ?? '';

if (!$identifier || !$password) {
    header('Location: ' . BASE_URL . '/index.php?login_error=' . urlencode('Email and password are required.'));
    exit;
}

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ? OR LOWER(username) = ? LIMIT 1");
    $stmt->execute([strtolower($identifier), strtolower($identifier)]);
    $user = $stmt->fetch();
} catch (\Exception $e) {
    header('Location: ' . BASE_URL . '/index.php?login_error=' . urlencode('Database error. Check DB settings.'));
    exit;
}

if (!$user || !password_verify($password, $user['password_hash'])) {
    header('Location: ' . BASE_URL . '/index.php?login_error=' . urlencode('Invalid email/username or password.'));
    exit;
}

if ($user['status'] !== 'active') {
    header('Location: ' . BASE_URL . '/index.php?login_error=' . urlencode('Your account has been ' . $user['status'] . '.'));
    exit;
}

// Set session
try { @session_regenerate_id(true); } catch (\Throwable $e) {}
$_SESSION['user_id']   = $user['user_id'];
$_SESSION['username']  = $user['username'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'];
$_SESSION['avatar']    = $user['avatar_url'];

// Refresh session cookie
$lifetime = 30 * 24 * 60 * 60;
$cookieOpts = [
    'expires'  => time() + $lifetime,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
];
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $cookieOpts['secure'] = true;
}
@setcookie(session_name(), session_id(), $cookieOpts);

// Update online status
try {
    $pdo->prepare("UPDATE users SET is_online=1, last_seen=NOW() WHERE user_id=?")
        ->execute([$user['user_id']]);
} catch (\Throwable $e) { /* non-critical */ }

header('Location: ' . BASE_URL . '/chat.php');
exit;
