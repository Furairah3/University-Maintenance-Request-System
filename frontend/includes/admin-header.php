<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$unreadCount = Auth::getUnreadNotificationCount();
$userInitial = strtoupper(substr(Auth::getUserName() ?? 'A', 0, 1));
$profileImage = Auth::getProfileImage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin' ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/frontend/css/style.css">
</head>
<body>
<div class="app-layout">
    <aside class="sidebar admin-sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="logo">AU</div>
            <div><h2>Smart Hostel</h2><small>Admin · Ashesi</small></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Overview</div>
                <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>"><span class="icon">📊</span> Dashboard</a>
                <a href="requests.php" class="nav-link <?= $currentPage === 'requests' ? 'active' : '' ?>"><span class="icon">📋</span> All Requests</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Management</div>
                <a href="staff.php" class="nav-link <?= $currentPage === 'staff' ? 'active' : '' ?>"><span class="icon">👥</span> Staff Management</a>
                <a href="categories.php" class="nav-link <?= $currentPage === 'categories' ? 'active' : '' ?>"><span class="icon">🏷</span> Categories</a>
                <a href="metrics.php" class="nav-link <?= $currentPage === 'metrics' ? 'active' : '' ?>"><span class="icon">📈</span> Metrics</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Account</div>
                <a href="settings.php" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>"><span class="icon">⚙️</span> Settings</a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <?php if ($profileImage): ?>
                    <img src="<?= APP_URL . '/' . htmlspecialchars($profileImage) ?>" alt="Profile" class="user-avatar" style="object-fit:cover;">
                <?php else: ?>
                    <div class="user-avatar"><?= $userInitial ?></div>
                <?php endif; ?>
                <div class="user-details">
                    <div class="name"><?= htmlspecialchars(Auth::getUserName()) ?></div>
                    <div class="role">Administrator</div>
                </div>
            </div>
        </div>
    </aside>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
                <h1><?= $pageTitle ?? 'Dashboard' ?></h1>
            </div>
            <div class="topbar-right">
                <a href="<?= APP_URL ?>/logout.php" class="btn btn-secondary btn-sm">Logout</a>
            </div>
        </header>
        <main class="page-content fade-in">
