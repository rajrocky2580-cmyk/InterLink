<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$pdo = getDB();
$uid = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $targetId = (int)($_GET['user_id'] ?? $uid);
    $stmt = $pdo->prepare(
        "SELECT user_id, username, full_name, avatar_url, bio, phone, is_online, last_seen, created_at
         FROM users WHERE user_id = ? AND status = 'active'"
    );
    $stmt->execute([$targetId]);
    $user = $stmt->fetch();
    if (!$user) { jsonResponse(['error' => 'User not found'], 404); }
    $user['avatar_url'] = BASE_URL . '/uploads/avatars/' . $user['avatar_url'];
    jsonResponse(['user' => $user]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data      = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $full_name = sanitize($data['full_name'] ?? '');
    $bio       = sanitize($data['bio'] ?? '');
    $phone     = sanitize($data['phone'] ?? '');

    // Handle avatar upload
    $avatar = null;
    if (!empty($_FILES['avatar']['tmp_name'])) {
        $ext    = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $stored = $uid . '_' . time() . '.' . $ext;
        $dest   = UPLOAD_PATH . 'avatars/' . $stored;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
            $avatar = $stored;
        }
    }

    $sql = "UPDATE users SET full_name=?, bio=?, phone=?" . ($avatar ? ", avatar_url=?" : "") . " WHERE user_id=?";
    $params = [$full_name, $bio, $phone];
    if ($avatar) $params[] = $avatar;
    $params[] = $uid;

    $pdo->prepare($sql)->execute($params);
    $_SESSION['full_name'] = $full_name;
    if ($avatar) $_SESSION['avatar'] = $avatar;

    jsonResponse(['success' => true]);
}
