<?php
/**
 * Student Layout Header - Sidebar + Topbar
 * Include at the top of every student page after Auth::requireRole('student')
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$unreadCount = Auth::getUnreadNotificationCount();
$userInitial = strtoupper(substr(Auth::getUserName() ?? 'S', 0, 1));
$profileImage = Auth::getProfileImage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/frontend/css/style.css">
</head>
<body>
<div class="app-layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="logo">🏠</div>
            <div>
                <h2><?= APP_NAME ?></h2>
                <small>Student Portal</small>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <span class="icon">📊</span> Dashboard
                </a>
                <a href="new-request.php" class="nav-link <?= $currentPage === 'new-request' ? 'active' : '' ?>">
                    <span class="icon">➕</span> New Request
                </a>
                <a href="my-requests.php" class="nav-link <?= $currentPage === 'my-requests' ? 'active' : '' ?>">
                    <span class="icon">📋</span> My Requests
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Account</div>
                <a href="notifications.php" class="nav-link <?= $currentPage === 'notifications' ? 'active' : '' ?>">
                    <span class="icon">🔔</span> Notifications
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge-count"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                    <span class="icon">⚙️</span> Settings
                </a>
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
                    <div class="role">Student</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
                <h1><?= $pageTitle ?? 'Dashboard' ?></h1>
            </div>
            <div class="topbar-right">
                <div style="position:relative">
                    <button class="btn-icon" onclick="this.nextElementSibling.classList.toggle('show')">
                        🔔
                        <?php if ($unreadCount > 0): ?><span class="notification-dot"></span><?php endif; ?>
                    </button>
                    <div class="notifications-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <h4>Notifications</h4>
                            <a href="notifications.php" style="font-size:12px">View all</a>
                        </div>
                        <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px">
                            <?= $unreadCount > 0 ? "$unreadCount new notifications" : "No new notifications" ?>
                        </div>
                    </div>
                </div>
                <a href="<?= APP_URL ?>/logout.php" class="btn btn-secondary btn-sm">Logout</a>
            </div>
        </header>
        <main class="page-content fade-in">
