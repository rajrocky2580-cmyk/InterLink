<?php
// =========================================================
// InterLink — Configuration
// =========================================================
// Edit these values to match your environment

// =========================================================
// DATABASE — Update these for InfinityFree hosting!
// =========================================================
// InfinityFree: Find these in your hosting control panel
//   DB_HOST: e.g. 'sql###.infinityfree.com'
//   DB_NAME: e.g. 'if0_12345678_interlink'
//   DB_USER: e.g. 'if0_12345678'
//   DB_PASS: your database password
// XAMPP (local): leave as-is below
define('DB_HOST',    'localhost');
define('DB_NAME',    'InterLink');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

define('WS_PORT', 8080);
// Auto-detect HTTP vs HTTPS (works on InfinityFree SSL and local XAMPP)
$_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host   = ($_SERVER['HTTP_HOST'] ?? 'localhost');

// Auto-detect the base path so the app works:
//   - Locally on XAMPP  → files are in htdocs/InterLink/ → base path = /InterLink
//   - On InfinityFree   → files are at the domain root  → base path = (empty)
$_docRoot  = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$_appRoot  = rtrim(str_replace('\\', '/', realpath(__DIR__ . '/..')), '/');
$_basePath = str_replace($_docRoot, '', $_appRoot); // e.g. '/InterLink' or ''
$_basePath = rtrim($_basePath, '/');

define('BASE_URL',    $_scheme . '://' . $_host . $_basePath);
define('UPLOAD_URL',  $_basePath . '/uploads/'); // e.g. '/InterLink/uploads/' or '/uploads/'

define('SESSION_NAME', 'InterLink_session');

// Allowed MIME types for file uploads
define('ALLOWED_MIME_TYPES', [
    // Images
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    // Documents
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    // Audio
    'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/aac',
    'audio/mp4', 'audio/webm', 'audio/x-m4a',
    // Video
    'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
    'video/x-msvideo', 'video/mpeg'
]);

// =========================================================
// SMTP Email Configuration (for OTP / Forgot Password)
// =========================================================
// Use a Gmail App Password: myaccount.google.com → Security → App Passwords
// Enable 2-Step Verification first, then generate an App Password.
define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       465);              // 465 = SSL, 587 = TLS/STARTTLS
define('SMTP_USERNAME',   'your_gmail@gmail.com');  // ← your Gmail address
define('SMTP_PASSWORD',   'xxxx xxxx xxxx xxxx');   // ← 16-char App Password
define('SMTP_FROM_EMAIL', 'your_gmail@gmail.com');  // ← same Gmail
define('SMTP_FROM_NAME',  'InterLink');
define('OTP_EXPIRY_MINS', 10);  // OTP valid for 10 minutes
