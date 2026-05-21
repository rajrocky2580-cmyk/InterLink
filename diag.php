<?php
// InterLink — Quick Diagnostic (DELETE AFTER USE)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre style='font-family:monospace;background:#0a0e1a;color:#e2e8f0;padding:20px;min-height:100vh;margin:0;font-size:14px'>";
echo "<b style='color:#4f8ef7;font-size:18px'>InterLink — 500 Error Diagnostic</b>\n\n";

// 1. PHP Version
echo "✅ PHP Version: " . PHP_VERSION . "\n";

// 2. Required Extensions
$exts = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'session'];
foreach ($exts as $ext) {
    echo (extension_loaded($ext) ? "✅" : "❌") . " Extension [$ext]: " . (extension_loaded($ext) ? "loaded" : "MISSING") . "\n";
}
echo "\n";

// 3. DB Connection
echo "<b style='color:#fbbf24'>--- Database ---</b>\n";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=InterLink;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✅ DB Connection: OK\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "✅ Tables (" . count($tables) . "): " . implode(', ', $tables) . "\n";
} catch (PDOException $e) {
    echo "❌ DB Connection FAILED: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Include Files
echo "<b style='color:#fbbf24'>--- Include Files ---</b>\n";
$files = [
    'includes/config.php',
    'includes/auth.php',
    'includes/db.php',
    'includes/helpers.php',
];
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    echo (file_exists($path) ? "✅" : "❌") . " $f: " . (file_exists($path) ? "exists" : "MISSING") . "\n";
}
echo "\n";

// 5. Try loading config (catches any fatal errors)
echo "<b style='color:#fbbf24'>--- Loading config.php ---</b>\n";
try {
    require_once __DIR__ . '/includes/config.php';
    echo "✅ config.php loaded OK\n";
    echo "   BASE_URL = " . BASE_URL . "\n";
    echo "   SMTP_HOST = " . SMTP_HOST . "\n";
} catch (Throwable $e) {
    echo "❌ config.php FATAL: " . $e->getMessage() . " on line " . $e->getLine() . "\n";
}
echo "\n";

// 6. Try loading auth.php
echo "<b style='color:#fbbf24'>--- Loading auth.php ---</b>\n";
try {
    require_once __DIR__ . '/includes/auth.php';
    echo "✅ auth.php loaded OK\n";
    echo "   Session status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE") . "\n";
} catch (Throwable $e) {
    echo "❌ auth.php FATAL: " . $e->getMessage() . " on line " . $e->getLine() . "\n";
}
echo "\n";

// 7. Session test
echo "<b style='color:#fbbf24'>--- Session ---</b>\n";
echo "   session_name: " . session_name() . "\n";
echo "   session_id: " . (session_id() ?: "(none)") . "\n";
echo "   isLoggedIn: " . (isLoggedIn() ? "YES" : "NO") . "\n";
echo "\n";

// 8. Writable dirs
echo "<b style='color:#fbbf24'>--- Writable Directories ---</b>\n";
$dirs = ['uploads', 'assets'];
foreach ($dirs as $d) {
    $path = __DIR__ . '/' . $d;
    echo (is_writable($path) ? "✅" : "⚠️") . " $d: " . (is_writable($path) ? "writable" : "NOT writable") . "\n";
}

echo "\n<b style='color:#4ade80'>Diagnostic complete! Delete diag.php when done.</b>\n";
echo "</pre>";
