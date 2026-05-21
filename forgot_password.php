<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/chat.php');
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title>InterLink — Forgot Password</title>
  <meta name="description" content="Reset your InterLink password with a one-time code sent to your email.">
  <link rel="icon" href="assets/images/favicon.png" type="image/png">
  <link rel="stylesheet" href="assets/css/main.css">
  <style>
    /* ── Step wizard ── */
    .steps-track {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0;
      margin-bottom: 32px;
    }
    .step-dot {
      width: 34px; height: 34px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .8rem; font-weight: 700;
      border: 2px solid rgba(255,255,255,.15);
      color: var(--text-muted);
      background: rgba(255,255,255,.04);
      transition: all .35s;
      position: relative; z-index: 1;
      flex-shrink: 0;
    }
    .step-dot.active {
      background: linear-gradient(135deg,#4f8ef7,#6366f1);
      border-color: transparent;
      color: #fff;
      box-shadow: 0 0 18px rgba(79,142,247,.45);
    }
    .step-dot.done {
      background: rgba(34,197,94,.18);
      border-color: rgba(34,197,94,.5);
      color: #4ade80;
    }
    .step-line {
      flex: 1; height: 2px;
      background: rgba(255,255,255,.1);
      max-width: 64px;
      transition: background .35s;
    }
    .step-line.done { background: rgba(34,197,94,.4); }

    /* ── OTP boxes ── */
    .otp-grid {
      display: flex; gap: 10px; justify-content: center; margin: 8px 0 4px;
    }
    .otp-box {
      width: 50px; height: 58px;
      background: rgba(255,255,255,.06);
      border: 1.5px solid rgba(255,255,255,.14);
      border-radius: 12px;
      font-size: 1.6rem; font-weight: 800;
      text-align: center; color: #fff;
      caret-color: #4f8ef7;
      transition: border-color .2s, box-shadow .2s;
      outline: none;
    }
    .otp-box:focus {
      border-color: #4f8ef7;
      box-shadow: 0 0 0 3px rgba(79,142,247,.25);
    }
    .otp-box.filled { border-color: rgba(79,142,247,.6); }

    /* ── pw strength bar ── */
    #pw-bar-wrap { height: 4px; border-radius: 4px; background: rgba(255,255,255,.08); overflow: hidden; margin-top: 6px; }
    #pw-bar      { height: 100%; width: 0; border-radius: 4px; transition: all .3s; }

    /* ── success card ── */
    .success-card {
      text-align: center; padding: 12px 0 4px;
    }
    .success-icon {
      font-size: 3rem; margin-bottom: 12px;
      animation: popIn .5s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes popIn {
      from { transform: scale(0); opacity: 0; }
      to   { transform: scale(1); opacity: 1; }
    }

    /* ── countdown ── */
    #countdown {
      display: inline-block;
      background: rgba(79,142,247,.1);
      border: 1px solid rgba(79,142,247,.25);
      border-radius: 20px;
      padding: 4px 14px;
      font-size: .78rem;
      color: #93c5fd;
      margin-top: 8px;
    }
    #countdown.expiring { color: #fbbf24; border-color: rgba(251,191,36,.35); background: rgba(251,191,36,.08); }

    .resend-btn {
      background: none; border: none; color: #4f8ef7;
      cursor: pointer; font-size: .85rem; text-decoration: underline;
      padding: 0; margin-top: 4px;
    }
    .resend-btn:disabled { color: var(--text-muted); cursor: not-allowed; text-decoration: none; }

    /* dev OTP notice */
    .dev-notice {
      background: rgba(139,92,246,.1); border: 1px dashed rgba(139,92,246,.4);
      border-radius: 10px; padding: 10px 14px; font-size: .8rem; color: #c4b5fd;
      margin-top: 10px; text-align: center;
    }
  </style>
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-card glass-card fade-in-up" style="max-width: 430px;">

    <!-- Logo -->
    <div class="auth-logo">
      <div class="auth-logo-icon">💬</div>
      <div class="auth-logo-text">Inter<span>Link</span></div>
    </div>

    <!-- Step tracker -->
    <div class="steps-track" id="steps-track">
      <div class="step-dot active" id="dot-1">1</div>
      <div class="step-line" id="line-1"></div>
      <div class="step-dot" id="dot-2">2</div>
      <div class="step-line" id="line-2"></div>
      <div class="step-dot" id="dot-3">3</div>
    </div>

    <!-- Alert box -->
    <div id="alert-box"></div>

    <!-- ══════════════════════════════════════════════
         STEP 1 — Enter email
    ══════════════════════════════════════════════ -->
    <div id="step-1">
      <h1 style="font-size:1.5rem;margin-bottom:4px">Forgot Password?</h1>
      <p class="text-muted" style="font-size:.88rem;margin-bottom:24px">Enter your email and we'll send you a 6-digit OTP.</p>

      <form id="form-email" class="flex-col gap-4">
        <div class="form-group">
          <label class="form-label" for="input-email">Email Address</label>
          <input class="form-input" type="email" id="input-email" placeholder="you@example.com" required autocomplete="email">
        </div>
        <button type="submit" class="btn btn-primary w-full" id="btn-send">
          <span id="btn-send-txt">Send OTP</span>
          <span id="btn-send-spin" class="spinner hidden"></span>
        </button>
      </form>
    </div>

    <!-- ══════════════════════════════════════════════
         STEP 2 — Enter OTP
    ══════════════════════════════════════════════ -->
    <div id="step-2" class="hidden">
      <h1 style="font-size:1.5rem;margin-bottom:4px">Enter OTP</h1>
      <p class="text-muted" id="otp-subtitle" style="font-size:.88rem;margin-bottom:6px">A 6-digit code was sent to <strong id="lbl-email" style="color:var(--text-primary)"></strong></p>
      <div id="countdown">⏱ 10:00 remaining</div>
      <div id="dev-notice-box"></div>

      <form id="form-otp" class="flex-col gap-4" style="margin-top:20px">
        <div class="otp-grid" id="otp-grid">
          <input class="otp-box" type="text" inputmode="numeric" maxlength="1" data-index="0" autocomplete="one-time-code">
          <input class="otp-box" type="text" inputmode="numeric" maxlength="1" data-index="1">
          <input class="otp-box" type="text" inputmode="numeric" maxlength="1" data-index="2">
          <input class="otp-box" type="text" inputmode="numeric" maxlength="1" data-index="3">
          <input class="otp-box" type="text" inputmode="numeric" maxlength="1" data-index="4">
          <input class="otp-box" type="text" inputmode="numeric" maxlength="1" data-index="5">
        </div>
        <button type="submit" class="btn btn-primary w-full" id="btn-verify">
          <span id="btn-verify-txt">Verify OTP</span>
          <span id="btn-verify-spin" class="spinner hidden"></span>
        </button>
      </form>

      <p style="text-align:center;font-size:.82rem;color:var(--text-muted);margin-top:16px">
        Didn't receive it?
        <button class="resend-btn" id="btn-resend" disabled>Resend OTP</button>
      </p>
    </div>

    <!-- ══════════════════════════════════════════════
         STEP 3 — New password
    ══════════════════════════════════════════════ -->
    <div id="step-3" class="hidden">
      <h1 style="font-size:1.5rem;margin-bottom:4px">New Password</h1>
      <p class="text-muted" style="font-size:.88rem;margin-bottom:24px">OTP verified ✅ — choose a strong new password.</p>

      <form id="form-reset" class="flex-col gap-4">
        <div class="form-group">
          <label class="form-label" for="pw-new">New Password</label>
          <div class="relative">
            <input class="form-input" type="password" id="pw-new" placeholder="Min. 8 characters" required minlength="8" style="padding-right:44px" autocomplete="new-password">
            <button type="button" id="toggle-pw1" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);font-size:1rem;cursor:pointer">👁️</button>
          </div>
          <div id="pw-bar-wrap"><div id="pw-bar"></div></div>
        </div>

        <div class="form-group">
          <label class="form-label" for="pw-confirm">Confirm Password</label>
          <div class="relative">
            <input class="form-input" type="password" id="pw-confirm" placeholder="Repeat password" required autocomplete="new-password" style="padding-right:44px">
            <button type="button" id="toggle-pw2" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);font-size:1rem;cursor:pointer">👁️</button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-full" id="btn-reset">
          <span id="btn-reset-txt">Reset Password</span>
          <span id="btn-reset-spin" class="spinner hidden"></span>
        </button>
      </form>
    </div>

    <!-- ══════════════════════════════════════════════
         STEP 4 — Success
    ══════════════════════════════════════════════ -->
    <div id="step-done" class="hidden success-card">
      <div class="success-icon">🎉</div>
      <h1 style="font-size:1.4rem;margin-bottom:8px">Password Reset!</h1>
      <p class="text-muted" style="font-size:.88rem;margin-bottom:24px">Your password has been updated successfully.</p>
      <a href="index.php" class="btn btn-primary w-full" style="text-align:center;display:block;">Sign In →</a>
    </div>

    <p style="text-align:center;margin-top:24px;font-size:.875rem;color:var(--text-secondary)">
      Remember it? <a href="index.php">Sign in →</a>
    </p>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';

