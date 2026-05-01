<?php
require_once '../includes/auth.php';
requireLogin('admin');
$activePage = 'staff';
$db = getDB();

$success = getFlash('success');
$error   = getFlash('error');

// Handle deactivate/activate toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $tid = (int)$_POST['toggle_id'];
    $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND role = 'staff'")->execute([$tid]);
    setFlash('success', 'Staff account updated.');
    redirect(SITE_URL . '/admin/staff.php');
}

// Get all staff
$staff = $db->query("
    SELECT u.*, COUNT(r.id) AS active_tasks
    FROM users u
    LEFT JOIN requests r ON r.assigned_to = u.id AND r.status != 'Completed'
    WHERE u.role = 'staff'
    GROUP BY u.id
    ORDER BY u.is_active DESC, u.name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff Management — HostelIQ Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div>
        <div class="page-title">Staff Management</div>
        <div class="page-subtitle">Manage maintenance staff accounts and monitor workloads</div>
      </div>
      <a href="add_staff.php" class="btn btn-primary">➕ Add Staff Member</a>
    </div>
    <div class="page-body">
      <?php if ($success): ?><div class="alert alert-success" data-auto-dismiss>✓ <?=sanitize($success)?></div><?php endif; ?>
      <?php if ($error):   ?><div class="alert alert-danger">⚠ <?=sanitize($error)?></div><?php endif; ?>

      <!-- STATS -->
      <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
        <?php
        $total    = count($staff);
        $active   = count(array_filter($staff, fn($s) => $s['is_active']));
        $inactive = $total - $active;
        $avgLoad  = $active ? round(array_sum(array_column(array_filter($staff,fn($s)=>$s['is_active']),'active_tasks')) / max($active,1),1) : 0;
        foreach ([
          ['Total Staff', $total,   '#6c757d','👷'],
          ['Active',      $active,  '#28A745','✅'],
          ['Avg Workload',$avgLoad, '#FFC107','📋'],
          ['Inactive',    $inactive,'#DC3545','🔴'],
        ] as [$l,$v,$c,$i]): ?>
        <div class="stat-card" style="--accent:<?=$c?>">
          <div class="stat-label"><?=$l?></div>
          <div class="stat-value"><?=$v?></div>
          <div class="stat-icon"><?=$i?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="card">
        <div class="table-wrapper">
          <?php if (empty($staff)): ?>
          <div class="empty-state">
            <div class="empty-icon">👷</div>
            <div class="empty-title">No staff accounts yet</div>
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px">Add your first staff member to start assigning requests.</p>
            <a href="add_staff.php" class="btn btn-primary">➕ Add Staff Member</a>
          </div>
          <?php else: ?>
          <table class="table">
            <thead>
              <tr><th>Staff Member</th><th>Profession</th><th>Email</th><th>Active Tasks</th><th>Workload</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($staff as $s): ?>
            <?php
            $maxLoad = 5;
            $loadPct = min(100, round($s['active_tasks'] / $maxLoad * 100));
            $loadColor = $loadPct >= 80 ? '#DC3545' : ($loadPct >= 50 ? '#FFC107' : '#28A745');
            ?>
            <tr style="<?=!$s['is_active']?'opacity:.55;':''?>">
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div class="avatar" style="background:<?=$s['is_active']?'var(--primary)':'#6c757d'?>">
                    <?=strtoupper(substr(explode(' ',$s['name'])[0],0,1).substr(explode(' ',$s['name'])[1]??'',0,1))?>
                  </div>
                  <strong><?=sanitize($s['name'])?></strong>
                </div>
              </td>
              <td style="font-size:12px;">
                <?php if (!empty($s['profession'])): ?>
                  <span class="badge badge-secondary"><?=sanitize($s['profession'])?></span>
                <?php else: ?>
                  <span style="color:var(--text-muted);font-size:11px;">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;color:var(--text-muted)"><?=sanitize($s['email'])?></td>
              <td style="font-weight:700"><?=$s['active_tasks']?></td>
              <td style="min-width:120px">
                <div class="progress-bar">
                  <div class="progress-fill" style="width:<?=$loadPct?>%;background:<?=$loadColor?>"></div>
                </div>
                <div style="font-size:10px;color:var(--text-muted);margin-top:3px"><?=$s['active_tasks']?>/<?=$maxLoad?> tasks</div>
              </td>
              <td>
                <?php if ($s['is_active']): ?>
                  <span class="badge badge-success">Active</span>
                <?php else: ?>
                  <span class="badge badge-danger">Inactive</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;color:var(--text-muted)"><?=date('M j, Y',strtotime($s['created_at']))?></td>
              <td>
                <div style="display:flex;gap:6px;">
                  <a href="add_staff.php?edit=<?=$s['id']?>" class="btn btn-outline btn-sm">Edit</a>
                  <form method="POST" style="display:inline" onsubmit="return confirm('<?=$s['is_active']?'Deactivate':'Activate'?> this staff account?');">
                    <input type="hidden" name="toggle_id" value="<?=$s['id']?>">
                    <button type="submit" class="btn btn-sm <?=$s['is_active']?'btn-danger':'btn-success'?>">
                      <?=$s['is_active']?'Deactivate':'Activate'?>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
