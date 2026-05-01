<?php
require_once 'includes/auth.php';
require_once 'includes/email.php';
require_once 'includes/schema.php';
ensureExtendedSchema();

if (isLoggedIn()) redirect(SITE_URL . '/' . $_SESSION['role'] . '/dashboard.php');

$success = '';
$error   = '';
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, name, email FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always show the same generic success message, even if email doesn't exist —
        // prevents email-enumeration attacks. We just silently skip sending if not found.
        if ($user) {
            $token   = bin2hex(random_bytes(32));   // 64 hex chars
            $expires = date('Y-m-d H:i:s', time() + 30 * 60); // 30 minutes

            $db->prepare("
                INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP
            ")->execute([$user['id'], $token, $expires]);

            $resetUrl = SITE_URL . "/reset_password.php?token={$token}";
            $subject  = "[HostelIQ] Reset your password";
            $html     = "<!DOCTYPE html><html><body style='font-family:sans-serif;background:#f4f4f4;padding:24px;'>
                <div style='max-width:520px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;'>
                  <div style='background:#8B0000;padding:24px;text-align:center;'>
                    <h2 style='color:#fff;margin:0;'>HostelIQ — Reset Password</h2>
                  </div>
                  <div style='padding:28px;'>
                    <p>Hi <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
                    <p>We got a request to reset your HostelIQ password. Click the button below to set a new one:</p>
                    <p style='text-align:center;margin:24px 0;'>
                      <a href='{$resetUrl}' style='display:inline-block;background:#8B0000;color:#fff;padding:14px 32px;border-radius:6px;text-decoration:none;font-weight:600;'>Reset My Password</a>
                    </p>
                    <p style='color:#555;font-size:13px;'>Or paste this link into your browser:<br>
                      <span style='word-break:break-all;color:#8B0000;'>{$resetUrl}</span>
                    </p>
                    <p style='color:#888;font-size:12px;'>This link expires in <strong>30 minutes</strong>. If you didn't ask for a reset, you can safely ignore this email — your password won't change.</p>
                    <p style='color:#999;font-size:11px;margin-top:24px;'>Ashesi University — Smart Hostel Management System</p>
                  </div>
                </div></body></html>";

            sendEmail($user['email'], $user['name'], $subject, $html);
        }

        $success = "If an account with {$email} exists, we've sent a password reset link. Check your inbox (and spam folder).";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot Password — HostelIQ</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-left">
    <a href="index.php" class="auth-logo">Hostel<span>IQ</span></a>
    <p class="auth-tagline">Reset your password</p>
    <div class="auth-feature">
      <div class="auth-feature-icon">📧</div>
      <div>
        <div class="auth-feature-title">Quick & secure</div>
        <div class="auth-feature-text">We'll email you a one-time link to set a new password.</div>
      </div>
    </div>
    <div class="auth-feature">
      <div class="auth-feature-icon">⏱</div>
      <div>
        <div class="auth-feature-title">Link expires in 30 min</div>
        <div class="auth-feature-text">For your safety, the reset link is short-lived and single-use.</div>
      </div>
    </div>
    <div style="margin-top:auto;padding-top:40px;font-size:11px;color:rgba(255,255,255,.3);">
      CS415 Group 11 · Ashesi University
    </div>
  </div>

  <div class="auth-right">
    <div class="auth-form-box fade-in">
      <h1 class="auth-form-title">Forgot password?</h1>
      <p class="auth-form-sub">Enter the email on your account and we'll send you a reset link.</p>

      <?php if ($success): ?>
        <div class="alert alert-success">✓ <?=sanitize($success)?></div>
        <p style="text-align:center;margin-top:18px;font-size:13px;">
          <a href="login.php">← Back to sign in</a>
        </p>
      <?php else: ?>
        <?php if ($error): ?><div class="alert alert-danger">⚠ <?=sanitize($error)?></div><?php endif; ?>

        <form method="POST">
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control"
              placeholder="you@example.com"
              value="<?=sanitize($email)?>" required autofocus>
          </div>
          <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">Send Reset Link</button>
        </form>

        <p style="text-align:center;margin-top:18px;font-size:13px;color:var(--text-muted);">
          Remembered it? <a href="login.php">Sign in</a> · Don't have an account? <a href="register.php">Register</a>
        </p>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