// ── Helpers ──────────────────────────────────────────────
function showAlert(msg, type = 'error') {
  document.getElementById('alert-box').innerHTML =
    `<div class="alert alert-${type}" style="margin-bottom:16px">
       ${type === 'error' ? '⚠️' : '✅'} ${msg}
     </div>`;
}
function clearAlert() { document.getElementById('alert-box').innerHTML = ''; }
function setLoading(btnId, spinId, txtId, on) {
  const b = document.getElementById(btnId);
  b.disabled = on;
  document.getElementById(spinId).classList.toggle('hidden', !on);
  document.getElementById(txtId).classList.toggle('hidden', on);
}

// ── Step navigation ───────────────────────────────────────
let currentStep = 1;
function goStep(n) {
  [1, 2, 3].forEach(i => {
    document.getElementById('step-' + i)?.classList.toggle('hidden', i !== n);
    if (i === 4) return;
    const dot  = document.getElementById('dot-' + i);
    const line = document.getElementById('line-' + i);
    if (i < n)  { dot.classList.add('done');   dot.classList.remove('active'); dot.textContent = '✓'; }
    if (i === n){ dot.classList.add('active'); dot.classList.remove('done'); }
    if (i > n)  { dot.classList.remove('done','active'); dot.textContent = i; }
    if (line) line.classList.toggle('done', i < n);
  });
  if (n === 4) {
    ['step-1','step-2','step-3'].forEach(id => document.getElementById(id)?.classList.add('hidden'));
    document.getElementById('step-done').classList.remove('hidden');
    ['dot-1','dot-2','dot-3'].forEach(id => {
      const d = document.getElementById(id);
      d.classList.add('done'); d.classList.remove('active'); d.textContent = '✓';
    });
    ['line-1','line-2'].forEach(id => document.getElementById(id)?.classList.add('done'));
  }
  currentStep = n;
  clearAlert();
}

