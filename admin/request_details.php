<?php
require_once '../includes/auth.php';
require_once '../includes/email.php';
require_once '../includes/schema.php';
requireLogin('admin');
$activePage = 'requests';
$db  = getDB();
$id  = (int)($_GET['id'] ?? 0);

ensureExtendedSchema();

$stmt = $db->prepare("
    SELECT r.*, u.name AS student_name, u.email AS student_email, u.room_number,
           s.name AS staff_name
    FROM requests r
    JOIN users u ON u.id = r.student_id
    LEFT JOIN users s ON s.id = r.assigned_to
    WHERE r.id = ?
");
$stmt->execute([$id]);
$req = $stmt->fetch();
if (!$req) { redirect(SITE_URL . '/admin/requests.php'); }

// Staff list — include profession so admin can match the right person to the request
$staffList = $db->query("SELECT id, name, profession FROM users WHERE role='staff' AND is_active=1 ORDER BY profession, name")->fetchAll();

// Reopen lineage — parent + children
$parentReq = null;
if (!empty($req['parent_request_id'])) {
    $p = $db->prepare("SELECT id, title, status FROM requests WHERE id = ?");
    $p->execute([$req['parent_request_id']]);
    $parentReq = $p->fetch() ?: null;
}
$cstmt = $db->prepare("SELECT id, title, status, created_at FROM requests WHERE parent_request_id = ? ORDER BY created_at DESC");
$cstmt->execute([$id]);
$childRequests = $cstmt->fetchAll();

// Status history
$hist = $db->prepare("SELECT sh.*, u.name AS changed_by_name FROM status_history sh LEFT JOIN users u ON u.id = sh.changed_by WHERE sh.request_id = ? ORDER BY sh.changed_at DESC");
$hist->execute([$id]);
$history = $hist->fetchAll();

$success = getFlash('success') ?? ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPriority = $_POST['priority'] ?? $req['priority'];
    $newStaff    = (int)($_POST['assigned_to'] ?? 0) ?: null;
    $newStatus   = $_POST['status'] ?? $req['status'];

    $validStatuses  = ['Pending','In Progress','Completed'];
    $validPriorities= ['High','Medium','Low'];

    if (!in_array($newStatus, $validStatuses))   { $error = 'Invalid status.'; }
    if (!in_array($newPriority, $validPriorities)) { $error = 'Invalid priority.'; }

    if (!$error) {
        $oldStatus = $req['status'];

        $db->prepare("UPDATE requests SET priority=?, assigned_to=?, status=?, updated_at=NOW() WHERE id=?")
           ->execute([$newPriority, $newStaff, $newStatus, $id]);

        // Log status change
        if ($oldStatus !== $newStatus) {
            $db->prepare("INSERT INTO status_history (request_id, old_status, new_status, changed_by) VALUES (?,?,?,?)")
               ->execute([$id, $oldStatus, $newStatus, $_SESSION['user_id']]);
            notifyStatusChange($id, $oldStatus, $newStatus);
        }

        // Notify on new assignment
        if ($newStaff && $newStaff !== (int)$req['assigned_to']) {
            notifyAssignment($id, $newStaff);
        }

        $success = 'Request updated successfully.';
        // Refresh data
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        $hist->execute([$id]);
        $history = $hist->fetchAll();
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $db->prepare("DELETE FROM requests WHERE id=?")->execute([$id]);
    setFlash('success', 'Request deleted.');
    redirect(SITE_URL . '/admin/requests.php');
}

// Handle: admin sends a reminder to the assigned staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remind_staff'])) {
    $note = trim($_POST['staff_note'] ?? '');
    $res  = remindStaff($id, $note);
    if (!empty($res['ok'])) {
        setFlash('success', "Reminder sent to {$req['staff_name']}. Student has been informed.");
    } else {
        setFlash('success', $res['error'] ?? 'Could not send reminder.');
    }
    redirect(SITE_URL . '/admin/request_details.php?id=' . $id);
}

