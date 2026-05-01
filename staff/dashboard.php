<?php
require_once '../includes/auth.php';
requireLogin('staff');
$activePage = 'dashboard';
$db  = getDB();
$uid = $_SESSION['user_id'];

$stats = $db->prepare("
    SELECT
      COUNT(*) total,
      SUM(status='Pending') pending,
      SUM(status='In Progress') inprogress,
      SUM(status='Completed') completed
    FROM requests WHERE assigned_to = ?
");
$stats->execute([$uid]);
$s = $stats->fetch();

$filter = $_GET['filter'] ?? 'active';
$sql = "SELECT r.*, u.name AS student_name, u.room_number FROM requests r JOIN users u ON u.id = r.student_id WHERE r.assigned_to = ?";
$params = [$uid];

if ($filter === 'active')    { $sql .= " AND r.status != 'Completed'"; }
elseif ($filter === 'done')  { $sql .= " AND r.status = 'Completed'"; }
$sql .= " ORDER BY FIELD(r.priority,'High','Medium','Low'), r.created_at ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$priorityColors = ['High'=>'#DC3545','Medium'=>'#FFC107','Low'=>'#28A745'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Tasks — HostelIQ Staff</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div>
        <div class="page-title">My Assigned Tasks</div>
        <div class="page-subtitle"><?=$s['inprogress']?> in progress · <?=$s['pending']?> pending</div>
      </div>
    </div>
    <div class="page-body">
      <?php if ($f = getFlash('success')): ?><div class="alert alert-success" data-auto-dismiss>✓ <?=sanitize($f)?></div><?php endif; ?>

      <!-- STAT CARDS -->
      <div class="stat-grid">
        <?php foreach ([
          ['Total Tasks',    $s['total'],     '#6c757d','📋'],
          ['Pending',        $s['pending'],   '#FFC107','⏳'],
          ['In Progress',    $s['inprogress'],'#0d6efd','🔧'],
          ['Completed',      $s['completed'], '#28A745','✅'],
        ] as [$l,$v,$c,$i]): ?>
        <div class="stat-card" style="--accent:<?=$c?>">
          <div class="stat-label"><?=$l?></div>
          <div class="stat-value"><?=$v?></div>
          <div class="stat-icon"><?=$i?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- FILTER TABS -->
      <div style="display:flex;gap:8px;margin-bottom:18px;">
        <?php foreach ([['active','Active Tasks'],['done','Completed'],['all','All']] as [$f,$l]): ?>
        <a href="?filter=<?=$f?>" class="btn <?=$filter===$f?'btn-primary':'btn-outline'?> btn-sm"><?=$l?></a>
        <?php endforeach; ?>
      </div>

      <!-- TASK CARDS GRID -->
      <?php if (empty($tasks)): ?>
      <div class="empty-state card" style="padding:60px 24px">
        <div class="empty-icon">✅</div>
        <div class="empty-title"><?=$filter==='done'?'No completed tasks yet':'No active tasks!'?></div>
        <p style="font-size:13px;color:var(--text-muted)"><?=$filter==='active'?'You\'re all caught up. New tasks will appear here when assigned.':'Tasks you complete will show here.'?></p>
      </div>
      <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;">
        <?php foreach ($tasks as $t): ?>
        <?php $pc = $priorityColors[$t['priority']] ?? '#6c757d'; ?>
        <div class="card" style="border-left:4px solid <?=$pc?>;transition:transform .2s;" onmouseenter="this.style.transform='translateY(-2px)'" onmouseleave="this.style.transform=''">
          <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
              <div style="flex:1;margin-right:10px;">
                <div style="font-size:14px;font-weight:700;line-height:1.4"><?=sanitize($t['title'])?></div>
              </div>
              <?=priorityBadge($t['priority'])?>
            </div>

            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;">
              <?=statusBadge($t['status'])?>
              <span class="badge badge-secondary"><?=sanitize($t['category'])?></span>
            </div>

            <div style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">
              <div>👤 <?=sanitize($t['student_name'])?><?=$t['room_number']?' · Room '.sanitize($t['room_number']):''?></div>
              <div style="margin-top:3px">📅 Submitted <?=date('M j, Y', strtotime($t['created_at']))?></div>
            </div>

            <a href="task_details.php?id=<?=$t['id']?>" class="btn btn-primary btn-block btn-sm">View Task →</a>
          </div>
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
