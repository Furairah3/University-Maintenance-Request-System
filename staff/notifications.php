<?php
require_once '../includes/auth.php';
requireLogin('staff');
$activePage = 'notifications';
$db  = getDB();
$uid = $_SESSION['user_id'];

// Mark a single notification as read, then forward to the target page
if (isset($_GET['read'])) {
    $nid = (int)$_GET['read'];
    if ($nid > 0) {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
           ->execute([$nid, $uid]);
    }
    $goto = $_GET['goto'] ?? '';
    if ($goto && preg_match('/^[a-zA-Z0-9_\-\.\/?=&]+$/', $goto)) {
        redirect(SITE_URL . '/' . ltrim($goto, '/'));
    }
    redirect(SITE_URL . '/staff/notifications.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$uid]);
    redirect(SITE_URL . '/staff/notifications.php');
}

$ust = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$ust->execute([$uid]);
$unread = (int)$ust->fetchColumn();

$stmt = $db->prepare("
    SELECT n.*, r.title AS request_title, r.status AS request_status,
           u.name AS student_name
    FROM notifications n
    LEFT JOIN requests r ON r.id = n.request_id
    LEFT JOIN users u ON u.id = r.student_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 200
");
$stmt->execute([$uid]);
$notifs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Notifications — HostelIQ Staff</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div>
        <div class="page-title">Notifications</div>
        <div class="page-subtitle"><?=count($notifs)?> total<?=$unread ? " — <strong>{$unread} unread</strong>" : ''?></div>
      </div>
      <div class="topbar-actions">
        <?php if ($unread > 0): ?>
        <form method="POST" style="display:inline;">
          <button type="submit" name="mark_all_read" value="1" class="btn btn-outline">✓ Mark all as read</button>
        </form>
        <?php endif; ?>
        <a href="dashboard.php" class="btn btn-outline">← My Tasks</a>
      </div>
    </div>
    <div class="page-body" style="max-width:820px;">
      <?php if (empty($notifs)): ?>
      <div class="card">
        <div class="empty-state">
          <div class="empty-icon">🔔</div>
          <div class="empty-title">No notifications yet</div>
          <p style="font-size:12px;">You'll see new task assignments here.</p>
        </div>
      </div>
      <?php else: ?>
      <div class="card">
        <?php foreach ($notifs as $i => $n): ?>
        <?php
          $msg    = $n['message'];
          $isRead = (int)$n['is_read'] === 1;
          if (stripos($msg, 'assigned') !== false) { $icon = '🛠'; $label = 'New assignment'; }
          else                                     { $icon = '🔔'; $label = ''; }
        ?>
        <div style="display:flex;gap:14px;padding:16px 20px;
                    border-bottom:<?=$i < count($notifs)-1 ? '1px solid var(--border)' : 'none'?>;
                    background:<?=$isRead ? 'transparent' : 'var(--primary-lt)'?>;
                    border-left:4px solid <?=$isRead ? 'transparent' : 'var(--primary)'?>;
                    opacity:<?=$isRead ? '.7' : '1'?>;">
          <div style="width:38px;height:38px;border-radius:50%;
                      background:<?=$isRead ? 'var(--bg)' : 'var(--primary-lt)'?>;
                      display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">
            <?=$icon?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
              <?php if ($label): ?>
              <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;"><?=$label?></div>
              <?php endif; ?>
              <?php if (!$isRead): ?>
              <span class="badge badge-danger" style="font-size:9px;padding:2px 7px;">NEW</span>
              <?php endif; ?>
            </div>
            <div style="font-size:13px;font-weight:<?=$isRead ? '500' : '700'?>;margin-bottom:3px;"><?=sanitize($msg)?></div>
            <?php if ($n['student_name']): ?>
            <div style="font-size:11px;color:var(--text-muted);">Student: <?=sanitize($n['student_name'])?></div>
            <?php endif; ?>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?=date('M j, Y g:ia', strtotime($n['created_at']))?> — <?=timeAgo($n['created_at'])?></div>
          </div>
          <?php if ($n['request_id']): ?>
          <div style="flex-shrink:0;display:flex;align-items:center;">
            <a href="?read=<?=$n['id']?>&goto=staff/task_details.php?id=<?=$n['request_id']?>"
               class="btn <?=$isRead ? 'btn-outline' : 'btn-primary'?> btn-sm">Open</a>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
