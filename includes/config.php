<?php
// =========================================================
// InterLink — Configuration
// =========================================================
// Reads from environment variables (Render.com) with
// fallback to local XAMPP values for development.

// =========================================================
// DATABASE CONFIGURATION
// =========================================================
// On Render.com → set these as Environment Variables in dashboard
// Locally (XAMPP) → uses fallback values below
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: 'InterLink');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

define('WS_PORT', 8080);

// Auto-detect HTTP vs HTTPS (works on Render SSL and local XAMPP)
$_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host   = ($_SERVER['HTTP_HOST'] ?? 'localhost');

// Auto-detect the base path so the app works:
//   - Locally on XAMPP  → files are in htdocs/InterLink/ → base path = /InterLink
//   - On Render/Docker  → files are at the domain root  → base path = (empty)
$_docRoot  = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$_appRoot  = rtrim(str_replace('\\', '/', realpath(__DIR__ . '/..')), '/');
$_basePath = str_replace($_docRoot, '', $_appRoot); // e.g. '/InterLink' or ''
$_basePath = rtrim($_basePath, '/');

define('BASE_URL',    $_scheme . '://' . $_host . $_basePath);
define('UPLOAD_URL',  $_basePath . '/uploads/');

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
// On Render.com → set these as Environment Variables in dashboard
// Locally → update values below directly
define('SMTP_HOST',       getenv('SMTP_HOST')       ?: 'smtp.gmail.com');
define('SMTP_PORT',       465);
define('SMTP_USERNAME',   getenv('SMTP_USERNAME')   ?: 'your_gmail@gmail.com');
define('SMTP_PASSWORD',   getenv('SMTP_PASSWORD')   ?: 'xxxx xxxx xxxx xxxx');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'your_gmail@gmail.com');
define('SMTP_FROM_NAME',  'InterLink');
define('OTP_EXPIRY_MINS', 10);
