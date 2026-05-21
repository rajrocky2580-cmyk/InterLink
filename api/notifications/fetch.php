<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$pdo = getDB();
$uid = currentUserId();

$stmt = $pdo->prepare("
    SELECT notification_id, type, reference_id, message, is_read, created_at
    FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 30
");
$stmt->execute([$uid]);
$notifs = $stmt->fetchAll();

$unread = array_filter($notifs, fn($n) => !$n['is_read']);

foreach ($notifs as &$n) { $n['time'] = formatTime($n['created_at']); }

jsonResponse(['notifications'=>$notifs,'unread_count'=>count($unread)]);
