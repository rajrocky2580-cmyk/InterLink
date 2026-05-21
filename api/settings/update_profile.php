<?php
// api/settings/update_profile.php — Update display name, username, bio, avatar
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

$pdo = getDB();
$uid = currentUserId();

// ---- Handle avatar upload ----
$avatarFile = null;
if (!empty($_FILES['avatar']['tmp_name'])) {
    $ext     = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) {
        $_SESSION['settings_err'] = 'Invalid image type. Use JPG, PNG, GIF or WebP.';
        header('Location: ' . BASE_URL . '/settings.php'); exit;
    }
    if ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
        $_SESSION['settings_err'] = 'Image too large (max 5 MB).';
        header('Location: ' . BASE_URL . '/settings.php'); exit;
    }
    $dir  = __DIR__ . '/../../uploads/avatars/';
    $name = 'user_' . $uid . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . $name)) {
        $avatarFile = $name;
    }
}

// ---- Update text fields ----
$fullName = trim($_POST['full_name'] ?? '');
$username = strtolower(trim($_POST['username'] ?? ''));
$bio      = trim($_POST['bio'] ?? '');

if (!$fullName || !$username) {
    $_SESSION['settings_err'] = 'Name and username are required.';
    header('Location: ' . BASE_URL . '/settings.php'); exit;
}

// Check username uniqueness
$check = $pdo->prepare("SELECT user_id FROM users WHERE username=? AND user_id!=?");
$check->execute([$username, $uid]);
if ($check->fetch()) {
    $_SESSION['settings_err'] = 'That username is already taken.';
    header('Location: ' . BASE_URL . '/settings.php'); exit;
}

if ($avatarFile) {
    $pdo->prepare("UPDATE users SET full_name=?, username=?, bio=?, avatar_url=? WHERE user_id=?")
        ->execute([$fullName, $username, $bio, $avatarFile, $uid]);
} else {
    $pdo->prepare("UPDATE users SET full_name=?, username=?, bio=? WHERE user_id=?")
        ->execute([$fullName, $username, $bio, $uid]);
}

$_SESSION['settings_msg'] = 'Profile updated successfully!';
header('Location: ' . BASE_URL . '/settings.php'); exit;
