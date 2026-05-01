<?php
require_once '../includes/auth.php';
requireLogin('admin');
$activePage = 'dashboard';
$db = getDB();

$stats = $db->query("
  SELECT
    COUNT(*) AS total,
    SUM(status='Pending') AS pending,
    SUM(status='In Progress') AS inprogress,
    SUM(status='Completed') AS completed,
    (SELECT COUNT(*) FROM users WHERE role='staff' AND is_active=1) AS staff_count
  FROM requests
")->fetch();

// Avg completion time (days)
$avg = $db->query("
  SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, r.created_at, sh.changed_at)/24),1) AS avg_days
  FROM requests r
  JOIN status_history sh ON sh.request_id = r.id AND sh.new_status = 'Completed'
  WHERE r.status = 'Completed'
")->fetchColumn();

// Recent requests
$recent = $db->query("
  SELECT r.*, u.name AS student_name, s.name AS staff_name
  FROM requests r
  JOIN users u ON u.id = r.student_id
  LEFT JOIN users s ON s.id = r.assigned_to
  ORDER BY r.created_at DESC LIMIT 8
")->fetchAll();

// Category breakdown
$cats = $db->query("SELECT category, COUNT(*) AS cnt FROM requests GROUP BY category ORDER BY cnt DESC")->fetchAll();
$maxCat = max(1, array_max(array_column($cats,'cnt')));
function array_max($arr){return count($arr)?max($arr):0;}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard — HostelIQ</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div>
        <div class="page-title">Admin Dashboard</div>
        <div class="page-subtitle"><?=date('l, F j, Y')?></div>
      </div>
      <div class="topbar-actions">
        <a href="staff.php" class="btn btn-outline btn-sm">👷 Manage Staff</a>
        <a href="requests.php" class="btn btn-primary btn-sm">📋 All Requests</a>
      </div>
    </div>
    <div class="page-body">

      <!-- STAT CARDS -->
      <div class="stat-grid" style="grid-template-columns:repeat(5,1fr)">
        <?php foreach ([
          ['Total Requests', $stats['total'],      '#6c757d', '📋'],
          ['Pending',        $stats['pending'],     '#FFC107', '⏳'],
          ['In Progress',    $stats['inprogress'],  '#0d6efd', '🔧'],
          ['Completed',      $stats['completed'],   '#28A745', '✅'],
          ['Active Staff',   $stats['staff_count'], '#8B0000', '👷'],
        ] as [$l,$v,$c,$i]): ?>
        <div class="stat-card" style="--accent:<?=$c?>">
          <div class="stat-label"><?=$l?></div>
          <div class="stat-value"><?=$v?></div>
          <div class="stat-icon"><?=$i?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

        <!-- RECENT REQUESTS -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">Recent Requests</div>
            <a href="requests.php" class="btn btn-outline btn-sm">View All</a>
          </div>
          <div class="table-wrapper">
            <table class="table">
              <thead>
                <tr><th>Title</th><th>Student</th><th>Category</th><th>Priority</th><th>Status</th><th>Date</th><th></th></tr>
              </thead>
              <tbody>
              <?php foreach ($recent as $r): ?>
              <tr>
                <td style="max-width:180px"><strong><?=sanitize($r['title'])?></strong></td>
                <td style="font-size:12px"><?=sanitize($r['student_name'])?></td>
                <td><span class="badge badge-secondary"><?=sanitize($r['category'])?></span></td>
                <td><?=priorityBadge($r['priority'])?></td>
                <td><?=statusBadge($r['status'])?></td>
                <td style="white-space:nowrap;color:var(--text-muted);font-size:12px"><?=date('M j', strtotime($r['created_at']))?></td>
                <td><a href="request_details.php?id=<?=$r['id']?>" class="btn btn-outline btn-sm">Manage</a></td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- SIDEBAR PANELS -->
        <div>
          <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><div class="card-title">By Category</div></div>
            <div class="card-body">
              <?php foreach ($cats as $c): ?>
              <div style="margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                  <span><?=sanitize($c['category'])?></span>
                  <strong><?=$c['cnt']?></strong>
                </div>
                <div class="progress-bar">
                  <div class="progress-fill" style="width:<?=round($c['cnt']/$maxCat*100)?>%"></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="card">
            <div class="card-body">
              <div style="font-size:13px;font-weight:700;margin-bottom:12px;">⚡ Quick Stats</div>
              <?php foreach ([
                ['Avg Completion', ($avg??'—').' days'],
                ['Unassigned',     $db->query("SELECT COUNT(*) FROM requests WHERE assigned_to IS NULL AND status='Pending'")->fetchColumn().' requests'],
                ['High Priority',  $db->query("SELECT COUNT(*) FROM requests WHERE priority='High' AND status!='Completed'")->fetchColumn().' open'],
              ] as [$l,$v]): ?>
              <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px;">
                <span style="color:var(--text-muted)"><?=$l?></span>
                <strong><?=$v?></strong>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
