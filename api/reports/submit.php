<?php
// =========================================================
// InterLink — Report Submission API
// =========================================================
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(['error' => 'Method not allowed'], 405); }

$pdo  = getDB();
$uid  = currentUserId();
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$messageId    = !empty($data['message_id']) ? (int)$data['message_id'] : null;
$reportedUser = !empty($data['reported_user']) ? (int)$data['reported_user'] : null;
$reason       = trim($data['reason'] ?? '');

if (!$reason) {
    jsonResponse(['success' => false, 'error' => 'Please provide a reason for your report.'], 400);
}

if (!$messageId && !$reportedUser) {
    jsonResponse(['success' => false, 'error' => 'A message or user must be specified.'], 400);
}

// If reporting a message, look up the sender
if ($messageId && !$reportedUser) {
    $stmt = $pdo->prepare("SELECT sender_id FROM messages WHERE message_id = ?");
    $stmt->execute([$messageId]);
    $msg = $stmt->fetch();
    if ($msg) {
        $reportedUser = (int)$msg['sender_id'];
    }
}

// Prevent self-reporting
if ($reportedUser === $uid) {
    jsonResponse(['success' => false, 'error' => 'You cannot report yourself.'], 400);
}

// Check for duplicate recent report (within 1 hour)
$stmt = $pdo->prepare(
    "SELECT report_id FROM reports
     WHERE reported_by = ? AND message_id <=> ? AND reported_user <=> ?
       AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
);
$stmt->execute([$uid, $messageId, $reportedUser]);
if ($stmt->fetch()) {
    jsonResponse(['success' => false, 'error' => 'You already reported this recently.'], 429);
}

$stmt = $pdo->prepare(
    "INSERT INTO reports (reported_by, reported_user, message_id, reason) VALUES (?, ?, ?, ?)"
);
$stmt->execute([$uid, $reportedUser, $messageId, $reason]);

jsonResponse(['success' => true, 'message' => 'Report submitted. Thank you.']);
