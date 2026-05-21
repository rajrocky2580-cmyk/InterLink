<?php
// =========================================================
// InterLink — WebRTC Signal Sender
// =========================================================
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(['error' => 'Method not allowed'], 405); }

$pdo  = getDB();
$uid  = currentUserId();

// Accept both JSON body (local) and form-encoded body (InfinityFree WAF bypass)
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { $data = $_POST; }

$toUser  = (int)($data['to_user']          ?? 0);
$convId  = (int)($data['conversation_id']  ?? 0);
$type    = $data['type']                   ?? '';
// payload may arrive as a pre-encoded JSON string (URLSearchParams) or as an array (JSON body)
$rawPayload = $data['payload'] ?? null;
$payload    = is_array($rawPayload) ? json_encode($rawPayload) : ($rawPayload ?: null);

$allowed = ['offer', 'answer', 'ice-candidate', 'hangup', 'reject', 'busy'];
if (!$toUser || !in_array($type, $allowed)) {
    jsonResponse(['error' => 'Invalid parameters'], 400);
}

try {
    // Clean stale signals (>60 s) to keep the table lean
    $pdo->exec("DELETE FROM call_signals WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 SECOND)");

    $stmt = $pdo->prepare(
        "INSERT INTO call_signals (from_user, to_user, conversation_id, type, payload)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$uid, $toUser, $convId, $type, $payload]);

    jsonResponse(['success' => true]);

} catch (PDOException $e) {
    error_log('InterLink call_signals insert error: ' . $e->getMessage());
    jsonResponse(['error' => 'Signaling unavailable. Visit /setup_db.php to create missing tables.'], 500);
}
