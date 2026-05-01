<?php
require_once '../includes/auth.php';
require_once '../includes/schema.php';
requireLogin('student');
$activePage = 'dashboard';
$db = getDB();
$uid = $_SESSION['user_id'];

ensureExtendedSchema();

// Completed requests that still need a review from this student
$pendingReviews = $db->prepare("
    SELECT r.id, r.title, r.updated_at
    FROM requests r
    LEFT JOIN reviews rv ON rv.request_id = r.id
    WHERE r.student_id = ? AND r.status = 'Completed' AND rv.id IS NULL
    ORDER BY r.updated_at DESC
");
$pendingReviews->execute([$uid]);
$needReview = $pendingReviews->fetchAll();

$stats = $db->prepare("
  SELECT
    COUNT(*) AS total,
    SUM(status='Pending') AS pending,
    SUM(status='In Progress') AS inprogress,
    SUM(status='Completed') AS completed
  FROM requests WHERE student_id = ?
");
$stats->execute([$uid]);
$s = $stats->fetch();

$recent = $db->prepare("SELECT * FROM requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 5");
$recent->execute([$uid]);
$recentRequests = $recent->fetchAll();

$user = getUserById($uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Dashboard — HostelIQ</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div>
        <div class="page-title">Good <?=date('G')<12?'morning':(date('G')<17?'afternoon':'evening')?>, <?=sanitize(explode(' ',$_SESSION['name'])[0])?>! 👋</div>
        <div class="page-subtitle"><?=date('l, F j, Y')?><?=$user['room_number']?' · Room '.$user['room_number']:''?></div>
      </div>
      <div class="topbar-actions">
        <a href="new_request.php" class="btn btn-primary">➕ New Request</a>
      </div>
    </div>

    <div class="page-body">
      <?php if ($f = getFlash('success')): ?><div class="alert alert-success" data-auto-dismiss>✓ <?=sanitize($f)?></div><?php endif; ?>

      <?php if (!empty($needReview)): ?>
      <!-- REVIEW REMINDER BANNER -->
      <div class="card" style="margin-bottom:20px;border-left:4px solid var(--gold);background:var(--gold-lt);">
        <div class="card-body" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
          <div style="font-size:28px;">⭐</div>
          <div style="flex:1;min-width:220px;">
            <div style="font-size:14px;font-weight:700;margin-bottom:4px;">
              <?=count($needReview)?> completed request<?=count($needReview)===1?'':'s'?> awaiting your review
            </div>
            <div style="font-size:12px;color:var(--text-muted);">
              Your feedback helps us improve the service. Please rate the work when you get a moment.
            </div>
          </div>
          <?php if (count($needReview) === 1): ?>
          <a href="request_details.php?id=<?=$needReview[0]['id']?>" class="btn btn-gold">Review Now</a>
          <?php else: ?>
          <a href="my_requests.php?status=Completed" class="btn btn-gold">Review Requests</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- STAT CARDS -->
      <div class="stat-grid">
        <div class="stat-card" style="--accent:#6c757d">
          <div class="stat-label">Total Requests</div>
          <div class="stat-value"><?=$s['total']?></div>
          <div class="stat-icon">📋</div>
        </div>
        <div class="stat-card" style="--accent:#FFC107">
          <div class="stat-label">Pending</div>
          <div class="stat-value"><?=$s['pending']?></div>
          <div class="stat-icon">⏳</div>
        </div>
        <div class="stat-card" style="--accent:#0d6efd">
          <div class="stat-label">In Progress</div>
          <div class="stat-value"><?=$s['inprogress']?></div>
          <div class="stat-icon">🔧</div>
        </div>
        <div class="stat-card" style="--accent:#28A745">
          <div class="stat-label">Completed</div>
          <div class="stat-value"><?=$s['completed']?></div>
          <div class="stat-icon">✅</div>
        </div>
      </div>

      <!-- RECENT REQUESTS -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Recent Requests</div>
          <a href="my_requests.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-wrapper">
          <?php if (empty($recentRequests)): ?>
          <div class="empty-state">
            <div class="empty-icon">📭</div>
            <div class="empty-title">No requests yet</div>
            <p style="font-size:13px;margin-bottom:16px;color:var(--text-muted)">Submit your first maintenance request</p>
            <a href="new_request.php" class="btn btn-primary">➕ New Request</a>
          </div>
          <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Category</th>
                <th>Date</th>
                <th>Status</th>
                <th>Priority</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentRequests as $r): ?>
              <tr>
                <td><strong><?=sanitize($r['title'])?></strong></td>
                <td><?=sanitize($r['category'])?></td>
                <td style="white-space:nowrap;color:var(--text-muted)"><?=date('M j, Y', strtotime($r['created_at']))?></td>
                <td><?=statusBadge($r['status'])?></td>
                <td><?=priorityBadge($r['priority'])?></td>
                <td><a href="request_details.php?id=<?=$r['id']?>" class="btn btn-outline btn-sm">View</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /page-body -->
  </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
