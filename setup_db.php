<?php
// =========================================================
// InterLink — One-Click Database & Environment Setup
// =========================================================
// XAMPP:        Visit http://localhost/InterLink/setup_db.php
// InfinityFree: Visit https://yourdomain.com/InterLink/setup_db.php
// NOTE: On InfinityFree update DB_HOST, DB_NAME, DB_USER, DB_PASS in includes/config.php
//       then update the credentials below too.

// ---------- Read DB credentials from environment variables (Render) or fallback to XAMPP ----------
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'InterLink';
$results = [];
$errors  = [];

// ---------- 1. Create database & tables ----------
try {
    $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Create database (may fail on shared hosting — create via control panel instead)
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $results[] = "✅ Database '{$dbName}' created / already exists.";
    } catch (PDOException $e) {
        $results[] = "ℹ️ Could not create database (may already exist on shared host): " . $e->getMessage();
    }

    $pdo->exec("USE `{$dbName}`");

    // Read and execute schema
    $schemaPath = __DIR__ . '/sql/schema.sql';
    if (!file_exists($schemaPath)) {
        $errors[] = "❌ Schema file not found: sql/schema.sql";
    } else {
        $sql = file_get_contents($schemaPath);
        // Remove the CREATE DATABASE and USE lines (we already did that)
        $sql = preg_replace('/CREATE DATABASE.*?;\s*/i', '', $sql);
        $sql = preg_replace('/USE\s+\w+;\s*/i', '', $sql);

        // Split by semicolons and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $tableCount = 0;
        foreach ($statements as $stmt) {
            if (empty($stmt) || $stmt === '--') continue;
            try {
                $pdo->exec($stmt);
                if (stripos($stmt, 'CREATE TABLE') !== false) $tableCount++;
                if (stripos($stmt, 'INSERT INTO') !== false) $results[] = "✅ Default admin user seeded.";
            } catch (PDOException $e) {
                // Ignore duplicate key errors for seed data
                if ($e->getCode() != 23000) {
                    $errors[] = "⚠️ SQL: " . substr($stmt, 0, 60) . "... → " . $e->getMessage();
                } else {
                    $results[] = "ℹ️ Admin user already exists (skipped).";
                }
            }
        }
        $results[] = "✅ Schema executed — $tableCount table(s) processed.";
    }
    // ---------- 1b. Live schema patches for existing databases ----------
    // These ALTER statements are safe to run on already-set-up databases.
    $patches = [
        // Add 'video' to message_type ENUM (was missing, broke video uploads)
        "ALTER TABLE messages MODIFY message_type ENUM('text','image','file','video','audio','system') DEFAULT 'text'",
        // Allow files.message_id to be NULL so upload.php can record files before the message row exists
        "ALTER TABLE files MODIFY message_id INT NULL",
        // Create call_signals table for WebRTC signaling relay (missing from original schema)
        "CREATE TABLE IF NOT EXISTS call_signals (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            from_user       INT NOT NULL,
            to_user         INT NOT NULL,
            conversation_id INT DEFAULT 0,
            type            VARCHAR(30) NOT NULL,
            payload         MEDIUMTEXT,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (from_user) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (to_user)   REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_to_user (to_user),
            INDEX idx_created (created_at)
        )",
        // Create password_resets table for OTP-based forgot password flow
        "CREATE TABLE IF NOT EXISTS password_resets (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT NOT NULL,
            otp        VARCHAR(6) NOT NULL,
            expires_at DATETIME NOT NULL,
            used       TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_otp (otp)
        )",
        // Create friendships table (was missing from original schema — CRITICAL fix)
        "CREATE TABLE IF NOT EXISTS friendships (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            requester_id INT NOT NULL,
            addressee_id INT NOT NULL,
            status       ENUM('pending','accepted','rejected','blocked') DEFAULT 'pending',
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (requester_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (addressee_id) REFERENCES users(user_id) ON DELETE CASCADE,
            UNIQUE KEY unique_friendship (requester_id, addressee_id),
            INDEX idx_addressee (addressee_id),
            INDEX idx_status (status)
        )",
    ];
    foreach ($patches as $patch) {
        try {
            $pdo->exec($patch);
            $results[] = "✅ Schema patch applied: " . substr($patch, 0, 60) . '...';
        } catch (PDOException $e) {
            // Usually means column/enum already correct — not an error worth showing
            $results[] = "ℹ️ Patch skipped (already applied): " . substr($patch, 0, 50) . '...';
        }
    }
} catch (PDOException $e) {
    $errors[] = "❌ Database connection failed: " . $e->getMessage();
}

// ---------- 2. Create upload directories ----------
$dirs = [
    __DIR__ . '/uploads',
    __DIR__ . '/uploads/avatars',
    __DIR__ . '/uploads/images',
    __DIR__ . '/uploads/videos',
    __DIR__ . '/uploads/audio',
    __DIR__ . '/uploads/files',
];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0777, true)) {
            $results[] = "✅ Created directory: " . basename($dir);
        } else {
            $errors[] = "❌ Failed to create: $dir";
        }
    } else {
        $results[] = "ℹ️ Directory exists: " . str_replace(__DIR__, '', $dir);
    }
}

