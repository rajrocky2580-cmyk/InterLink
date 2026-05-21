<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$identifier = sanitizeField($data['email'] ?? '');
$password   = $data['password'] ?? '';

if (!$identifier || !$password) {
    jsonResponse(['success' => false, 'error' => 'Email/username and password are required.'], 400);
}

$pdo  = getDB();
// Allow login by email OR username
$stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ? OR LOWER(username) = ? LIMIT 1");
$stmt->execute([$identifier, $identifier]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonResponse(['success' => false, 'error' => 'Invalid email/username or password.'], 401);
}

if ($user['status'] !== 'active') {
    jsonResponse(['success' => false, 'error' => 'Your account has been ' . $user['status'] . '.'], 403);
}

// Regenerate session for security (wrapped — can fail on shared hosting)
try { @session_regenerate_id(true); } catch (\Throwable $e) {}
$_SESSION['user_id']   = $user['user_id'];
$_SESSION['username']  = $user['username'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'];
$_SESSION['avatar']    = $user['avatar_url'];

// Explicitly set cookie with 30-day lifetime
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
$pdo->prepare("UPDATE users SET is_online=1, last_seen=NOW() WHERE user_id=?")
    ->execute([$user['user_id']]);

jsonResponse(['success' => true, 'redirect' => BASE_URL . '/chat.php']);

