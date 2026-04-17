<?php
require_once __DIR__ . '/backend/includes/Auth.php';
require_once __DIR__ . '/backend/includes/helpers.php';
Auth::initSession();

if (Auth::isLoggedIn()) { header("Location: student/dashboard.php"); exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $auth = new Auth();
        $result = $auth->register($name, $email, $password);
        if ($result['success']) {
            redirect('login.php', $result['message'], 'success');
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="frontend/css/style.css">
</head>
<body>
<canvas id="bgCanvas" class="bg-canvas"></canvas>
<div class="auth-page">
    <div class="auth-container">
        <div class="auth-brand">Ashesi University</div>
        <div class="auth-card">
            <div class="auth-logo">
                <div class="logo-icon">AU</div>
                <h1>Create Account</h1>
                <p>Register with your Ashesi email</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="name">Full name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-control"
                           placeholder="Enter your full name"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                           required minlength="2" maxlength="100">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">University email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="you@ashesi.edu.gh"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required>
                    <p class="form-text">Must be a valid @ashesi.edu.gh email</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Create a strong password"
                           required minlength="8">
                    <p class="form-text">Min 8 characters, include uppercase letter and number</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                           placeholder="Repeat your password"
                           required minlength="8">
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width:100%">
                    Create Account
                </button>
            </form>

            <div class="auth-footer">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>
    </div>
</div>
<script src="frontend/js/bg-animation.js"></script>
</body>
</html>
