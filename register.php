<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/chat.php');
    exit;
}
$csrf = generateCsrfToken();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title>InterLink — Create Account</title>
  <meta name="description" content="Create your InterLink account and start messaging instantly.">
  <link rel="icon" href="assets/images/favicon.png" type="image/png">
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card glass-card fade-in-up" style="max-width:480px">

    <div class="auth-logo">
      <div class="auth-logo-icon">💬</div>
      <div class="auth-logo-text">Inter<span>Link</span></div>
    </div>

    <h1 style="font-size:1.6rem;margin-bottom:4px">Create your account</h1>
    <p class="text-muted" style="margin-bottom:28px;font-size:.9rem">Join InterLink and start messaging</p>

    <div id="alert-box"></div>

    <form id="register-form" class="flex-col gap-3">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">

      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input class="form-input" type="text" id="full_name" placeholder="John Doe" required>
      </div>

      <div class="form-group">
        <label class="form-label">Username</label>
        <div class="relative">
          <input class="form-input" type="text" id="username" placeholder="johndoe" required pattern="[a-zA-Z0-9_]+" style="padding-right:36px">
          <span id="username-status" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:.9rem"></span>
        </div>
        <span id="username-hint" class="text-xs text-muted">Letters, numbers and underscores only</span>
      </div>

      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input class="form-input" type="email" id="email" placeholder="you@example.com" required autocomplete="email">
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <input class="form-input" type="password" id="password" placeholder="Min. 8 characters" required minlength="8" autocomplete="new-password">
        <div id="pw-strength" style="height:4px;border-radius:4px;margin-top:6px;background:var(--border);overflow:hidden">
          <div id="pw-strength-bar" style="height:100%;width:0;border-radius:4px;transition:all .3s"></div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Confirm Password</label>
        <input class="form-input" type="password" id="confirm_password" placeholder="Repeat password" required autocomplete="new-password">
      </div>

      <button type="submit" class="btn btn-primary w-full" id="reg-btn" style="margin-top:8px">
        <span id="btn-text">Create Account</span>
        <span id="btn-spinner" class="spinner hidden"></span>
      </button>
    </form>

    <p style="text-align:center;margin-top:24px;font-size:.875rem;color:var(--text-secondary)">
      Already have an account? <a href="index.php">Sign in →</a>
    </p>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';

// Password strength indicator
document.getElementById('password').addEventListener('input', function() {
  const val = this.value;
  const bar = document.getElementById('pw-strength-bar');
  let score = 0;
  if (val.length >= 8) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const colors = ['','#ef4444','#f59e0b','#22c55e','#4f8ef7'];
  const widths = ['0%','25%','50%','75%','100%'];
  bar.style.width  = widths[score];
  bar.style.background = colors[score];
});

// Username availability
let unameTimer;
document.getElementById('username').addEventListener('input', function() {
  clearTimeout(unameTimer);
  const status = document.getElementById('username-status');
  if (!this.value) { status.textContent = ''; return; }
  status.textContent = '⏳';
  unameTimer = setTimeout(async () => {
    const res  = await fetch(`${BASE_URL}/api/users/search.php?q=${encodeURIComponent(this.value)}`);
    // simplified check
    status.textContent = /^[a-zA-Z0-9_]+$/.test(this.value) ? '✅' : '❌';
  }, 500);
});

document.getElementById('register-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn   = document.getElementById('reg-btn');
  const txt   = document.getElementById('btn-text');
  const spin  = document.getElementById('btn-spinner');
  const alert = document.getElementById('alert-box');

  const pw  = document.getElementById('password').value;
  const cpw = document.getElementById('confirm_password').value;
  if (pw !== cpw) {
    alert.innerHTML = `<div class="alert alert-error">⚠️ Passwords do not match.</div>`;
    return;
  }

  btn.disabled = true; txt.classList.add('hidden'); spin.classList.remove('hidden');
  alert.innerHTML = '';

  const data = {
    full_name: document.getElementById('full_name').value,
    username:  document.getElementById('username').value,
    email:     document.getElementById('email').value,
    password:  pw,
    confirm_password: cpw
  };

  try {
    const res  = await fetch(`${BASE_URL}/api/auth/register.php`, {
      method:'POST', body: new URLSearchParams(data)
    });
    const json = await res.json();
    if (json.success) {
      window.location.href = json.redirect;
    } else {
      alert.innerHTML = `<div class="alert alert-error">⚠️ ${json.error}</div>`;
      btn.disabled = false; txt.classList.remove('hidden'); spin.classList.add('hidden');
    }
  } catch(err) {
    alert.innerHTML = `<div class="alert alert-error">⚠️ Connection error. Please try again.</div>`;
    btn.disabled = false; txt.classList.remove('hidden'); spin.classList.add('hidden');
  }
});
</script>
</body>
</html>