// Handle: quick reassign (different form, just changes assigned_to)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_reassign'])) {
    $newStaff = (int)($_POST['new_staff_id'] ?? 0) ?: null;
    if ($newStaff && $newStaff !== (int)$req['assigned_to']) {
        $db->prepare("UPDATE requests SET assigned_to = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$newStaff, $id]);
        notifyAssignment($id, $newStaff);
        setFlash('success', 'Task reassigned. New staff member has been notified.');
    }
    redirect(SITE_URL . '/admin/request_details.php?id=' . $id);
}

$steps = ['Pending','Assigned','In Progress','Completed'];
$stepIdx = ['Pending'=>0,'In Progress'=>2,'Completed'=>3];
$currentStep = $stepIdx[$req['status']] ?? 0;
if ($req['assigned_to']) $currentStep = max($currentStep, 1);

// Student review + reminder history
$rv = $db->prepare("SELECT * FROM reviews WHERE request_id = ?");
$rv->execute([$id]);
$review = $rv->fetch();

$rm = $db->prepare("SELECT * FROM reminders WHERE request_id = ? ORDER BY created_at DESC");
$rm->execute([$id]);
$reminders = $rm->fetchAll();

function adminRenderStars(int $rating): string {
    $out = '<span class="star-rating-display">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= $i <= $rating ? '★' : '<span class="star-off">★</span>';
    }
    return $out . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Request — HostelIQ Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div>
        <div class="page-title">Manage Request</div>
        <div class="page-subtitle"><?=sanitize($req['title'])?></div>
      </div>
      <a href="requests.php" class="btn btn-outline">← Back to All Requests</a>
    </div>
    <div class="page-body">

      <?php if ($success): ?><div class="alert alert-success" data-auto-dismiss>✓ <?=sanitize($success)?></div><?php endif; ?>
      <?php if ($error):   ?><div class="alert alert-danger">⚠ <?=sanitize($error)?></div><?php endif; ?>

      <?php if ($parentReq): ?>
      <div class="alert alert-info" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        🔄 <span>This is a reopen of:</span>
        <a href="request_details.php?id=<?=$parentReq['id']?>" style="font-weight:600;">
          #<?=$parentReq['id']?> — <?=sanitize($parentReq['title'])?>
        </a>
        <?=statusBadge($parentReq['status'])?>
      </div>
      <?php endif; ?>

      <?php if (!empty($childRequests)): ?>
      <div class="alert alert-warning" style="display:flex;flex-direction:column;gap:6px;">
        <div>🔁 <strong>Student has reopened this <?=count($childRequests)?> time<?=count($childRequests)===1?'':'s'?>:</strong></div>
        <?php foreach ($childRequests as $c): ?>
        <a href="request_details.php?id=<?=$c['id']?>" style="font-size:12px;">
          → #<?=$c['id']?> — <?=sanitize($c['title'])?> · <?=$c['status']?> · <?=date('M j, Y', strtotime($c['created_at']))?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- TIMELINE -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-body" style="padding:20px 28px;">
          <div style="font-size:13px;font-weight:600;margin-bottom:16px;">Request Timeline</div>
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

      <div style="display:grid;grid-template-columns:420px 1fr;gap:20px;align-items:start;">

        <!-- ADMIN CONTROLS -->
        <div>
          <div class="card" style="margin-bottom:16px;border-top:4px solid var(--gold);">
            <div class="card-header">
              <div class="card-title">⚙️ Admin Controls</div>
              <span style="font-size:11px;color:var(--text-muted)">Changes trigger notifications</span>
            </div>
            <div class="card-body">
              <form method="POST">
                <div class="form-group">
                  <label class="form-label">Priority</label>
                  <select name="priority" class="form-control">
                    <?php foreach (['High','Medium','Low'] as $p): ?>
                    <option value="<?=$p?>" <?=$req['priority']===$p?'selected':''?>><?=$p?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Assign Staff Member</label>
                  <select name="assigned_to" class="form-control">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($staffList as $st): ?>
                    <?php
                      $tasks=$db->prepare("SELECT COUNT(*) FROM requests WHERE assigned_to=? AND status!='Completed'");
                      $tasks->execute([$st['id']]);
                      $cnt = $tasks->fetchColumn();
                      $prof = !empty($st['profession']) ? $st['profession'] : 'No profession set';
                    ?>
                    <option value="<?=$st['id']?>" <?=(int)$req['assigned_to']===$st['id']?'selected':''?>>
                      <?=sanitize($st['name'])?> · <?=sanitize($prof)?> (<?=$cnt?> active)
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Status</label>
                  <select name="status" class="form-control">
                    <?php foreach (['Pending','In Progress','Completed'] as $st): ?>
                    <option value="<?=$st?>" <?=$req['status']===$st?'selected':''?>><?=$st?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <button type="submit" class="btn btn-gold btn-block" style="margin-bottom:10px;">Save Changes</button>
              </form>
              <form method="POST" onsubmit="return confirm('Delete this request? This cannot be undone.');">
                <input type="hidden" name="delete" value="1">
                <button type="submit" class="btn btn-danger btn-block">🗑 Delete Request</button>
              </form>
            </div>
          </div>

          <?php if ($req['assigned_to']): ?>
          <!-- STAFF ACTIONS — remind or reassign -->
          <div class="card" style="margin-bottom:16px;border-top:4px solid var(--info);">
            <div class="card-header">
              <div class="card-title">👷 Staff Actions</div>
              <span style="font-size:11px;color:var(--text-muted)">Currently: <?=sanitize($req['staff_name'] ?? '—')?></span>
            </div>
            <div class="card-body">
              <!-- Remind current staff -->
              <form method="POST" style="margin-bottom:14px;">
                <div class="form-group">
                  <label class="form-label">Remind <?=sanitize($req['staff_name'] ?? 'staff')?></label>
                  <textarea name="staff_note" class="form-control" rows="2"
                    placeholder="Optional message (e.g. urgency, updated details)…"></textarea>
                  <p class="form-hint">Staff will receive an in-app notification and email. Student is also informed.</p>
                </div>
                <button type="submit" name="remind_staff" value="1" class="btn btn-info btn-block">📣 Send Reminder to Staff</button>
              </form>

              <hr style="border:none;border-top:1px solid var(--border);margin:14px 0;">

              <!-- Quick reassign -->
              <form method="POST" onsubmit="return confirm('Reassign this task to a different staff member?');">
                <div class="form-group">
                  <label class="form-label">Reassign to someone else</label>
                  <select name="new_staff_id" class="form-control" required>
                    <option value="">Pick a staff member…</option>
                    <?php foreach ($staffList as $st): ?>
                      <?php if ((int)$st['id'] === (int)$req['assigned_to']) continue; ?>
                      <option value="<?=$st['id']?>"><?=sanitize($st['name'])?></option>
                    <?php endforeach; ?>
                  </select>
                  <p class="form-hint">Moves the task to the selected staff. Both old and new staff are notified.</p>
                </div>
                <button type="submit" name="quick_reassign" value="1" class="btn btn-outline btn-block">🔁 Reassign Task</button>
              </form>
            </div>
          </div>
          <?php elseif (!empty($reminders)): ?>
          <!-- Student is asking but no staff is assigned yet -->
          <div class="card" style="margin-bottom:16px;border-top:4px solid var(--warning);">
            <div class="card-body">
              <div style="font-size:13px;font-weight:700;margin-bottom:6px;">⚠ Student is asking for follow-up</div>
              <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">
                The student has sent <?=count($reminders)?> reminder<?=count($reminders)===1?'':'s'?> but no staff is assigned.
                Assign a staff member above to get this moving.
              </p>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($review): ?>
          <!-- STUDENT REVIEW -->
          <div class="card" style="margin-bottom:16px;border-top:4px solid var(--gold);">
            <div class="card-header"><div class="card-title">⭐ Student Review</div></div>
            <div class="card-body">
              <?=adminRenderStars((int)$review['rating'])?>
              <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                Submitted <?=date('M j, Y', strtotime($review['created_at']))?>
                <?php if ($review['created_at'] !== $review['updated_at']): ?>
                  (edited <?=timeAgo($review['updated_at'])?>)
                <?php endif; ?>
              </div>
              <?php if (!empty($review['comment'])): ?>
              <p style="font-size:13px;line-height:1.6;margin-top:10px;white-space:pre-wrap;background:var(--bg);padding:10px 12px;border-radius:6px;"><?=sanitize($review['comment'])?></p>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($reminders)): ?>
          <!-- STUDENT REMINDERS -->
          <div class="card" style="margin-bottom:16px;border-top:4px solid var(--info);">
            <div class="card-header">
              <div class="card-title">📣 Student Reminders</div>
              <span class="badge badge-info"><?=count($reminders)?></span>
            </div>
            <div class="card-body" style="padding:14px;max-height:200px;overflow-y:auto;">
              <?php foreach ($reminders as $rem): ?>
              <div style="padding:8px 0;border-bottom:1px solid var(--border);">
                <div style="font-size:11px;color:var(--text-muted);"><?=date('M j, Y g:ia', strtotime($rem['created_at']))?> — <?=timeAgo($rem['created_at'])?></div>
                <?php if (!empty($rem['message'])): ?>
                <div style="font-size:12px;margin-top:4px;"><?=sanitize($rem['message'])?></div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- ACTIVITY LOG -->
          <div class="card">
            <div class="card-header"><div class="card-title">Activity Log</div></div>
            <div class="card-body" style="padding:16px;max-height:300px;overflow-y:auto;">
              <?php if (empty($history)): ?>
              <p style="font-size:13px;color:var(--text-muted)">No activity yet.</p>
              <?php else: ?>
              <?php foreach ($history as $h): ?>
              <div style="display:flex;gap:10px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--border);">
                <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);margin-top:5px;flex-shrink:0;"></div>
                <div>
                  <div style="font-size:11px;color:var(--text-muted)"><?=date('M j, Y g:ia', strtotime($h['changed_at']))?></div>
                  <div style="font-size:12px;font-weight:500">→ <strong><?=sanitize($h['new_status'])?></strong></div>
                  <?php if ($h['changed_by_name']): ?><div style="font-size:11px;color:var(--text-muted)">by <?=sanitize($h['changed_by_name'])?></div><?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- REQUEST DETAILS -->
        <div class="card">
          <div class="card-body">
            <h2 style="font-size:18px;font-weight:800;margin-bottom:10px;"><?=sanitize($req['title'])?></h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
              <?=statusBadge($req['status'])?>
              <?=priorityBadge($req['priority'])?>
              <span class="badge badge-secondary"><?=sanitize($req['category'])?></span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px 24px;margin-bottom:18px;padding:16px;background:var(--bg);border-radius:8px;">
              <?php foreach ([
                ['Student',    sanitize($req['student_name'])],
                ['Room',       sanitize($req['room_number'] ?? '—')],
                ['Email',      sanitize($req['student_email'])],
                ['Category',   sanitize($req['category'])],
                ['Submitted',  date('M j, Y g:ia', strtotime($req['created_at']))],
                ['Updated',    date('M j, Y g:ia', strtotime($req['updated_at']))],
              ] as [$l,$v]): ?>
              <div>
                <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;"><?=$l?></div>
                <div style="font-size:13px;font-weight:600;margin-top:2px;"><?=$v?></div>
              </div>
              <?php endforeach; ?>
            </div>

            <div style="margin-bottom:18px;">
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:8px;">Description</div>
              <p style="font-size:13px;line-height:1.7;white-space:pre-wrap;background:var(--bg);padding:14px;border-radius:8px;"><?=sanitize($req['description'])?></p>
            </div>

            <?php if ($req['image_path']): ?>
            <div>
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:8px;">Attached Photo</div>
              <img src="<?=UPLOAD_URL.sanitize($req['image_path'])?>" alt="Request photo" style="max-width:100%;max-height:300px;border-radius:8px;border:1px solid var(--border);">
            </div>
            <?php else: ?>
            <div style="background:var(--bg);border-radius:8px;padding:24px;text-align:center;color:var(--text-muted);font-size:12px;">No photo attached</div>
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
