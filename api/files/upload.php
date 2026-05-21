<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(['error'=>'Method not allowed'],405); }

$pdo = getDB();
$uid = currentUserId();

if (empty($_FILES['file']['tmp_name'])) { jsonResponse(['error'=>'No file uploaded'],400); }

$file     = $_FILES['file'];
$mimeType = mime_content_type($file['tmp_name']);
$size     = $file['size'];

if (!in_array($mimeType, ALLOWED_MIME_TYPES)) { jsonResponse(['error'=>'File type not allowed: ' . $mimeType],415); }
if ($size > MAX_FILE_SIZE) { jsonResponse(['error'=>'File too large (max 10MB)'],413); }

$ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$storedName = bin2hex(random_bytes(16)) . '.' . $ext;

// Route to correct subdirectory
$isImage = str_starts_with($mimeType, 'image/');
$isVideo = str_starts_with($mimeType, 'video/');
$isAudio = str_starts_with($mimeType, 'audio/');

if ($isImage)      $subdir = 'images';
elseif ($isVideo)  $subdir = 'videos';
elseif ($isAudio)  $subdir = 'audio';
else               $subdir = 'files';

$dest = UPLOAD_PATH . $subdir . '/' . $storedName;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    jsonResponse(['error'=>'Upload failed'],500);
}

// Record file in database for tracking & admin stats
// message_id is NULL at this point — it gets linked after the message is inserted
$fileId = 0;
try {
    $stmt = $pdo->prepare(
        "INSERT INTO files (message_id, uploaded_by, original_name, stored_name, file_type, file_size)
         VALUES (NULL, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$uid, $file['name'], $storedName, $mimeType, $size]);
    $fileId = (int)$pdo->lastInsertId();
} catch (Exception $e) {
    // Non-fatal: file is saved; DB tracking is best-effort
    error_log('InterLink file tracking error: ' . $e->getMessage());
}

$url = BASE_URL . '/uploads/' . $subdir . '/' . $storedName;
jsonResponse([
    'success'    => true,
    'url'        => $url,
    'file_id'    => $fileId,
    'file_name'  => $file['name'],
    'file_type'  => $mimeType,
    'file_size'  => $size,
    'stored'     => $storedName,
    'is_image'   => $isImage,
    'is_video'   => $isVideo,
    'is_audio'   => $isAudio,
]);
