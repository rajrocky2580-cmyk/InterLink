<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$username  = sanitizeField($data['username'] ?? '');
$email     = sanitizeField($data['email'] ?? '');
$full_name = sanitize($data['full_name'] ?? ''); // display name — HTML-safe is fine
$password  = $data['password'] ?? '';
$confirm   = $data['confirm_password'] ?? '';

// Validation
if (!$username || !$email || !$password || !$full_name) {
    jsonResponse(['success' => false, 'error' => 'All fields are required.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'error' => 'Invalid email address.'], 400);
}
if (strlen($password) < 8) {
    jsonResponse(['success' => false, 'error' => 'Password must be at least 8 characters.'], 400);
}
if ($password !== $confirm) {
    jsonResponse(['success' => false, 'error' => 'Passwords do not match.'], 400);
}
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    jsonResponse(['success' => false, 'error' => 'Username may only contain letters, numbers, and underscores.'], 400);
}

$pdo = getDB();

// Check duplicates
$stmt = $pdo->prepare("SELECT user_id FROM users WHERE username=? OR email=?");
$stmt->execute([$username, $email]);
if ($stmt->fetch()) {
    jsonResponse(['success' => false, 'error' => 'Username or email already taken.'], 409);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $pdo->prepare(
    "INSERT INTO users (username, email, password_hash, full_name) VALUES (?,?,?,?)"
);
$stmt->execute([$username, $email, $hash, $full_name]);
$userId = $pdo->lastInsertId();

// Auto-login
session_regenerate_id(true);
$_SESSION['user_id']   = $userId;
$_SESSION['username']  = $username;
$_SESSION['full_name'] = $full_name;
$_SESSION['role']      = 'user';
$_SESSION['avatar']    = 'default.png';

$pdo->prepare("UPDATE users SET is_online=1, last_seen=NOW() WHERE user_id=?")
    ->execute([$userId]);

jsonResponse(['success' => true, 'redirect' => BASE_URL . '/chat.php']);
