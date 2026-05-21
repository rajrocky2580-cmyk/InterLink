<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
if (isLoggedIn()) {
    $pdo = getDB();
    $pdo->prepare("UPDATE users SET is_online=0, last_seen=NOW() WHERE user_id=?")->execute([currentUserId()]);
}
session_destroy();
header('Location: ' . BASE_URL . '/index.php');
exit;