// ── Stored email + reset token ───────────────────────────
let savedEmail = '';
let savedResetToken = '';  // returned by verify_otp.php, sent to reset_password_otp.php


// ── STEP 1: Send OTP ──────────────────────────────────────
document.getElementById('form-email').addEventListener('submit', async e => {
  e.preventDefault();
  clearAlert();
  const email = document.getElementById('input-email').value.trim();
  setLoading('btn-send', 'btn-send-spin', 'btn-send-txt', true);

  try {
    const res  = await fetch(`${BASE_URL}/api/auth/send_otp.php`, {
      method: 'POST', body: new URLSearchParams({ email })
    });
    const json = await res.json();

    if (json.success) {
      savedEmail = email;
      document.getElementById('lbl-email').textContent = email;
      startCountdown(10 * 60);
      goStep(2);
      setTimeout(() => document.querySelector('.otp-box').focus(), 100);

      // Dev mode: SMTP not configured — OTP shown on screen
      if (json.dev_otp) {
        // Update subtitle
        document.getElementById('otp-subtitle').innerHTML =
          `📋 Email delivery skipped — <strong style="color:#fbbf24">use the code shown below</strong>`;
        // Show notice with click-to-fill
        document.getElementById('dev-notice-box').innerHTML =
          `<div class="dev-notice" style="cursor:pointer" id="dev-fill-btn" title="Click to auto-fill">
             🛠️ <strong>SMTP not configured</strong> — OTP displayed here for testing.<br>
             <span style="display:inline-block;margin-top:8px;font-size:1.8rem;font-weight:900;letter-spacing:.3em;color:#c4b5fd;font-family:monospace">${json.dev_otp}</span><br>
             <span style="font-size:.75rem;opacity:.7;margin-top:4px;display:block">👆 Tap to auto-fill</span>
           </div>`;
        // Click to fill the OTP boxes automatically
        document.getElementById('dev-fill-btn').addEventListener('click', () => {
          const digits = String(json.dev_otp).split('');
          otpBoxes.forEach((b, i) => { b.value = digits[i] || ''; b.classList.toggle('filled', !!b.value); });
          document.getElementById('btn-verify').focus();
        });
      }
    } else {
      showAlert(json.error || 'Failed to send OTP.');
    }
  } catch {
    showAlert('Connection error. Please try again.');
  } finally {
    setLoading('btn-send', 'btn-send-spin', 'btn-send-txt', false);
  }
});

