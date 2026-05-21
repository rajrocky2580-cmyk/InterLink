<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
requireLogin();

$uid  = currentUserId();
$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->execute([$uid]);
$user = $stmt->fetch();
$csrf = generateCsrfToken();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title>InterLink — My Profile</title>
  <link rel="icon" href="assets/images/favicon.png" type="image/png">
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<div style="min-height:100vh;padding:32px 20px;display:flex;flex-direction:column;align-items:center;gap:24px">

  <!-- Back -->
  <div style="width:100%;max-width:560px;display:flex;align-items:center;gap:12px">
    <a href="chat.php" class="btn btn-ghost btn-sm">← Back to Chat</a>
    <h1 style="font-size:1.25rem;font-weight:700">My Profile</h1>
  </div>

  <div class="glass-card fade-in-up" style="width:100%;max-width:560px;padding:32px">

    <!-- Avatar Section -->
    <div style="display:flex;flex-direction:column;align-items:center;gap:16px;margin-bottom:32px">
      <div class="relative" style="cursor:pointer" onclick="document.getElementById('avatar-input').click()">
        <?php
        $avatarSrc = BASE_URL . '/uploads/avatars/' . $user['avatar_url'];
        $initials  = strtoupper(substr($user['full_name'],0,2));
        $hasAvatar = $user['avatar_url'] && $user['avatar_url'] !== 'default.png';
        ?>
        <?php if ($hasAvatar): ?>
        <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="Avatar" id="avatar-display"
             style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--accent)">
        <?php else: ?>
        <div class="avatar-placeholder" id="avatar-display" style="width:96px;height:96px;background:var(--accent);font-size:1.8rem;border:3px solid var(--accent)">
          <?= $initials ?>
        </div>
        <?php endif; ?>
        <div style="position:absolute;bottom:0;right:0;width:28px;height:28px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;border:2px solid var(--bg-secondary)">✏️</div>
      </div>
      <div style="text-align:center">
        <div style="font-weight:700;font-size:1.1rem"><?= htmlspecialchars($user['full_name']) ?></div>
        <div class="text-muted text-sm">@<?= htmlspecialchars($user['username']) ?></div>
        <div class="text-muted text-xs" style="margin-top:4px">Member since <?= date('M Y', strtotime($user['created_at'])) ?></div>
      </div>
    </div>

    <div id="alert-box"></div>

    <form id="profile-form" class="flex-col gap-4" enctype="multipart/form-data">
      <input type="file" id="avatar-input" name="avatar" accept="image/*" style="display:none" onchange="previewAvatar(this)">

      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input class="form-input" type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Bio</label>
        <textarea class="form-input" id="bio" name="bio" rows="3" placeholder="Tell people a bit about yourself…" style="resize:vertical"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Phone</label>
        <input class="form-input" type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+1 234 567 8900">
      </div>

      <div class="form-group">
        <label class="form-label">Email</label>
        <input class="form-input" type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled style="opacity:.6">
        <span class="text-xs text-muted">Email cannot be changed</span>
      </div>

      <div class="flex gap-3" style="margin-top:8px">
        <button type="submit" class="btn btn-primary" id="save-btn">
          <span id="save-text">Save Changes</span>
          <span id="save-spin" class="spinner hidden"></span>
        </button>
        <a href="chat.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>

    <!-- Change Password -->
    <hr style="border:none;border-top:1px solid var(--border);margin:28px 0">
    <h3 style="margin-bottom:16px">Change Password</h3>
    <form id="pw-form" class="flex-col gap-3">
      <div class="form-group">
        <label class="form-label">Current Password</label>
        <input class="form-input" type="password" id="cur_pw" placeholder="••••••••">
      </div>
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input class="form-input" type="password" id="new_pw" placeholder="Min. 8 characters" minlength="8">
      </div>
      <button type="submit" class="btn btn-ghost btn-sm" style="align-self:flex-start">Update Password</button>
    </form>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';

function previewAvatar(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const avatar = document.getElementById('avatar-display');
    if (avatar) {
      if (avatar.tagName === 'IMG') {
        avatar.src = e.target.result;
      } else {
        // Replace placeholder with img
        const img = document.createElement('img');
        img.src = e.target.result;
        img.id = 'avatar-display';
        img.alt = 'Avatar';
        img.style.cssText = 'width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--accent)';
        avatar.replaceWith(img);
      }
    }
  };
  reader.readAsDataURL(file);
}

document.getElementById('profile-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn  = document.getElementById('save-btn');
  const txt  = document.getElementById('save-text');
  const spin = document.getElementById('save-spin');
  const alertBox = document.getElementById('alert-box');

  btn.disabled = true; txt.classList.add('hidden'); spin.classList.remove('hidden');

  const formData = new FormData(this);
  // Add fields that aren't in form elements with name attributes
  formData.set('full_name', document.getElementById('full_name').value);
  formData.set('bio', document.getElementById('bio').value);
  formData.set('phone', document.getElementById('phone').value);

  try {
    const res  = await fetch(`${BASE_URL}/api/users/profile.php`, { method:'POST', body: formData });
    const data = await res.json();
    if (data.success) {
      alertBox.innerHTML = `<div class="alert alert-success">✅ Profile updated successfully!</div>`;
    } else {
      alertBox.innerHTML = `<div class="alert alert-error">⚠️ ${data.error || 'Update failed.'}</div>`;
    }
  } catch(err) {
    alertBox.innerHTML = `<div class="alert alert-error">⚠️ Connection error.</div>`;
  }
  btn.disabled = false; txt.classList.remove('hidden'); spin.classList.add('hidden');
  setTimeout(() => alertBox.innerHTML = '', 4000);
});

// Password Change Handler
document.getElementById('pw-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const alertBox = document.getElementById('alert-box');
  const curPw = document.getElementById('cur_pw').value;
  const newPw = document.getElementById('new_pw').value;

  if (!curPw || !newPw) {
    alertBox.innerHTML = `<div class="alert alert-error">⚠️ Please fill in both password fields.</div>`;
    setTimeout(() => alertBox.innerHTML = '', 4000);
    return;
  }
  if (newPw.length < 8) {
    alertBox.innerHTML = `<div class="alert alert-error">⚠️ New password must be at least 8 characters.</div>`;
    setTimeout(() => alertBox.innerHTML = '', 4000);
    return;
  }

  try {
    const res = await fetch(`${BASE_URL}/api/users/change_password.php`, {
      method: 'POST',
      body: new URLSearchParams({ current_password: curPw, new_password: newPw })
    });
    const data = await res.json();
    if (data.success) {
      alertBox.innerHTML = `<div class="alert alert-success">✅ Password updated successfully!</div>`;
      document.getElementById('cur_pw').value = '';
      document.getElementById('new_pw').value = '';
    } else {
      alertBox.innerHTML = `<div class="alert alert-error">⚠️ ${data.error || 'Password change failed.'}</div>`;
    }
  } catch(err) {
    alertBox.innerHTML = `<div class="alert alert-error">⚠️ Connection error.</div>`;
  }
  setTimeout(() => alertBox.innerHTML = '', 4000);
});
</script>
</body>
</html>
