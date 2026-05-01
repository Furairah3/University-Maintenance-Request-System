<?php
require_once '../includes/auth.php';
requireLogin('admin');
$activePage = 'reports';
$db = getDB();

// Overall stats
$stats = $db->query("SELECT COUNT(*) total, SUM(status='Completed') completed, SUM(status='Pending') pending, SUM(status='In Progress') inprogress FROM requests")->fetch();

// Avg completion time
$avgComp = $db->query("SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR,r.created_at,sh.changed_at)),1) FROM requests r JOIN status_history sh ON sh.request_id=r.id AND sh.new_status='Completed'")->fetchColumn();
$avgResp = $db->query("SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR,r.created_at,sh.changed_at)),1) FROM requests r JOIN status_history sh ON sh.request_id=r.id AND sh.new_status='In Progress'")->fetchColumn();

// Category breakdown
$cats = $db->query("SELECT category, COUNT(*) cnt FROM requests GROUP BY category ORDER BY cnt DESC")->fetchAll();
$maxCat = max(1, max(array_column($cats,'cnt') ?: [1]));

// Priority breakdown
$prios = $db->query("SELECT priority, COUNT(*) cnt FROM requests WHERE priority IS NOT NULL GROUP BY priority ORDER BY FIELD(priority,'High','Medium','Low')")->fetchAll();

// Monthly trend (last 6 months)
$monthly = $db->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month, COUNT(*) cnt
    FROM requests
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY created_at ASC
")->fetchAll();
$maxMonthly = max(1, max(array_column($monthly,'cnt') ?: [1]));