// ── STEP 2: OTP boxes logic ───────────────────────────────
const otpBoxes = document.querySelectorAll('.otp-box');
otpBoxes.forEach((box, i) => {
  box.addEventListener('input', e => {
    const val = e.target.value.replace(/\D/g,'');
    box.value = val.slice(-1);
    box.classList.toggle('filled', !!box.value);
    if (box.value && i < 5) otpBoxes[i + 1].focus();
  });
  box.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !box.value && i > 0) {
      otpBoxes[i - 1].focus();
      otpBoxes[i - 1].value = '';
      otpBoxes[i - 1].classList.remove('filled');
    }
    if (e.key === 'ArrowLeft' && i > 0) otpBoxes[i-1].focus();
    if (e.key === 'ArrowRight' && i < 5) otpBoxes[i+1].focus();
  });
  // Handle paste on any box
  box.addEventListener('paste', e => {
    e.preventDefault();
    const digits = (e.clipboardData.getData('text')).replace(/\D/g,'').slice(0,6);
    digits.split('').forEach((d, j) => {
      if (otpBoxes[j]) { otpBoxes[j].value = d; otpBoxes[j].classList.add('filled'); }
    });
    if (digits.length < 6 && otpBoxes[digits.length]) otpBoxes[digits.length].focus();
    else if (digits.length === 6) document.getElementById('btn-verify').focus();
  });
});

function getOtp() { return Array.from(otpBoxes).map(b => b.value).join(''); }

document.getElementById('form-otp').addEventListener('submit', async e => {
  e.preventDefault();
  clearAlert();
  const otp = getOtp();
  if (otp.length < 6) { showAlert('Please enter all 6 digits.'); return; }
  setLoading('btn-verify', 'btn-verify-spin', 'btn-verify-txt', true);

  try {
    const res  = await fetch(`${BASE_URL}/api/auth/verify_otp.php`, {
      method: 'POST', body: new URLSearchParams({ email: savedEmail, otp })
    });
    const json = await res.json();

    if (json.success) {
      savedResetToken = json.reset_token || '';  // store the DB-issued token
      stopCountdown();
      goStep(3);
      setTimeout(() => document.getElementById('pw-new').focus(), 100);
    } else {
      showAlert(json.error || 'Invalid OTP.');
      // Shake all boxes
      otpBoxes.forEach(b => { b.style.borderColor = '#ef4444'; b.value = ''; b.classList.remove('filled'); });
      setTimeout(() => otpBoxes.forEach(b => { b.style.borderColor = ''; }), 1200);
      otpBoxes[0].focus();
    }
  } catch {
    showAlert('Connection error. Please try again.');
  } finally {
    setLoading('btn-verify', 'btn-verify-spin', 'btn-verify-txt', false);
  }
});