// ---------- 3. Create default avatar ----------
$avatarPath = __DIR__ . '/uploads/avatars/default.png';
if (!file_exists($avatarPath)) {
    // Generate a simple 200x200 default avatar with initials
    if (function_exists('imagecreatetruecolor')) {
        $img = imagecreatetruecolor(200, 200);
        $bg = imagecolorallocate($img, 79, 142, 247); // accent blue
        imagefill($img, 0, 0, $bg);
        $white = imagecolorallocate($img, 255, 255, 255);
        // Draw a simple user silhouette circle
        imagefilledellipse($img, 100, 75, 70, 70, $white);
        imagefilledellipse($img, 100, 180, 120, 100, $white);
        imagepng($img, $avatarPath);
        imagedestroy($img);
        $results[] = "✅ Default avatar created.";
    } else {
        // Fallback: create a tiny 1x1 PNG
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        file_put_contents($avatarPath, $png);
        $results[] = "✅ Default avatar placeholder created (GD not available).";
    }
} else {
    $results[] = "ℹ️ Default avatar already exists.";
}

// ---------- 4. Create default group avatar ----------
$groupAvatar = __DIR__ . '/uploads/avatars/group_default.png';
if (!file_exists($groupAvatar)) {
    if (function_exists('imagecreatetruecolor')) {
        $img = imagecreatetruecolor(200, 200);
        $bg = imagecolorallocate($img, 139, 92, 246); // purple
        imagefill($img, 0, 0, $bg);
        $white = imagecolorallocate($img, 255, 255, 255);
        // Two overlapping circles for group
        imagefilledellipse($img, 80, 85, 60, 60, $white);
        imagefilledellipse($img, 120, 85, 60, 60, $white);
        imagefilledellipse($img, 100, 170, 130, 90, $white);
        imagepng($img, $groupAvatar);
        imagedestroy($img);
        $results[] = "✅ Default group avatar created.";
    }
} else {
    $results[] = "ℹ️ Group avatar already exists.";
}

// ---------- 5. Update admin password hash ----------
// The schema seeds 'password' as the hash, let's update it to 'Admin@1234'
try {
    if (isset($pdo)) {
        $hash = password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin' AND password_hash = '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'");
        $stmt->execute([$hash]);
        if ($stmt->rowCount() > 0) {
            $results[] = "✅ Admin password updated to 'Admin@1234'.";
        } else {
            $results[] = "ℹ️ Admin password already set or user not found.";
        }
    }
} catch (PDOException $e) {
    $errors[] = "⚠️ Could not update admin password: " . $e->getMessage();
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>InterLink — Setup</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: #0a0e1a;
      color: #f1f5f9;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background-image:
        radial-gradient(ellipse at 20% 50%, rgba(79,142,247,0.06) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 10%, rgba(139,92,246,0.05) 0%, transparent 50%);
    }
    .card {
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.10);
      border-radius: 20px;
      backdrop-filter: blur(20px);
      padding: 40px;
      max-width: 600px;
      width: 100%;
    }
    h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 8px; }
    h1 span { color: #4f8ef7; }
    .subtitle { color: #94a3b8; font-size: .9rem; margin-bottom: 24px; }
    .result { padding: 10px 14px; border-radius: 10px; margin: 6px 0; font-size: .875rem; }
    .result.ok { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); }
    .result.err { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #fca5a5; }
    .creds {
      margin-top: 24px; padding: 16px; border-radius: 12px;
      background: rgba(79,142,247,0.1); border: 1px solid rgba(79,142,247,0.2);
    }
    .creds h3 { font-size: 1rem; margin-bottom: 8px; color: #93c5fd; }
    .creds p { font-size: .875rem; color: #cbd5e1; margin: 4px 0; }
    .creds code { background: rgba(0,0,0,0.3); padding: 2px 8px; border-radius: 6px; font-family: monospace; }
    .btn {
      display: inline-block; margin-top: 20px; padding: 12px 28px;
      background: linear-gradient(135deg, #4f8ef7, #3a74d9);
      color: #fff; border-radius: 12px; text-decoration: none;
      font-weight: 600; font-size: .9375rem;
      box-shadow: 0 4px 15px rgba(79,142,247,0.25);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn:hover { transform: translateY(-1px); box-shadow: 0 6px 25px rgba(79,142,247,0.4); }
  </style>
</head>
<body>
<div class="card">
  <h1>Inter<span>Link</span> Setup</h1>
  <p class="subtitle">One-click environment setup</p>

  <?php foreach ($results as $r): ?>
    <div class="result ok"><?= $r ?></div>
  <?php endforeach; ?>

  <?php foreach ($errors as $e): ?>
    <div class="result err"><?= $e ?></div>
  <?php endforeach; ?>

  <?php if (empty($errors)): ?>
    <div class="creds">
      <h3>🔐 Admin Credentials</h3>
      <p>Email: <code>admin@InterLink.local</code></p>
      <p>Password: <code>Admin@1234</code></p>
    </div>
    <a href="index.php" class="btn">→ Go to InterLink</a>
  <?php else: ?>
    <p style="margin-top:16px;color:#fca5a5;font-size:.875rem">Fix the errors above and refresh this page.</p>
  <?php endif; ?>
</div>
</body>
</html>