// Staff performance
$staffPerf = $db->query("
    SELECT u.name, COUNT(r.id) total, SUM(r.status='Completed') done, COUNT(CASE WHEN r.status!='Completed' THEN 1 END) active
    FROM users u
    LEFT JOIN requests r ON r.assigned_to = u.id
    WHERE u.role='staff' AND u.is_active=1
    GROUP BY u.id ORDER BY total DESC
")->fetchAll();

$resolution = $stats['total'] > 0 ? round($stats['completed']/$stats['total']*100) : 0;
$catColors = ['Electrical'=>'#8B0000','Plumbing'=>'#0d6efd','Furniture'=>'#28A745','HVAC'=>'#FFC107','Other'=>'#6c757d'];
$prioColors = ['High'=>'#DC3545','Medium'=>'#FFC107','Low'=>'#28A745'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Analytics — HostelIQ Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div><div class="page-title">Analytics & Reports</div><div class="page-subtitle">Performance overview — all time</div></div>
    </div>
    <div class="page-body">

      <!-- KPI CARDS -->
      <div class="stat-grid" style="grid-template-columns:repeat(4,1fr)">
        <?php foreach ([
          ['Avg Response Time', ($avgResp??'—').'h', '#FFC107','Time to first action'],
          ['Avg Completion',    ($avgComp??'—').'h', '#0d6efd','Submission to resolved'],
          ['Resolution Rate',   $resolution.'%',     '#28A745','Requests fully resolved'],
          ['Open Requests',     $stats['pending']+$stats['inprogress'], '#DC3545', 'Pending + In Progress'],
        ] as [$l,$v,$c,$note]): ?>
        <div class="stat-card" style="--accent:<?=$c?>">
          <div class="stat-label"><?=$l?></div>
          <div class="stat-value"><?=$v?></div>
          <div style="font-size:10px;color:var(--text-muted);margin-top:6px"><?=$note?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

        <!-- CATEGORY CHART -->
        <div class="card">
          <div class="card-header"><div class="card-title">Requests by Category</div></div>
          <div class="card-body">
            <?php foreach ($cats as $c): ?>
            <?php $pct = round($c['cnt']/$maxCat*100); $color = $catColors[$c['category']] ?? '#6c757d'; ?>
            <div style="margin-bottom:14px;">
              <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px;">
                <span style="font-weight:600"><?=sanitize($c['category'])?></span>
                <span><?=$c['cnt']?> <span style="color:var(--text-muted)">(<?=round($c['cnt']/$stats['total']*100)?>%)</span></span>
              </div>
              <div class="progress-bar" style="height:10px">
                <div class="progress-fill" style="width:<?=$pct?>%;background:<?=$color?>"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- PRIORITY CHART -->
        <div class="card">
          <div class="card-header"><div class="card-title">Requests by Priority</div></div>
          <div class="card-body">
            <!-- Simple donut-style visual with bars -->
            <?php foreach ($prios as $p): ?>
            <?php if (!$p['priority']) continue; $color = $prioColors[$p['priority']] ?? '#6c757d'; $pct = $stats['total']?round($p['cnt']/$stats['total']*100):0; ?>
            <div style="margin-bottom:16px;">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <div style="display:flex;align-items:center;gap:8px;">
                  <div style="width:10px;height:10px;border-radius:50%;background:<?=$color?>"></div>
                  <span style="font-size:12px;font-weight:600"><?=sanitize($p['priority'])?></span>
                </div>
                <strong><?=$p['cnt']?></strong>
              </div>
              <div class="progress-bar" style="height:8px">
                <div class="progress-fill" style="width:<?=$pct?>%;background:<?=$color?>"></div>
              </div>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:20px;padding:14px;background:var(--bg);border-radius:8px;">
              <div style="font-size:12px;font-weight:700;margin-bottom:8px;">Summary</div>
              <?php foreach ([['Total',  $stats['total'],'#222'],['Pending',$stats['pending'],'#856404'],['In Progress',$stats['inprogress'],'#084298'],['Completed',$stats['completed'],'#155724']] as [$l,$v,$c]): ?>
              <div style="display:flex;justify-content:space-between;font-size:12px;padding:4px 0;">
                <span style="color:var(--text-muted)"><?=$l?></span><strong style="color:<?=$c?>"><?=$v?></strong>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;">

        <!-- MONTHLY TREND -->
        <div class="card">
          <div class="card-header"><div class="card-title">Monthly Request Volume (Last 6 Months)</div></div>
          <div class="card-body">
            <?php if (empty($monthly)): ?>
            <p style="color:var(--text-muted);font-size:13px">No data yet.</p>
            <?php else: ?>
            <!-- Bar chart using CSS -->
            <div style="display:flex;align-items:flex-end;gap:16px;height:160px;padding-bottom:8px;border-bottom:2px solid var(--border);">
              <?php foreach ($monthly as $m): ?>
              <?php $h = round($m['cnt']/$maxMonthly*140); ?>
              <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
                <span style="font-size:11px;font-weight:700;color:var(--primary)"><?=$m['cnt']?></span>
                <div style="width:100%;height:<?=$h?>px;background:var(--primary);border-radius:4px 4px 0 0;opacity:.85;transition:.3s;"></div>
              </div>
              <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:16px;margin-top:8px;">
              <?php foreach ($monthly as $m): ?>
              <div style="flex:1;text-align:center;font-size:10px;color:var(--text-muted)"><?=$m['month']?></div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- STAFF PERFORMANCE -->
        <div class="card">
          <div class="card-header"><div class="card-title">Staff Performance</div></div>
          <div class="card-body" style="padding:16px;">
            <?php if (empty($staffPerf)): ?>
            <p style="color:var(--text-muted);font-size:13px">No staff data yet.</p>
            <?php else: ?>
            <?php foreach ($staffPerf as $s): ?>
            <?php $rate = $s['total']?round($s['done']/$s['total']*100):0; ?>
            <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border);">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                <div style="display:flex;align-items:center;gap:8px;">
                  <div class="avatar" style="width:28px;height:28px;font-size:11px"><?=strtoupper(substr($s['name'],0,2))?></div>
                  <span style="font-size:12px;font-weight:600"><?=sanitize($s['name'])?></span>
                </div>
                <span style="font-size:11px;color:var(--text-muted)"><?=$s['done']?>/<?=$s['total']?> done</span>
              </div>
              <div class="progress-bar" style="height:6px">
                <div class="progress-fill" style="width:<?=$rate?>%;background:<?=$rate>=80?'#28A745':($rate>=50?'#FFC107':'#DC3545')?>"></div>
              </div>
              <div style="font-size:10px;color:var(--text-muted);margin-top:3px"><?=$rate?>% completion · <?=$s['active']?> active</div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