// ── Countdown timer ───────────────────────────────────────
let countdownInterval;
function startCountdown(seconds) {
  let rem = seconds;
  const el = document.getElementById('countdown');
  const resendBtn = document.getElementById('btn-resend');
  resendBtn.disabled = true;

  function tick() {
    const m = String(Math.floor(rem / 60)).padStart(2, '0');
    const s = String(rem % 60).padStart(2, '0');
    el.textContent = `⏱ ${m}:${s} remaining`;
    el.classList.toggle('expiring', rem <= 60);
    if (rem <= 0) {
      clearInterval(countdownInterval);
      el.textContent = '⏱ OTP expired — request a new one';
      el.classList.add('expiring');
      resendBtn.disabled = false;
    }
    rem--;
  }
  tick();
  countdownInterval = setInterval(tick, 1000);

  // Enable resend after 30s
  setTimeout(() => { if (rem > 0) resendBtn.disabled = false; }, 30000);
}
function stopCountdown() { clearInterval(countdownInterval); }

// Resend OTP
document.getElementById('btn-resend').addEventListener('click', async () => {
  clearAlert();
  document.getElementById('btn-resend').disabled = true;
  document.getElementById('dev-notice-box').innerHTML = '';
  otpBoxes.forEach(b => { b.value = ''; b.classList.remove('filled'); });

  try {
    const res  = await fetch(`${BASE_URL}/api/auth/send_otp.php`, {
      method: 'POST', body: new URLSearchParams({ email: savedEmail })
    });
    const json = await res.json();
    if (json.success) {
      showAlert('New OTP sent!', 'success');
      startCountdown(10 * 60);
      if (json.dev_otp) {
        document.getElementById('dev-notice-box').innerHTML =
          `<div class="dev-notice" style="cursor:pointer" id="dev-fill-btn2" title="Click to auto-fill">
             🛠️ New OTP: <strong style="letter-spacing:.3em;font-size:1.4rem;font-family:monospace">${json.dev_otp}</strong><br>
             <span style="font-size:.75rem;opacity:.7">👆 Tap to auto-fill</span>
           </div>`;
        document.getElementById('dev-fill-btn2').addEventListener('click', () => {
          const digits = String(json.dev_otp).split('');
          otpBoxes.forEach((b, i) => { b.value = digits[i] || ''; b.classList.toggle('filled', !!b.value); });
          document.getElementById('btn-verify').focus();
        });
      }
    } else {
      showAlert(json.error || 'Could not resend OTP.');
    }
  } catch {
    showAlert('Connection error.');
  }
});

// ── STEP 3: Password strength + reset ─────────────────────
document.getElementById('pw-new').addEventListener('input', function() {
  const v = this.value, bar = document.getElementById('pw-bar');
  let score = 0;
  if (v.length >= 8) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  const colors = ['','#ef4444','#f59e0b','#22c55e','#4f8ef7'];
  const widths  = ['0%','25%','50%','75%','100%'];
  bar.style.width = widths[score]; bar.style.background = colors[score];
});

['toggle-pw1','toggle-pw2'].forEach((id, i) => {
  document.getElementById(id).addEventListener('click', function() {
    const pw = document.getElementById(i === 0 ? 'pw-new' : 'pw-confirm');
    pw.type = pw.type === 'password' ? 'text' : 'password';
    this.textContent = pw.type === 'password' ? '👁️' : '🙈';
  });
});

document.getElementById('form-reset').addEventListener('submit', async e => {
  e.preventDefault();
  clearAlert();
  const pw  = document.getElementById('pw-new').value;
  const cpw = document.getElementById('pw-confirm').value;
  if (pw !== cpw) { showAlert('Passwords do not match.'); return; }
  if (pw.length < 8) { showAlert('Password must be at least 8 characters.'); return; }

  setLoading('btn-reset', 'btn-reset-spin', 'btn-reset-txt', true);

  try {
    const res  = await fetch(`${BASE_URL}/api/auth/reset_password_otp.php`, {
      method: 'POST', body: new URLSearchParams({ reset_token: savedResetToken, password: pw, confirm: cpw })
    });
    const json = await res.json();

    if (json.success) {
      goStep(4);
    } else {
      showAlert(json.error || 'Failed to reset password.');
    }
  } catch {
    showAlert('Connection error. Please try again.');
  } finally {
    setLoading('btn-reset', 'btn-reset-spin', 'btn-reset-txt', false);
  }
});
</script>
</body>
</html>
