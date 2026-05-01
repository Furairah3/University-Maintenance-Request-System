<?php
require_once 'includes/auth.php';
require_once 'includes/schema.php';
ensureExtendedSchema();

if (isLoggedIn()) redirect(SITE_URL . '/' . $_SESSION['role'] . '/dashboard.php');

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$valid = false;
$user  = null;

// Validate token
if ($token && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT pr.user_id, pr.expires_at, u.name, u.email
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token = ?
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if ($row && strtotime($row['expires_at']) >= time()) {
        $valid = true;
        $user  = $row;
    } else {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
} else {
    $error = 'Missing or malformed reset link. Please request a new one from the forgot password page.';
}

// Handle password submission
if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $new = $_POST['new_password'] ?? '';
    $cfm = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $new)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/\d/', $new)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $new)) {
        $error = 'Password must contain at least one special character.';
    } elseif ($new !== $cfm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        $db->prepare("UPDATE users SET password = ? WHERE id = ?")
           ->execute([hashPassword($new), $user['user_id']]);
        $db->prepare("DELETE FROM password_resets WHERE user_id = ?")
           ->execute([$user['user_id']]);

        setFlash('success', 'Password reset successfully. Please sign in with your new password.');
        redirect(SITE_URL . '/login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password — HostelIQ</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-left">
    <a href="index.php" class="auth-logo">Hostel<span>IQ</span></a>
    <p class="auth-tagline">Set a new password</p>
    <div class="auth-feature">
      <div class="auth-feature-icon">🔒</div>
      <div>
        <div class="auth-feature-title">Strong password required</div>
        <div class="auth-feature-text">8+ chars, with upper, lower, number and a special character.</div>
      </div>
    </div>
    <div style="margin-top:auto;padding-top:40px;font-size:11px;color:rgba(255,255,255,.3);">
      CS415 Group 11 · Ashesi University
    </div>
  </div>

  <div class="auth-right">
    <div class="auth-form-box fade-in">
      <h1 class="auth-form-title">Reset password</h1>

      <?php if (!$valid): ?>
        <div class="alert alert-danger">⚠ <?=sanitize($error)?></div>
        <p style="text-align:center;margin-top:18px;">
          <a href="forgot_password.php" class="btn btn-primary btn-block">Request a new reset link</a>
        </p>
        <p style="text-align:center;margin-top:14px;font-size:13px;">
          <a href="login.php">← Back to sign in</a>
        </p>
      <?php else: ?>
        <p class="auth-form-sub">Setting a new password for <strong><?=sanitize($user['email'])?></strong>.</p>

        <?php if ($error): ?><div class="alert alert-danger">⚠ <?=sanitize($error)?></div><?php endif; ?>

        <form method="POST">
          <input type="hidden" name="token" value="<?=sanitize($token)?>">

          <div class="form-group">
            <label class="form-label">New Password *</label>
            <input type="password" name="new_password" id="passwordInput"
              class="form-control" placeholder="At least 8 characters" required minlength="8" autofocus>
            <p class="form-hint">Must include: uppercase, lowercase, number, and a special character.</p>
            <div id="pwStrength" style="display:none;margin-top:6px;">
              <div class="progress-bar" style="height:5px;"><div id="pwBar" class="progress-fill" style="width:0%;background:var(--danger);transition:.2s;"></div></div>
              <div id="pwLabel" style="font-size:11px;margin-top:4px;color:var(--text-muted);"></div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Confirm New Password *</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
          </div>

          <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:4px;">Update Password</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
