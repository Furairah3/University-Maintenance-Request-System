<?php
require_once 'includes/auth.php';
require_once 'includes/schema.php';
ensureExtendedSchema();
if (isLoggedIn()) redirect(SITE_URL . '/' . $_SESSION['role'] . '/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please fill in both fields.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && verifyPassword($password, $user['password'])) {
            // Block unverified accounts and route them to the verify page
            if (isset($user['is_verified']) && (int)$user['is_verified'] === 0) {
                $_SESSION['pending_verify_user_id'] = (int)$user['id'];
                setFlash('info', 'Please verify your email before signing in. We\'ve sent your code below.');
                redirect(SITE_URL . '/verify.php');
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            redirect(SITE_URL . '/' . $user['role'] . '/dashboard.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — HostelIQ</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-left">
    <a href="index.php" class="auth-logo">Hostel<span>IQ</span></a>
    <p class="auth-tagline">Ashesi University Smart Hostel Management</p>

    <div class="auth-feature">
      <div class="auth-feature-icon">🔧</div>
      <div>
        <div class="auth-feature-title">Report Maintenance Issues</div>
        <div class="auth-feature-text">Submit and track requests with ease</div>
      </div>
    </div>
    <div class="auth-feature">
      <div class="auth-feature-icon">📊</div>
      <div>
        <div class="auth-feature-title">Real-Time Status Updates</div>
        <div class="auth-feature-text">Know exactly where your request stands</div>
      </div>
    </div>
    <div class="auth-feature">
      <div class="auth-feature-icon">📧</div>
      <div>
        <div class="auth-feature-title">Email Notifications</div>
        <div class="auth-feature-text">Get notified at every stage automatically</div>
      </div>
    </div>

    <div style="margin-top:auto;padding-top:40px;font-size:11px;color:rgba(255,255,255,.3);">
      CS415 Group 11 · Ashesi University 2027
    </div>
  </div>

  <div class="auth-right">
    <div class="auth-form-box fade-in">
      <h1 class="auth-form-title">Welcome back</h1>
      <p class="auth-form-sub">Sign in with your Ashesi credentials</p>

      <?php if ($error): ?>
        <div class="alert alert-danger">⚠ <?=sanitize($error)?></div>
      <?php endif; ?>
      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-warning">⚠ You are not authorized to access that page.</div>
      <?php endif; ?>
      <?php if ($msg = getFlash('success')): ?>
        <div class="alert alert-success">✓ <?=sanitize($msg)?></div>
      <?php endif; ?>
      <?php if ($info = getFlash('info')): ?>
        <div class="alert alert-info">ℹ <?=sanitize($info)?></div>
      <?php endif; ?>

      <form method="POST" data-validate>
        <div class="form-group">
          <label class="form-label">University Email</label>
          <input type="email" name="email" class="form-control" placeholder="yourname@ashesi.edu.gh" value="<?=sanitize($_POST['email'] ?? '')?>" required autofocus>
        </div>
        <div class="form-group">
          <div style="display:flex;justify-content:space-between;align-items:baseline;">
            <label class="form-label" style="margin-bottom:6px;">Password</label>
            <a href="forgot_password.php" style="font-size:11px;color:var(--primary);">Forgot password?</a>
          </div>
          <input type="password" name="password" class="form-control" placeholder="Your password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px;">Sign In</button>
      </form>

      <p style="text-align:center;margin-top:20px;font-size:13px;color:var(--text-muted);">
        Don't have an account? <a href="register.php">Register here</a>
      </p>

      <div style="margin-top:28px;padding:14px;background:var(--bg);border-radius:8px;font-size:11px;color:var(--text-muted);">
        <strong>Demo accounts:</strong><br>
        Admin: admin@ashesi.edu.gh / password<br>
        Student: k.asante@ashesi.edu.gh / password<br>
        Staff: j.mensah@ashesi.edu.gh / password
      </div>
    </div>
  </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
