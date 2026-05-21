<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

$pdo = getDB();
$results = [];

// Fix all users: re-hash passwords submitted via this form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!$email || strlen($password) < 6) {
        $error = "Email and password (min 6 chars) are required.";
    } else {
        $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = "No user found with email/username: " . htmlspecialchars($email);
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")
                ->execute([$hash, $user['user_id']]);

            $success = "✅ Password updated for <strong>" . htmlspecialchars($user['username']) . "</strong> (ID: {$user['user_id']}). You can now <a href='index.php'>login here</a>.";
        }
    }
}

// List all users
$users = $pdo->query("SELECT user_id, username, email, status FROM users ORDER BY user_id")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>InterLink - Fix Password</title>
<style>
  body { font-family: sans-serif; background: #0f0f15; color: #e0e0e0; display:flex; flex-direction:column; align-items:center; padding:40px 20px; min-height:100vh; }
  .card { background:#1a1a2e; border:1px solid #333; border-radius:16px; padding:32px; width:100%; max-width:500px; margin-bottom:24px; }
  h1 { color:#60a5fa; margin:0 0 8px; }
  p.sub { color:#888; margin:0 0 24px; font-size:14px; }
  label { display:block; color:#aaa; font-size:13px; margin-bottom:6px; }
  input { width:100%; box-sizing:border-box; background:#0f0f1a; border:1px solid #444; color:#fff; padding:12px; border-radius:8px; margin-bottom:16px; font-size:15px; }
  button { width:100%; background:linear-gradient(135deg,#3b82f6,#6366f1); color:#fff; border:none; padding:13px; border-radius:8px; font-size:16px; font-weight:600; cursor:pointer; }
  .success { background:#0d2d0d; border:1px solid #2d6a2d; color:#6dff6d; padding:14px 16px; border-radius:8px; margin-bottom:16px; }
  .error { background:#2d0d0d; border:1px solid #6a2d2d; color:#ff6d6d; padding:14px 16px; border-radius:8px; margin-bottom:16px; }
  .warn { background:#2d2000; border:1px solid #6a4500; color:#ffa020; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:16px; }
  table { width:100%; border-collapse:collapse; font-size:13px; }
  th,td { border:1px solid #333; padding:8px 12px; text-align:left; }
  th { background:#111; color:#60a5fa; }
  .active { color:#6dff6d; } .banned { color:#ff6d6d; }
</style>
</head>
<body>

<div class="card">
  <h1>🔑 Reset Password</h1>
  <p class="sub">Enter your email (or username) and set a new password.</p>

  <div class="warn">⚠️ Delete <code>fix_password.php</code> after use!</div>

  <?php if (!empty($success)): ?><div class="success"><?= $success ?></div><?php endif; ?>
  <?php if (!empty($error)):   ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST">
    <label>Email Address or Username</label>
    <input type="text" name="email" placeholder="raukiraj37@gmail.com or riya_sharma07i" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

    <label>New Password</label>
    <input type="password" name="password" placeholder="Enter new password (min 6 chars)" required>

    <button type="submit">Reset Password</button>
  </form>
</div>

<div class="card">
  <h2 style="color:#60a5fa;margin:0 0 16px">All Users</h2>
  <table>
    <tr><th>ID</th><th>Username</th><th>Email</th><th>Status</th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= $u['user_id'] ?></td>
      <td><?= htmlspecialchars($u['username']) ?></td>
      <td><?= htmlspecialchars($u['email']) ?></td>
      <td class="<?= $u['status'] ?>"><?= $u['status'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

</body>
</html>
