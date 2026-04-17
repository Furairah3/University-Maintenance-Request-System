<?php
/**
 * Login Page
 * Smart Hostel Maintenance System
 */

require_once __DIR__ . '/backend/includes/Auth.php';
require_once __DIR__ . '/backend/includes/helpers.php';

Auth::initSession();

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    $redirect = match(Auth::getRole()) {
        'admin' => 'admin/dashboard.php',
        'staff' => 'staff/dashboard.php',
        default => 'student/dashboard.php',
    };
    header("Location: $redirect");
    exit;
}

$error = '';
$timeout = isset($_GET['timeout']);

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $auth = new Auth();
        $result = $auth->login($email, $password);

        if ($result['success']) {
            header("Location: " . $result['redirect']);
            exit;
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
    <title>Login - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
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
                <h1><?= APP_NAME ?></h1>
                <p>Hostel Maintenance Portal</p>
            </div>

            <?php if ($timeout): ?>
                <div class="alert alert-warning">
                    Your session has expired. Please log in again.
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="on">
                <div class="form-group">
                    <label class="form-label" for="email">Email address</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="you@ashesi.edu.gh"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Enter your password"
                           required minlength="8">
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width:100%">
                    Sign in
                </button>
            </form>

            <div class="auth-footer">
                Don't have an account? <a href="register.php">Create one</a>
            </div>
        </div>

        <p style="text-align:center;margin-top:16px;font-size:12px;color:rgba(255,255,255,0.4)">
            &copy; 2026 Group 11 &mdash; CS415 Software Engineering, Ashesi University
        </p>
    </div>
</div>
<script src="frontend/js/bg-animation.js"></script>
</body>
</html>
