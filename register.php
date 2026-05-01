<?php
require_once 'includes/auth.php';
require_once 'includes/schema.php';
require_once 'includes/verification.php';
ensureExtendedSchema();
if (isLoggedIn()) redirect(SITE_URL . '/' . $_SESSION['role'] . '/dashboard.php');

$errors = [];
$values = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $room     = trim($_POST['room_number'] ?? '');

    $values = compact('name','email','room');

    if (!$name)  $errors['name']  = 'Full name is required.';
    if (!$email) $errors['email'] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    // Strong password: ≥8 chars, mixed case, at least one digit, at least one special character
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors['password'] = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/\d/', $password)) {
        $errors['password'] = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors['password'] = 'Password must contain at least one special character (e.g. @, #, !, &).';
    }

    if ($password !== $confirm) $errors['confirm_password'] = 'Passwords do not match.';

    if (empty($errors)) {
        $db   = getDB();
        $chk  = $db->prepare("SELECT id, is_verified FROM users WHERE email = ?");
        $chk->execute([$email]);
        $existing = $chk->fetch();

        if ($existing && (int)$existing['is_verified'] === 1) {
            // A real verified account already uses this email
            $errors['email'] = 'An account with this email already exists. Please sign in instead.';
        } elseif ($existing && (int)$existing['is_verified'] === 0) {
            // Stuck unverified record from a previous attempt where SMTP failed —
            // refresh its details with the new submission and resend the OTP
            $hash = hashPassword($password);
            $db->prepare("UPDATE users SET name=?, password=?, room_number=? WHERE id=?")
               ->execute([$name, $hash, $room, $existing['id']]);
            $newUserId = (int)$existing['id'];
        } else {
            $hash = hashPassword($password);
            $ins  = $db->prepare("INSERT INTO users (name, email, password, role, room_number, is_verified) VALUES (?,?,?,'student',?,0)");
            $ins->execute([$name, $email, $hash, $room]);
            $newUserId = (int)$db->lastInsertId();
        }

        if (empty($errors)) {

            // Generate + send OTP, stash pending user id so verify.php knows who
            $otp = createAndSendOTP($newUserId);
            $_SESSION['pending_verify_user_id'] = $newUserId;

            if ($otp['sent']) {
                setFlash('info', "We sent a 6-digit code to {$email}. Check your inbox — and your spam folder just in case.");
            } else {
                // Email didn't actually go through. In DEV_MODE show the code so testing works.
                $msg = "We couldn't reach your email right now. ";
                if (DEV_MODE) {
                    $msg .= "(Dev fallback: your code is {$otp['code']}. ";
                    if (!empty($otp['error'])) $msg .= "SMTP error: {$otp['error']}. ";
                    $msg .= "Admins can run /admin/test_email.php to diagnose.)";
                } else {
                    $msg .= "Please try “Resend code” or contact support.";
                }
                setFlash('info', $msg);
            }
            redirect(SITE_URL . '/verify.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register — HostelIQ</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-left">
    <a href="index.php" class="auth-logo">Hostel<span>IQ</span></a>
    <p class="auth-tagline">Create your student account</p>
    <div class="auth-feature">
      <div class="auth-feature-icon">📝</div>
      <div>
        <div class="auth-feature-title">Submit Requests</div>
        <div class="auth-feature-text">Report issues with a simple form — takes less than a minute</div>
      </div>
    </div>
    <div class="auth-feature">
      <div class="auth-feature-icon">🔍</div>
      <div>
        <div class="auth-feature-title">Track Progress</div>
        <div class="auth-feature-text">See the full history of every request you've submitted</div>
      </div>
    </div>
    <div style="margin-top:auto;padding-top:40px;font-size:11px;color:rgba(255,255,255,.3);">
      CS415 Group 11 · Ashesi University 2027
    </div>
  </div>

  <div class="auth-right">
    <div class="auth-form-box fade-in">
      <h1 class="auth-form-title">Create account</h1>
      <p class="auth-form-sub">Student registration — staff accounts are created by admin</p>

      <form method="POST" data-validate>
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input type="text" name="name" class="form-control <?=isset($errors['name'])?'is-invalid':''?>" placeholder="Your full name" value="<?=sanitize($values['name']??'')?>" required>
          <?php if (isset($errors['name'])): ?><p class="form-error">⚠ <?=sanitize($errors['name'])?></p><?php endif; ?>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input type="email" name="email"
              class="form-control <?=isset($errors['email'])?'is-invalid':''?>"
              placeholder="you@example.com"
              value="<?=sanitize($values['email']??'')?>" required>
            <p class="form-hint">A 6-digit code will be sent here to verify your email.</p>
            <?php if (isset($errors['email'])): ?><p class="form-error">⚠ <?=sanitize($errors['email'])?></p><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Room Number</label>
            <input type="text" name="room_number" class="form-control" placeholder="e.g. B-214" value="<?=sanitize($values['room']??'')?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password *</label>
            <input type="password" name="password" id="passwordInput"
              class="form-control <?=isset($errors['password'])?'is-invalid':''?>"
              placeholder="At least 8 characters" required minlength="8">
            <p class="form-hint">Must include: uppercase, lowercase, number, and a special character (e.g. <strong>Hostel@2026</strong>)</p>
            <div id="pwStrength" style="display:none;margin-top:6px;">
              <div class="progress-bar" style="height:5px;"><div id="pwBar" class="progress-fill" style="width:0%;background:var(--danger);transition:.2s;"></div></div>
              <div id="pwLabel" style="font-size:11px;margin-top:4px;color:var(--text-muted);"></div>
            </div>
            <?php if (isset($errors['password'])): ?><p class="form-error">⚠ <?=sanitize($errors['password'])?></p><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password *</label>
            <input type="password" name="confirm_password" class="form-control <?=isset($errors['confirm_password'])?'is-invalid':''?>" placeholder="Repeat password" required>
            <?php if (isset($errors['confirm_password'])): ?><p class="form-error">⚠ <?=sanitize($errors['confirm_password'])?></p><?php endif; ?>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:4px;">Create Account</button>
      </form>

      <p style="text-align:center;margin-top:18px;font-size:13px;color:var(--text-muted);">
        Already have an account? <a href="login.php">Sign in</a>
      </p>
    </div>
  </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
