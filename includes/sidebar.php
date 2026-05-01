<?php
// includes/sidebar.php — role-aware sidebar
// Variables required: $activePage (string), $role (string)
require_once __DIR__ . '/profile.php';
$role = $_SESSION['role'] ?? 'student';
$name = $_SESSION['name'] ?? 'User';
$initials = implode('', array_map(fn($w) => $w[0], explode(' ', $name)));

// Fetch current user for avatar display
$sidebarUser = isset($_SESSION['user_id']) ? getUserById((int)$_SESSION['user_id']) : null;

$menus = [
  'student' => [
    ['dashboard',        '🏠', 'Dashboard',       SITE_URL.'/student/dashboard.php'],
    ['new_request',      '➕', 'New Request',      SITE_URL.'/student/new_request.php'],
    ['my_requests',      '📋', 'My Requests',      SITE_URL.'/student/my_requests.php'],
    ['notifications',    '🔔', 'Notifications',    SITE_URL.'/student/notifications.php'],
    ['settings',         '⚙️', 'Settings',         SITE_URL.'/student/settings.php'],
  ],
  'admin' => [
    ['dashboard',        '🏠', 'Dashboard',        SITE_URL.'/admin/dashboard.php'],
    ['requests',         '📋', 'All Requests',     SITE_URL.'/admin/requests.php'],
    ['staff',            '👷', 'Staff Management', SITE_URL.'/admin/staff.php'],
    ['categories',       '📁', 'Categories',       SITE_URL.'/admin/categories.php'],
    ['notifications',    '🔔', 'Notifications',    SITE_URL.'/admin/notifications.php'],
    ['reports',          '📊', 'Analytics',        SITE_URL.'/admin/reports.php'],
    ['settings',         '⚙️', 'Settings',         SITE_URL.'/admin/settings.php'],
  ],
  'staff' => [
    ['dashboard',        '🏠', 'My Tasks',         SITE_URL.'/staff/dashboard.php'],
    ['notifications',    '🔔', 'Notifications',    SITE_URL.'/staff/notifications.php'],
    ['settings',         '⚙️', 'Settings',         SITE_URL.'/staff/settings.php'],
  ],
];

$roleLabel = ['student'=>'Student Portal','admin'=>'Admin Panel','staff'=>'Maintenance Staff'][$role] ?? '';

// Notification count
$db = getDB();
$notifStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifStmt->execute([$_SESSION['user_id']]);
$unread = $notifStmt->fetchColumn();
?>
<button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle menu" type="button">☰</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo">
      <div class="brand-icon">H</div>
      <div>
        <div class="brand-name">HostelIQ</div>
        <div class="brand-tagline"><?=$roleLabel?></div>
      </div>
    </div>
    <div class="sidebar-user">
      <?php if (!empty($sidebarUser['avatar_path'])): ?>
        <img src="<?=avatarUrl($sidebarUser)?>" alt=""
          style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">
      <?php else: ?>
        <div class="user-avatar"><?=strtoupper(substr($initials,0,2))?></div>
      <?php endif; ?>
      <div>
        <div class="user-name"><?=sanitize($name)?></div>
        <div class="user-role"><?=ucfirst($role)?></div>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main Menu</div>
    <?php foreach ($menus[$role] as [$key,$icon,$label,$url]): ?>
    <a href="<?=$url?>" class="nav-item <?=$activePage===$key?'active':''?>">
      <span class="nav-icon"><?=$icon?></span>
      <?=$label?>
      <?php if ($key==='notifications' && $unread>0): ?>
        <span class="nav-badge"><?=$unread?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <a href="<?=SITE_URL?>/logout.php" class="nav-item">
      <span class="nav-icon">🚪</span> Logout
    </a>
  </div>
</aside>
