<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(['error'=>'Method not allowed'],405); }

$pdo    = getDB();
$uid    = currentUserId();
$data   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$convId = (int)($data['conversation_id'] ?? 0);
$content= trim($data['content'] ?? '');
$type   = $data['message_type'] ?? 'text';
$replyTo= !empty($data['reply_to']) ? (int)$data['reply_to'] : null;

if (!$convId || !$content) { jsonResponse(['error'=>'Missing fields'],400); }
if (!userBelongsToConversation($uid, $convId, $pdo)) { jsonResponse(['error'=>'Unauthorized'],403); }

$stmt = $pdo->prepare("INSERT INTO messages (conversation_id,sender_id,message_type,content,reply_to) VALUES (?,?,?,?,?)");
$stmt->execute([$convId,$uid,$type,$content,$replyTo]);
$msgId = $pdo->lastInsertId();

// Insert delivery status for all other members
$members = getConversationMembers($convId, $pdo);
foreach ($members as $memberId) {
    if ($memberId != $uid) {
        $pdo->prepare("INSERT INTO message_status (message_id,user_id,delivered) VALUES (?,?,1)")->execute([$msgId,$memberId]);
        // Create notification
        $pdo->prepare("INSERT INTO notifications (user_id,type,reference_id,message) VALUES (?,?,?,?)")
            ->execute([$memberId,'new_message',$convId,'New message from ' . ($_SESSION['full_name'] ?? 'Someone')]);
    }
}

// Return full message for immediate render
$stmt = $pdo->prepare("
    SELECT m.*, u.full_name AS sender_name, u.avatar_url AS sender_avatar
    FROM messages m JOIN users u ON m.sender_id=u.user_id
    WHERE m.message_id=?
");
$stmt->execute([$msgId]);
$msg = $stmt->fetch();
$msg['sender_avatar'] = BASE_URL . '/uploads/avatars/' . $msg['sender_avatar'];
$msg['is_mine']       = true;
$msg['time']          = formatMessageTime($msg['sent_at']);

jsonResponse(['success'=>true,'message'=>$msg]);
