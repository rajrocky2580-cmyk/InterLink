<?php
// api/settings/update_privacy.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

$pdo = getDB();
$uid = currentUserId();

// For now, handle online status toggle
// (other toggles stored in localStorage on client)
$showOnline = isset($_POST['show_online']) ? 1 : 0;

$pdo->prepare("UPDATE users SET is_online=? WHERE user_id=?")
    ->execute([$showOnline, $uid]);

$_SESSION['settings_msg'] = 'Privacy settings saved.';
header('Location: ' . BASE_URL . '/settings.php'); exit;
