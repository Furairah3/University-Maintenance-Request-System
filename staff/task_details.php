<?php
require_once '../includes/auth.php';
require_once '../includes/email.php';
requireLogin('staff');
$activePage = 'dashboard';
$db  = getDB();
$uid = $_SESSION['user_id'];
$id  = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT r.*, u.name AS student_name, u.room_number, u.email AS student_email
    FROM requests r JOIN users u ON u.id = r.student_id
    WHERE r.id = ? AND r.assigned_to = ?
");
$stmt->execute([$id, $uid]);
$task = $stmt->fetch();
if (!$task) { redirect(SITE_URL . '/staff/dashboard.php'); }

$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['new_status'] ?? '';
    $allowed = [
        'Pending'     => ['In Progress'],
        'In Progress' => ['Completed'],
    ];

    if (!isset($allowed[$task['status']]) || !in_array($newStatus, $allowed[$task['status']])) {
        $error = 'Invalid status transition.';
    } else {
        $oldStatus = $task['status'];
        $db->prepare("UPDATE requests SET status=?, updated_at=NOW() WHERE id=?")
           ->execute([$newStatus, $id]);
        $db->prepare("INSERT INTO status_history (request_id,old_status,new_status,changed_by) VALUES (?,?,?,?)")
           ->execute([$id, $oldStatus, $newStatus, $uid]);
        notifyStatusChange($id, $oldStatus, $newStatus);
        notifyAdminsOfStatus($id, $newStatus, 'staff');

        setFlash('success', "Task marked as {$newStatus}. Student and admin have been notified.");
        redirect(SITE_URL . '/staff/task_details.php?id='.$id);
    }
}

$flash   = getFlash('success');
$hist    = $db->prepare("SELECT sh.*, u.name AS changed_by_name FROM status_history sh LEFT JOIN users u ON u.id=sh.changed_by WHERE sh.request_id=? ORDER BY sh.changed_at DESC");
$hist->execute([$id]);
$history = $hist->fetchAll();

$steps = ['Pending','Assigned','In Progress','Completed'];
$stepIdx = ['Pending'=>0,'In Progress'=>2,'Completed'=>3];
$currentStep = $stepIdx[$task['status']] ?? 0;
if ($task['assigned_to']) $currentStep = max($currentStep, 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Task Details — HostelIQ Staff</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div>
        <div class="page-title">Task Details</div>
        <div class="page-subtitle"><?=sanitize($task['title'])?></div>
      </div>
      <a href="dashboard.php" class="btn btn-outline">← Back to Tasks</a>
    </div>
    <div class="page-body">
      <?php if ($flash): ?><div class="alert alert-success" data-auto-dismiss>✓ <?=sanitize($flash)?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger">⚠ <?=sanitize($error)?></div><?php endif; ?>

      <!-- TIMELINE -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-body" style="padding:20px 28px;">
          <div style="font-size:13px;font-weight:600;margin-bottom:16px;">Task Progress</div>
          <div class="timeline">
            <?php foreach ($steps as $i=>$step): ?>
            <div class="tl-step <?=$i<$currentStep?'done':($i===$currentStep?'active':'')?>">
              <div class="tl-dot"><?=$i<$currentStep?'✓':$i+1?></div>
              <div class="tl-label"><?=$step?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">

        <!-- TASK DETAILS -->
        <div class="card">
          <div class="card-body">
            <h2 style="font-size:18px;font-weight:800;margin-bottom:10px;"><?=sanitize($task['title'])?></h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
              <?=statusBadge($task['status'])?>
              <?=priorityBadge($task['priority'])?>
              <span class="badge badge-secondary"><?=sanitize($task['category'])?></span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px;padding:16px;background:var(--bg);border-radius:8px;">
              <?php foreach ([
                ['Student',    sanitize($task['student_name'])],
                ['Room',       sanitize($task['room_number'] ?? '—')],
                ['Category',   sanitize($task['category'])],
                ['Submitted',  date('M j, Y g:ia', strtotime($task['created_at']))],
              ] as [$l,$v]): ?>
              <div>
                <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;"><?=$l?></div>
                <div style="font-size:13px;font-weight:600;margin-top:2px;"><?=$v?></div>
              </div>
              <?php endforeach; ?>
            </div>

            <div style="margin-bottom:18px;">
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:8px;">Full Description</div>
              <p style="font-size:13px;line-height:1.7;white-space:pre-wrap;background:var(--bg);padding:14px;border-radius:8px;"><?=sanitize($task['description'])?></p>
            </div>

            <?php if ($task['image_path']): ?>
            <div>
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:8px;">Photo</div>
              <img src="<?=UPLOAD_URL.sanitize($task['image_path'])?>" alt="Task photo" style="max-width:100%;max-height:280px;border-radius:8px;border:1px solid var(--border);">
            </div>
            <?php else: ?>
            <div style="background:var(--bg);border-radius:8px;padding:20px;text-align:center;color:var(--text-muted);font-size:12px;">📷 No photo attached</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ACTION PANEL -->
        <div>
          <div class="card" style="margin-bottom:16px;border-top:4px solid var(--info);">
            <div class="card-header"><div class="card-title">Update Status</div></div>
            <div class="card-body">
              <div style="margin-bottom:16px;padding:12px;background:var(--bg);border-radius:8px;">
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">Current Status</div>
                <div><?=statusBadge($task['status'])?></div>
              </div>

              <?php if ($task['status'] === 'Pending'): ?>
              <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Click below when you begin working on this task. The student will be notified.</p>
              <form method="POST">
                <input type="hidden" name="new_status" value="In Progress">
                <button type="submit" class="btn btn-info btn-block btn-lg" style="margin-bottom:10px;">🔧 Mark as In Progress</button>
              </form>

              <?php elseif ($task['status'] === 'In Progress'): ?>
              <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Click below when the issue is fully resolved. The student will receive a completion email.</p>
              <form method="POST">
                <input type="hidden" name="new_status" value="Completed">
                <button type="submit" class="btn btn-success btn-block btn-lg" style="margin-bottom:10px;">✅ Mark as Completed</button>
              </form>

              <?php else: ?>
              <div style="background:var(--success-lt);border-radius:8px;padding:16px;text-align:center;">
                <div style="font-size:20px;margin-bottom:6px;">🎉</div>
                <div style="font-size:13px;font-weight:700;color:#155724">Task Completed</div>
                <div style="font-size:12px;color:#155724;margin-top:4px">This task has been resolved and archived.</div>
              </div>
              <?php endif; ?>

              <div style="margin-top:14px;padding:12px;background:#fff8e1;border-radius:8px;font-size:11px;color:#856404;border:1px solid #ffeaa7;">
                ℹ️ Each status change triggers an automatic email notification to the student.
              </div>
            </div>
          </div>

          <!-- ACTIVITY LOG -->
          <div class="card">
            <div class="card-header"><div class="card-title">Activity Log</div></div>
            <div class="card-body" style="padding:16px;max-height:280px;overflow-y:auto;">
              <?php if (empty($history)): ?>
              <p style="font-size:13px;color:var(--text-muted)">No activity yet.</p>
              <?php else: ?>
              <?php foreach ($history as $h): ?>
              <div style="display:flex;gap:10px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--border);">
                <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);margin-top:5px;flex-shrink:0;"></div>
                <div>
                  <div style="font-size:11px;color:var(--text-muted)"><?=date('M j, Y g:ia', strtotime($h['changed_at']))?></div>
                  <div style="font-size:12px;font-weight:500">→ <strong><?=sanitize($h['new_status'])?></strong></div>
                </div>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
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
