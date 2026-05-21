<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

$pdo = getDB();

// Handle password reset form submission
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reset_password') {
        $uid  = (int)$_POST['user_id'];
        $pass = $_POST['new_password'];
        if (strlen($pass) >= 6) {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE users SET password_hash=? WHERE user_id=?")->execute([$hash, $uid]);
            $msg = "✅ Password updated for user ID $uid";
        } else {
            $msg = "❌ Password must be at least 6 characters.";
        }
    }
    if ($_POST['action'] === 'fix_email') {
        $uid   = (int)$_POST['user_id'];
        $email = strtolower(trim($_POST['new_email']));
        $pdo->prepare("UPDATE users SET email=? WHERE user_id=?")->execute([$email, $uid]);
        $msg = "✅ Email updated for user ID $uid";
    }
    if ($_POST['action'] === 'activate') {
        $uid = (int)$_POST['user_id'];
        $pdo->prepare("UPDATE users SET status='active' WHERE user_id=?")->execute([$uid]);
        $msg = "✅ User ID $uid set to active.";
    }
}

$users = $pdo->query("SELECT user_id, username, email, LEFT(password_hash,30) AS hash_preview, role, status, created_at FROM users ORDER BY user_id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<title>InterLink - Debug Users</title>
<style>
body{font-family:monospace;background:#111;color:#eee;padding:20px}
table{border-collapse:collapse;width:100%;margin-top:20px}
th,td{border:1px solid #444;padding:8px 12px;text-align:left}
th{background:#222;color:#7df}
tr:hover{background:#1a1a1a}
.msg{background:#1a3a1a;border:1px solid #4a4;color:#afa;padding:10px;margin:10px 0;border-radius:6px}
.err{background:#3a1a1a;border:1px solid #a44;color:#faa;padding:10px;margin:10px 0;border-radius:6px}
form{display:inline}
input{background:#222;color:#eee;border:1px solid #555;padding:4px 8px;border-radius:4px}
button{background:#246;color:#fff;border:none;padding:5px 10px;border-radius:4px;cursor:pointer}
h1{color:#7df}
.warn{color:#fa0;font-size:12px}
</style>
</head>
<body>
<h1>🔍 InterLink — User Debug Panel</h1>
<p class="warn">⚠️ DELETE this file after debugging: <code>debug_users.php</code></p>

<?php if ($msg): ?>
<div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<table>
<tr>
    <th>ID</th><th>Username</th><th>Email (stored)</th>
    <th>Hash preview</th><th>Role</th><th>Status</th><th>Created</th>
    <th>Fix Email</th><th>Reset Password</th><th>Activate</th>
</tr>
<?php foreach ($users as $u): ?>
<tr>
    <td><?= $u['user_id'] ?></td>
    <td><?= htmlspecialchars($u['username']) ?></td>
    <td style="color:<?= str_contains($u['email'],'&') || str_contains($u['email'],'#') ? '#f66' : '#afa' ?>">
        <?= htmlspecialchars($u['email']) ?>
        <?php if (str_contains($u['email'],'&') || str_contains($u['email'],'#')): ?>
        <br><span style="color:#f66;font-size:11px">⚠️ CORRUPTED — HTML encoded!</span>
        <?php endif; ?>
    </td>
    <td><?= htmlspecialchars($u['hash_preview']) ?>...</td>
    <td><?= $u['role'] ?></td>
    <td style="color:<?= $u['status']==='active' ? '#afa' : '#f66' ?>"><?= $u['status'] ?></td>
    <td><?= $u['created_at'] ?></td>
    <td>
        <form method="POST">
            <input type="hidden" name="action" value="fix_email">
            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
            <input type="email" name="new_email" placeholder="new@email.com" style="width:160px">
            <button type="submit">Fix</button>
        </form>
    </td>
    <td>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
            <input type="password" name="new_password" placeholder="new password" style="width:120px">
            <button type="submit">Reset</button>
        </form>
    </td>
    <td>
        <?php if ($u['status'] !== 'active'): ?>
        <form method="POST">
            <input type="hidden" name="action" value="activate">
            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
            <button type="submit" style="background:#142">Activate</button>
        </form>
        <?php else: ?>
        <span style="color:#4a4">✓ Active</span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</table>

<br>
<p><strong>Total users:</strong> <?= count($users) ?></p>
<p style="color:#fa0">⚠️ Remember to delete <code>debug_users.php</code> when done!</p>
</body>
</html>
