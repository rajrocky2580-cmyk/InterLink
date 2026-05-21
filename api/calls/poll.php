<?php
// =========================================================
// InterLink — WebRTC Signal Poller
// =========================================================
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
$pdo = getDB();
$uid = currentUserId();

try {
    // Fetch all pending signals directed to this user
    $stmt = $pdo->prepare("
        SELECT cs.*, u.full_name AS from_name, u.avatar_url AS from_avatar
        FROM   call_signals cs
        JOIN   users u ON cs.from_user = u.user_id
        WHERE  cs.to_user = ?
        ORDER  BY cs.id ASC
        LIMIT  20
    ");
    $stmt->execute([$uid]);
    $rows = $stmt->fetchAll();

    // Delete consumed signals so they are not delivered twice
    if ($rows) {
        $ids = implode(',', array_map('intval', array_column($rows, 'id')));
        $pdo->exec("DELETE FROM call_signals WHERE id IN ($ids)");
    }

    $signals = [];
    foreach ($rows as $r) {
        $signals[] = [
            'id'              => (int)$r['id'],
            'from_user'       => (int)$r['from_user'],
            'from_name'       => $r['from_name'],
            'from_avatar'     => BASE_URL . '/uploads/avatars/' . ($r['from_avatar'] ?? 'default.png'),
            'conversation_id' => (int)$r['conversation_id'],
            'type'            => $r['type'],
            'payload'         => $r['payload'] ? json_decode($r['payload'], true) : null,
        ];
    }

    jsonResponse(['signals' => $signals]);

} catch (PDOException $e) {
    error_log('InterLink call_signals poll error: ' . $e->getMessage());
    // Return empty signals — client keeps polling and shows no error
    jsonResponse(['signals' => [], 'db_error' => 'call_signals table missing. Visit /setup_db.php']);
}
