<?php
require_once 'includes/auth.php';
require_once 'includes/verification.php';

// Must have a pending user in session (set by register.php), otherwise send them back
$userId = $_SESSION['pending_verify_user_id'] ?? null;
if (!$userId) {
    setFlash('error', 'No pending verification. Please register or sign in first.');
    redirect(SITE_URL . '/login.php');
}

$db = getDB();
$user = $db->prepare("SELECT id, name, email, is_verified FROM users WHERE id = ?");
$user->execute([$userId]);
$u = $user->fetch();

if (!$u) {
    unset($_SESSION['pending_verify_user_id']);
    redirect(SITE_URL . '/register.php');
}

if ($u['is_verified']) {
    unset($_SESSION['pending_verify_user_id']);
    setFlash('success', 'Your email is already verified. You can sign in now.');
    redirect(SITE_URL . '/login.php');
}

$error = '';
$info  = getFlash('info');

// Submit OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $code = trim($_POST['code'] ?? '');
    if (!preg_match('/^\d{6}$/', $code)) {
        $error = 'Please enter the 6-digit code you received by email.';
    } else {
        $res = verifyOTP($userId, $code);
        if (!empty($res['ok'])) {
            unset($_SESSION['pending_verify_user_id']);
            setFlash('success', 'Email verified! Please sign in to continue.');
            redirect(SITE_URL . '/login.php');
        }
        $error = match ($res['error']) {
            'expired'           => 'That code has expired. Tap “Resend code” to get a fresh one.',
            'too_many_attempts' => 'Too many incorrect attempts. Please resend a new code and try again.',
            'no_code'           => 'No code on file for this account. Please resend a new code.',
            default             => 'That code is incorrect. Check your email and try again.',
        };
    }
}

// Resend OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    $wait = resendCooldownSeconds($userId);
    if ($wait > 0) {
        $error = "Please wait {$wait} seconds before requesting another code.";
    } else {
        $otp = createAndSendOTP($userId);
        if ($otp['sent']) {
            $info = "A new code has been sent to {$u['email']}. Check your inbox.";
        } elseif (DEV_MODE) {
            $info = "Couldn't reach your email — dev fallback: your code is {$otp['code']}.";
        } else {
            $error = "We couldn't send the email: " . ($otp['error'] ?? 'unknown error');
        }
    }
}

$cooldown = resendCooldownSeconds($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verify your email — HostelIQ</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-left">
    <a href="index.php" class="auth-logo">Hostel<span>IQ</span></a>
    <p class="auth-tagline">One more step to activate your account</p>
    <div class="auth-feature">
      <div class="auth-feature-icon">📧</div>
      <div>
        <div class="auth-feature-title">We sent you a code</div>
        <div class="auth-feature-text">Check your Ashesi inbox — the 6-digit code expires in 10 minutes.</div>
      </div>
    </div>
    <div class="auth-feature">
      <div class="auth-feature-icon">🔒</div>
      <div>
        <div class="auth-feature-title">Why verify?</div>
        <div class="auth-feature-text">Confirms the email is really yours, so only you can access your requests.</div>
      </div>
    </div>
    <div style="margin-top:auto;padding-top:40px;font-size:11px;color:rgba(255,255,255,.3);">
      CS415 Group 11 · Ashesi University
    </div>
  </div>

  <div class="auth-right">
    <div class="auth-form-box fade-in">
      <h1 class="auth-form-title">Verify your email</h1>
      <p class="auth-form-sub">We sent a 6-digit code to <strong><?=sanitize($u['email'])?></strong>. Enter it below.</p>

      <?php if ($info):  ?><div class="alert alert-info">ℹ <?=sanitize($info)?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger">⚠ <?=sanitize($error)?></div><?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label class="form-label">Verification Code *</label>
          <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code"
            class="form-control" placeholder="______"
            style="font-size:22px;letter-spacing:12px;text-align:center;font-weight:700;"
            required autofocus>
          <p class="form-hint">Code expires <?=OTP_TTL_MINUTES?> minutes after it was sent. You have up to 5 attempts.</p>
        </div>

        <button type="submit" name="verify" value="1" class="btn btn-primary btn-block btn-lg">Verify & Continue</button>
      </form>

      <form method="POST" style="margin-top:14px;">
        <button type="submit" name="resend" value="1" class="btn btn-outline btn-block"
          <?=$cooldown > 0 ? 'disabled' : ''?>>
          <?=$cooldown > 0 ? "Resend code in {$cooldown}s" : 'Didn\'t get it? Resend code'?>
        </button>
      </form>

      <p style="text-align:center;margin-top:18px;font-size:13px;color:var(--text-muted);">
        Wrong email? <a href="register.php">Start over</a>
      </p>
    </div>
  </div>
</div>
<script src="assets/js/main.js"></script>
<?php if ($cooldown > 0): ?>
<script>
  // Live-tick the resend cooldown so the user sees it count down without refreshing
  (function () {
    let s = <?=$cooldown?>;
    const btn = document.querySelector('button[name="resend"]');
    const tick = () => {
      s -= 1;
      if (s <= 0) {
        btn.disabled = false;
        btn.textContent = "Didn't get it? Resend code";
        return;
      }
      btn.textContent = 'Resend code in ' + s + 's';
      setTimeout(tick, 1000);
    };
    setTimeout(tick, 1000);
  })();
</script>
<?php endif; ?>
</body>
</html>
