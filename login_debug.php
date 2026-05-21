<?php
// ============================================================
// InterLink — Login Diagnostics (DELETE AFTER USE)
// ============================================================
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

$pdo = getDB();
$steps = [];
$fixMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = strtolower(trim($_POST['email'] ?? ''));
    $password   = $_POST['password'] ?? '';

    // Step 1: Was the email/username found at all?
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $steps[] = ['❌', 'User lookup', "No user found with email/username: <code>" . htmlspecialchars($identifier) . "</code>"];
        $steps[] = ['💡', 'Fix', "This email is not in the database. Did you register with a different address? Check <a href='debug_users.php' style='color:#7df'>debug_users.php</a> to see all accounts."];
    } else {
        $steps[] = ['✅', 'User found', "Found user <strong>" . htmlspecialchars($user['username']) . "</strong> (ID: {$user['user_id']})"];

        // Step 2: Check stored email vs entered
        if ($user['email'] !== $identifier) {
            $steps[] = ['⚠️', 'Email mismatch', "Entered: <code>" . htmlspecialchars($identifier) . "</code> — Stored: <code>" . htmlspecialchars($user['email']) . "</code>"];
        } else {
            $steps[] = ['✅', 'Email match', "Stored email matches exactly"];
        }

        // Step 3: Check if hash looks valid
        $hashPreview = substr($user['password_hash'], 0, 7);
        if (!in_array($hashPreview, ['$2y$10', '$2y$12'])) {
            $steps[] = ['❌', 'Hash format', "Password hash looks corrupted or not bcrypt. Preview: <code>" . htmlspecialchars(substr($user['password_hash'], 0, 30)) . "...</code>"];
            $steps[] = ['💡', 'Fix', "Use <a href='fix_password.php' style='color:#7df'>fix_password.php</a> to reset this account's password."];
        } else {
            $steps[] = ['✅', 'Hash format', "Hash is valid bcrypt format ($hashPreview...)"];
        }

        // Step 4: Verify password
        if ($password) {
            $verified = password_verify($password, $user['password_hash']);
            if ($verified) {
                $steps[] = ['✅', 'Password verify', "password_verify() returned TRUE — password is correct!"];
            } else {
                $steps[] = ['❌', 'Password verify', "password_verify() returned FALSE — the password does NOT match the stored hash."];
                $steps[] = ['💡', 'Fix', "Use <a href='fix_password.php' style='color:#7df'>fix_password.php</a> to set a new password for this account."];
            }
        }

        // Step 5: Account status
        if ($user['status'] !== 'active') {
            $steps[] = ['❌', 'Account status', "Account is <strong>{$user['status']}</strong> — it must be <strong>active</strong> to log in."];
            $steps[] = ['💡', 'Fix', "Use <a href='debug_users.php' style='color:#7df'>debug_users.php</a> and click <em>Activate</em> next to this user."];
        } else {
            $steps[] = ['✅', 'Account status', "Account is <strong>active</strong>"];
        }

        // Step 6: Would the full login succeed?
        $wouldSucceed = $user && password_verify($password, $user['password_hash']) && $user['status'] === 'active';
        if ($wouldSucceed) {
            $steps[] = ['🎉', 'Overall result', "Login WOULD succeed. If you're still getting errors, the issue may be a session/cookie problem. Try clearing cookies and logging in again."];
        } else {
            $steps[] = ['💥', 'Overall result', "Login FAILS at one of the steps above. Follow the fix suggestions."];
        }

        // Auto-fix option: reset password in one click
        if (isset($_POST['fix_password']) && strlen($_POST['new_password'] ?? '') >= 6) {
            $newHash = password_hash($_POST['new_password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE users SET password_hash=?, status='active' WHERE user_id=?")->execute([$newHash, $user['user_id']]);
            $fixMsg = "✅ Password reset & account activated for <strong>" . htmlspecialchars($user['username']) . "</strong>. <a href='index.php'>Try logging in now →</a>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InterLink — Login Diagnostics</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:'Segoe UI',system-ui,sans-serif;background:#0a0e1a;color:#e2e8f0;min-height:100vh;padding:32px 16px;display:flex;flex-direction:column;align-items:center;gap:24px}
  .card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:32px;width:100%;max-width:640px;backdrop-filter:blur(20px)}
  h1{font-size:1.6rem;font-weight:800;margin-bottom:4px}
  h1 span{color:#4f8ef7}
  .warn{background:rgba(234,179,8,.1);border:1px solid rgba(234,179,8,.3);color:#fbbf24;padding:10px 16px;border-radius:10px;font-size:.85rem;margin-bottom:20px}
  label{display:block;font-size:.8rem;color:#94a3b8;margin-bottom:6px;margin-top:14px}
  input{width:100%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);color:#fff;padding:11px 14px;border-radius:10px;font-size:.95rem}
  input:focus{outline:none;border-color:#4f8ef7}
  .btn{margin-top:20px;width:100%;padding:12px;background:linear-gradient(135deg,#4f8ef7,#6366f1);color:#fff;border:none;border-radius:12px;font-size:1rem;font-weight:700;cursor:pointer;transition:.2s}
  .btn:hover{opacity:.9;transform:translateY(-1px)}
  .step{display:flex;gap:12px;padding:12px 16px;border-radius:10px;margin:6px 0;font-size:.88rem;align-items:flex-start;line-height:1.5}
  .step.ok{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2)}
  .step.err{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#fca5a5}
  .step.warn{background:rgba(234,179,8,.08);border:1px solid rgba(234,179,8,.2);color:#fde68a}
  .step.fix{background:rgba(79,142,247,.08);border:1px solid rgba(79,142,247,.2);color:#93c5fd}
  .step.party{background:rgba(139,92,246,.1);border:1px solid rgba(139,92,246,.3);color:#c4b5fd;font-weight:600}
  .step.boom{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#fca5a5;font-weight:700}
  .icon{font-size:1.1rem;flex-shrink:0;margin-top:1px}
  .key{font-weight:600;min-width:140px;color:#e2e8f0}
  .fix-box{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);border-radius:14px;padding:20px;margin-top:16px}
  .fix-box h3{color:#4ade80;margin-bottom:12px;font-size:1rem}
  .success-msg{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#4ade80;padding:14px 18px;border-radius:12px;font-size:.9rem;margin-top:16px}
  a{color:#4f8ef7;text-decoration:none}a:hover{text-decoration:underline}
  code{background:rgba(0,0,0,.4);padding:2px 7px;border-radius:5px;font-family:monospace;font-size:.85em}
</style>
</head>
<body>

<div class="card">
  <h1>Inter<span>Link</span> Login Diagnostics</h1>
  <p style="color:#64748b;font-size:.85rem;margin-top:2px;margin-bottom:20px">Step-by-step login failure analyzer</p>

  <div class="warn">⚠️ <strong>Security notice:</strong> Delete <code>login_debug.php</code> after fixing your issue.</div>

  <?php if ($fixMsg): ?>
    <div class="success-msg"><?= $fixMsg ?></div>
  <?php endif; ?>

  <form method="POST">
    <label>Email or Username</label>
    <input type="text" name="email" placeholder="raukiraj37@gmail.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

    <label>Password to test</label>
    <input type="password" name="password" placeholder="Enter the password you're trying" value="<?= htmlspecialchars($_POST['password'] ?? '') ?>">

    <button type="submit" class="btn">🔍 Run Diagnostics</button>
  </form>
</div>

<?php if (!empty($steps)): ?>
<div class="card">
  <h2 style="margin-bottom:16px;font-size:1.1rem">Diagnostic Results</h2>
  <?php foreach ($steps as [$icon, $key, $msg]):
    $cls = match($icon) {
        '✅' => 'ok',
        '❌','💥' => 'err',
        '⚠️' => 'warn',
        '💡' => 'fix',
        '🎉' => 'party',
        default => 'warn'
    };
  ?>
  <div class="step <?= $cls ?>">
    <span class="icon"><?= $icon ?></span>
    <span class="key"><?= htmlspecialchars($key) ?></span>
    <span><?= $msg ?></span>
  </div>
  <?php endforeach; ?>

  <?php
  // Check if user was found and show quick fix
  $stmt2 = $pdo->prepare("SELECT user_id, username, status FROM users WHERE email=? OR username=? LIMIT 1");
  $stmt2->execute([strtolower(trim($_POST['email'] ?? '')), strtolower(trim($_POST['email'] ?? ''))]);
  $u2 = $stmt2->fetch(PDO::FETCH_ASSOC);
  if ($u2):
  ?>
  <div class="fix-box">
    <h3>⚡ Quick Fix — Reset Password & Activate Account</h3>
    <form method="POST">
      <input type="hidden" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      <input type="hidden" name="fix_password" value="1">
      <label>New Password (min 6 chars)</label>
      <input type="password" name="new_password" placeholder="Enter your new password" required minlength="6">
      <input type="hidden" name="password" value="">
      <button type="submit" class="btn" style="background:linear-gradient(135deg,#22c55e,#16a34a);margin-top:16px">✅ Reset Password & Activate</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<p style="color:#475569;font-size:.78rem;text-align:center">Remember to <strong style="color:#ef4444">delete login_debug.php</strong> once your issue is resolved.</p>

</body>
</html>
