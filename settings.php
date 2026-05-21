<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/config.php';
requireLogin();

$pdo = getDB();
$uid = currentUserId();

// Auto-migrations: ensure optional columns & tables exist
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL");
} catch(Exception $e) {}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_blocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blocker_id INT NOT NULL, blocked_id INT NOT NULL,
        created_at DATETIME DEFAULT NOW(),
        UNIQUE KEY uq_block (blocker_id, blocked_id)
    )");
} catch(Exception $e) {}

// Load current user
$u = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
$u->execute([$uid]);
$user = $u->fetch();

// Load blocked users
$blocked = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.username, u.avatar_url, u.is_online
    FROM user_blocks b JOIN users u ON u.user_id = b.blocked_id
    WHERE b.blocker_id = ? ORDER BY b.created_at DESC
");
try { $blocked->execute([$uid]); $blockedList = $blocked->fetchAll(); }
catch(Exception $e) { $blockedList = []; }

$msg = $_SESSION['settings_msg'] ?? '';
$err = $_SESSION['settings_err'] ?? '';
unset($_SESSION['settings_msg'], $_SESSION['settings_err']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Settings — InterLink</title>
<meta name="base-url" content="<?= BASE_URL ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
<style>
html,body{overflow:auto!important;height:auto}
.settings-wrap{max-width:680px;margin:0 auto;padding:24px 16px 60px;min-height:100vh}
.settings-back{display:inline-flex;align-items:center;gap:8px;color:var(--text-secondary);font-size:.9rem;margin-bottom:20px;text-decoration:none;transition:color .2s}
.settings-back:hover{color:var(--accent)}
.settings-title{font-size:1.6rem;font-weight:800;margin-bottom:24px;background:linear-gradient(135deg,#fff,var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.settings-section{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:16px;margin-bottom:16px;overflow:hidden}
.settings-section-header{padding:16px 20px 12px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;gap:10px}
.settings-section-header .sh-icon{font-size:1.1rem}
.settings-section-header h2{font-size:.95rem;font-weight:700;color:var(--text-primary)}
.settings-row{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.04);gap:12px}
.settings-row:last-child{border-bottom:none}
.settings-row label{font-size:.875rem;color:var(--text-secondary);font-weight:500;min-width:120px}
.settings-row input,.settings-row textarea,.settings-row select{flex:1;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:9px 13px;color:var(--text-primary);font-size:.875rem;transition:border-color .2s;font-family:var(--font)}
.settings-row input:focus,.settings-row textarea:focus,.settings-row select:focus{border-color:var(--accent);outline:none}
.settings-row textarea{resize:vertical;min-height:72px}
.save-btn{background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#fff;border:none;border-radius:10px;padding:10px 24px;font-size:.875rem;font-weight:600;cursor:pointer;transition:all .2s}
.save-btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(79,142,247,.4)}
.section-footer{padding:14px 20px;display:flex;justify-content:flex-end}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.04)}
.toggle-row:last-child{border-bottom:none}
.toggle-info h4{font-size:.875rem;font-weight:600;color:var(--text-primary)}
.toggle-info p{font-size:.75rem;color:var(--text-muted);margin-top:2px}
.toggle{position:relative;width:44px;height:24px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:rgba(255,255,255,0.12);border-radius:24px;cursor:pointer;transition:.3s}
.toggle-slider:before{content:'';position:absolute;width:18px;height:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.3s}
.toggle input:checked+.toggle-slider{background:var(--accent)}
.toggle input:checked+.toggle-slider:before{transform:translateX(20px)}
.blocked-item{display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid rgba(255,255,255,0.04)}
.blocked-item:last-child{border-bottom:none}
.blocked-avatar{width:38px;height:38px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;color:#fff;flex-shrink:0}
.blocked-info{flex:1;min-width:0}
.blocked-info .bn{font-size:.875rem;font-weight:600}
.blocked-info .bu{font-size:.75rem;color:var(--text-muted)}
.unblock-btn{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#f87171;border-radius:8px;padding:6px 14px;font-size:.8rem;cursor:pointer;transition:all .2s}
.unblock-btn:hover{background:rgba(239,68,68,.2)}
.danger-btn{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;border-radius:10px;padding:10px 20px;font-size:.875rem;font-weight:600;cursor:pointer;transition:all .2s;width:100%}
.danger-btn:hover{background:rgba(239,68,68,.2)}
.alert-box{border-radius:10px;padding:12px 16px;font-size:.875rem;font-weight:500;margin-bottom:16px}
.alert-ok{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#86efac}
.alert-err{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.avatar-section{display:flex;align-items:center;gap:16px;padding:18px 20px}
.avatar-circle{width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--accent);flex-shrink:0}
.avatar-placeholder-big{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--purple));display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:800;color:#fff;flex-shrink:0;border:3px solid var(--accent)}
.avatar-upload-btn{background:rgba(79,142,247,.12);border:1px solid rgba(79,142,247,.3);color:var(--accent);border-radius:10px;padding:9px 18px;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s}
.avatar-upload-btn:hover{background:rgba(79,142,247,.2)}
</style>
</head>
<body>
<div class="settings-wrap">
  <a href="<?= BASE_URL ?>/chat.php" class="settings-back">← Back to Chat</a>
  <div class="settings-title">⚙️ Settings</div>

  <?php if($msg): ?><div class="alert-box alert-ok">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if($err): ?><div class="alert-box alert-err">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>

  <!-- ======= PROFILE ======= -->
  <div class="settings-section">
    <div class="settings-section-header"><span class="sh-icon">👤</span><h2>Profile</h2></div>
    <form method="POST" action="<?= BASE_URL ?>/api/settings/update_profile.php" enctype="multipart/form-data">
      <div class="avatar-section">
        <?php if(!empty($user['avatar_url']) && $user['avatar_url']!=='default.png'): ?>
          <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($user['avatar_url']) ?>" class="avatar-circle" alt="Avatar">
        <?php else: ?>
          <div class="avatar-placeholder-big"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
        <?php endif; ?>
        <div>
          <label class="avatar-upload-btn" for="avatar-file">📷 Change Photo</label>
          <input type="file" id="avatar-file" name="avatar" accept="image/*" style="display:none" onchange="this.form.submit()">
          <p style="font-size:.72rem;color:var(--text-muted);margin-top:6px">JPG, PNG or GIF · Max 5MB</p>
        </div>
      </div>
      <div class="settings-row"><label>Full Name</label><input name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>"></div>
      <div class="settings-row"><label>Username</label><input name="username" value="<?= htmlspecialchars($user['username']) ?>"></div>
      <div class="settings-row"><label>Bio</label><textarea name="bio" placeholder="Tell people about yourself…"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea></div>
      <div class="section-footer"><button type="submit" class="save-btn">Save Profile</button></div>
    </form>
  </div>

  <!-- ======= PRIVACY ======= -->
  <div class="settings-section">
    <div class="settings-section-header"><span class="sh-icon">🔒</span><h2>Privacy &amp; Security</h2></div>
    <form method="POST" action="<?= BASE_URL ?>/api/settings/update_privacy.php">
      <div class="toggle-row">
        <div class="toggle-info"><h4>Show Online Status</h4><p>Let others see when you're active</p></div>
        <label class="toggle"><input type="checkbox" name="show_online" value="1" <?= ($user['is_online'] ?? 1) ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info"><h4>Read Receipts</h4><p>Send read receipts when you view messages</p></div>
        <label class="toggle"><input type="checkbox" name="read_receipts" value="1" checked><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info"><h4>Message Requests</h4><p>Only friends can message you</p></div>
        <label class="toggle"><input type="checkbox" name="friends_only" value="1"><span class="toggle-slider"></span></label>
      </div>
      <div class="section-footer"><button type="submit" class="save-btn">Save Privacy</button></div>
    </form>
  </div>

  <!-- ======= NOTIFICATIONS ======= -->
  <div class="settings-section">
    <div class="settings-section-header"><span class="sh-icon">🔔</span><h2>Notifications</h2></div>
    <div class="toggle-row">
      <div class="toggle-info"><h4>Push Notifications</h4><p>Get notified when you receive messages</p></div>
      <label class="toggle"><input type="checkbox" id="push-toggle" checked><span class="toggle-slider"></span></label>
    </div>
    <div class="toggle-row">
      <div class="toggle-info"><h4>Message Preview</h4><p>Show message content in notifications</p></div>
      <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
    </div>
    <div class="toggle-row">
      <div class="toggle-info"><h4>Friend Requests</h4><p>Notify when someone sends a friend request</p></div>
      <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
    </div>
  </div>

  <!-- ======= BLOCKED USERS ======= -->
  <div class="settings-section">
    <div class="settings-section-header"><span class="sh-icon">🚫</span><h2>Blocked Users</h2></div>
    <?php if(empty($blockedList)): ?>
      <div style="padding:24px 20px;text-align:center;color:var(--text-muted);font-size:.875rem">No blocked users</div>
    <?php else: ?>
      <?php foreach($blockedList as $b):
        $init = strtoupper(substr($b['full_name'],0,2));
      ?>
      <div class="blocked-item">
        <div class="blocked-avatar"><?= $init ?></div>
        <div class="blocked-info">
          <div class="bn"><?= htmlspecialchars($b['full_name']) ?></div>
          <div class="bu">@<?= htmlspecialchars($b['username']) ?></div>
        </div>
        <button class="unblock-btn" onclick="unblockUser(<?= $b['user_id'] ?>, this)">Unblock</button>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- ======= ACCOUNT ======= -->
  <div class="settings-section">
    <div class="settings-section-header"><span class="sh-icon">🔑</span><h2>Account</h2></div>
    <form method="POST" action="<?= BASE_URL ?>/api/users/change_password.php">
      <div class="settings-row"><label>Current Password</label><input type="password" name="current_password" placeholder="••••••••"></div>
      <div class="settings-row"><label>New Password</label><input type="password" name="new_password" placeholder="Min 8 characters"></div>
      <div class="settings-row"><label>Confirm</label><input type="password" name="confirm_password" placeholder="Repeat new password"></div>
      <div class="section-footer"><button type="submit" class="save-btn">Change Password</button></div>
    </form>
  </div>

  <!-- ======= ABOUT ======= -->
  <div class="settings-section">
    <div class="settings-section-header"><span class="sh-icon">ℹ️</span><h2>About InterLink</h2></div>
    <div class="settings-row" style="flex-direction:column;align-items:flex-start;gap:4px">
      <span style="font-size:.875rem;font-weight:600">InterLink Messenger</span>
      <span style="font-size:.75rem;color:var(--text-muted)">Version 1.0.0 · Built with PHP &amp; MySQL</span>
    </div>
    <div class="settings-row"><label style="flex:1;color:var(--text-secondary)">Logged in as</label><span style="font-size:.875rem;color:var(--accent);font-weight:600">@<?= htmlspecialchars($user['username']) ?></span></div>
    <div style="padding:16px 20px;display:flex;gap:10px">
      <a href="<?= BASE_URL ?>/api/auth/logout.php" class="danger-btn" style="display:block;text-align:center;text-decoration:none">🚪 Log Out</a>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';

async function unblockUser(userId, btn) {
  if (!confirm('Unblock this user? They can message you again.')) return;
  try {
    const res  = await fetch(`${BASE_URL}/api/users/block.php`, {
      method:'POST', body: new URLSearchParams({ user_id: userId, action: 'unblock' })
    });
    const data = await res.json();
    if (data.success) { btn.closest('.blocked-item').remove(); }
    else alert(data.error || 'Failed to unblock');
  } catch(e) { alert('Connection error'); }
}

// Push notification toggle
document.getElementById('push-toggle')?.addEventListener('change', async function() {
  if (this.checked && 'Notification' in window && Notification.permission !== 'granted') {
    await Notification.requestPermission();
  }
});
</script>
</body>
</html>
