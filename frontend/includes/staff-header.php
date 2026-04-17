<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$userInitial = strtoupper(substr(Auth::getUserName() ?? 'S', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Staff' ?> - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/frontend/css/style.css">
</head>
<body>
<div class="app-layout">
    <aside class="sidebar" id="sidebar" style="background:linear-gradient(180deg,#7A1825 0%,#4A0E1A 100%);">
        <div class="sidebar-brand">
            <div class="logo">AU</div>
            <div><h2>Smart Hostel</h2><small>Staff · Ashesi</small></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Tasks</div>
                <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>"><span class="icon">📋</span> My Tasks</a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= $userInitial ?></div>
                <div class="user-details">
                    <div class="name"><?= htmlspecialchars(Auth::getUserName()) ?></div>
                    <div class="role">Maintenance Staff</div>
                </div>
            </div>
        </div>
    </aside>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
                <h1><?= $pageTitle ?? 'My Tasks' ?></h1>
            </div>
            <div class="topbar-right">
                <a href="<?= APP_URL ?>/logout.php" class="btn btn-secondary btn-sm">Logout</a>
            </div>
        </header>
        <main class="page-content fade-in">
