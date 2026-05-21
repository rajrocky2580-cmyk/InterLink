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
  <title>InterLink — Sign In</title>
  <meta name="description" content="Sign in to InterLink, your private real-time messaging platform.">
  <link rel="icon" href="assets/images/favicon.png" type="image/png">
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card glass-card fade-in-up">

    <div class="auth-logo">
      <div class="auth-logo-icon">💬</div>
      <div class="auth-logo-text">Inter<span>Link</span></div>
    </div>

    <h1 style="font-size:1.6rem;margin-bottom:4px">Welcome back</h1>
    <p class="text-muted" style="margin-bottom:28px;font-size:.9rem">Sign in to your account to continue</p>

    <div id="alert-box"><?php if (!empty($_GET['login_error'])): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($_GET['login_error']) ?></div><?php endif; ?></div>

    <form id="login-form" class="flex-col gap-4" action="<?= BASE_URL ?>/login_action.php" method="POST">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">

      <div class="form-group">
        <label class="form-label" for="email">Email or Username</label>
        <input class="form-input" type="text" id="email" name="email" placeholder="you@example.com or @username" required autocomplete="username">
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="relative">
          <input class="form-input" type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password" style="padding-right:44px">
          <button type="button" id="toggle-pw" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);font-size:1rem;cursor:pointer">👁️</button>
        </div>
        <div style="text-align:right;margin-top:6px">
          <a href="forgot_password.php" style="font-size:.8rem;color:var(--text-muted)">Forgot password?</a>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-full" id="login-btn" style="margin-top:4px">
        <span id="btn-text">Sign In</span>
        <span id="btn-spinner" class="spinner hidden"></span>
      </button>
    </form>

    <p style="text-align:center;margin-top:24px;font-size:.875rem;color:var(--text-secondary)">
      Don't have an account? <a href="register.php">Create one →</a>
    </p>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';

document.getElementById('toggle-pw').addEventListener('click', function() {
  const pw = document.getElementById('password');
  pw.type = pw.type === 'password' ? 'text' : 'password';
  this.textContent = pw.type === 'password' ? '👁️' : '🙈';
});

// Show spinner on submit (pure UX — form still submits normally to login_action.php)
document.getElementById('login-form').addEventListener('submit', function() {
  const btn  = document.getElementById('login-btn');
  const txt  = document.getElementById('btn-text');
  const spin = document.getElementById('btn-spinner');
  btn.disabled = true;
  txt.classList.add('hidden');
  spin.classList.remove('hidden');
});
</script>
</body>
</html>
