<?php
// =========================================================
// InterLink — Helper Utilities
// =========================================================

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Use for emails/usernames — no HTML encoding, just trim
function sanitizeField(string $input): string {
    return strtolower(trim($input));
}

/**
 * Format a datetime for display in conversation lists and notifications.
 * Always shows the actual time (not "Just now" / "Xm ago").
 */
function formatTime(string $datetime): string {
    $ts  = strtotime($datetime);
    $now = time();
    $diff = $now - $ts;

    if ($diff < 86400) {
        // Today — show clock time: "6:01 PM"
        return date('g:i A', $ts);
    }
    if ($diff < 172800) {
        // Yesterday
        return 'Yesterday ' . date('g:i A', $ts);
    }
    if ($diff < 604800) {
        // This week — show day + time: "Mon 6:01 PM"
        return date('D g:i A', $ts);
    }
    // Older — show date: "May 15"
    return date('M j', $ts);
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Format a last_seen datetime like WhatsApp / Instagram:
 *  - Within 1 minute  → "Active now"
 *  - Within 1 hour    → "Last seen X minutes ago"
 *  - Today            → "Last seen today at 3:45 PM"
 *  - Yesterday        → "Last seen yesterday at 9:12 AM"
 *  - This week        → "Last seen Monday at 2:30 PM"
 *  - Older            → "Last seen May 15 at 6:01 PM"
 */
function formatLastSeen(?string $datetime): string {
    if (!$datetime) return 'Last seen a while ago';
    $ts   = strtotime($datetime);
    $now  = time();
    $diff = $now - $ts;

    if ($diff < 60)     return 'Active now';
    if ($diff < 3600)   return 'Last seen ' . (int)($diff / 60) . ' min ago';
    if ($diff < 86400)  return 'Last seen today at '      . date('g:i A', $ts);
    if ($diff < 172800) return 'Last seen yesterday at '  . date('g:i A', $ts);
    if ($diff < 604800) return 'Last seen '  . date('l', $ts) . ' at ' . date('g:i A', $ts);
    return 'Last seen ' . date('M j', $ts) . ' at ' . date('g:i A', $ts);
}




function formatMessageTime(string $datetime): string {
    return date('g:i A', strtotime($datetime));
}

function getConversationMembers(int $convId, PDO $pdo): array {
    $stmt = $pdo->prepare(
        "SELECT user_id FROM conversation_members WHERE conversation_id = ? AND left_at IS NULL"
    );
    $stmt->execute([$convId]);
    return array_column($stmt->fetchAll(), 'user_id');
}

function userBelongsToConversation(int $userId, int $convId, PDO $pdo): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL"
    );
    $stmt->execute([$convId, $userId]);
    return (bool)$stmt->fetch();
}

function getAvatarUrl(string $avatarFile): string {
    return BASE_URL . '/uploads/avatars/' . $avatarFile;
}
