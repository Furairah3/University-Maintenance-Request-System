<?php
require_once '../includes/auth.php';
require_once '../includes/email.php';
require_once '../includes/schema.php';
requireLogin('student');
$activePage = 'my_requests';
$db  = getDB();
$uid = $_SESSION['user_id'];
$id  = (int)($_GET['id'] ?? 0);

ensureExtendedSchema();

$stmt = $db->prepare("SELECT r.*, u.name AS staff_name FROM requests r LEFT JOIN users u ON u.id = r.assigned_to WHERE r.id = ? AND r.student_id = ?");
$stmt->execute([$id, $uid]);
$req = $stmt->fetch();
if (!$req) { redirect(SITE_URL . '/student/my_requests.php'); }

// Parent + children (reopen lineage)
$parentReq = null;
if (!empty($req['parent_request_id'])) {
    $p = $db->prepare("SELECT id, title, status FROM requests WHERE id = ? AND student_id = ?");
    $p->execute([$req['parent_request_id'], $uid]);
    $parentReq = $p->fetch() ?: null;
}
$children = $db->prepare("SELECT id, title, status, created_at FROM requests WHERE parent_request_id = ? AND student_id = ? ORDER BY created_at DESC");
$children->execute([$id, $uid]);
$childRequests = $children->fetchAll();

$error = '';

// Helper — true when the student is still allowed to edit/cancel
$canEdit = ($req['status'] === 'Pending' && empty($req['assigned_to']));

// ── ACTION: Edit own request (only while Pending + unassigned) ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_request'])) {
    if (!$canEdit) {
        $error = 'You can only edit a request while it is still Pending and not yet assigned to staff.';
    } else {
        $newTitle    = trim($_POST['title'] ?? '');
        $newCategory = trim($_POST['category'] ?? '');
        $newDesc     = trim($_POST['description'] ?? '');
        $validCats   = ['Electrical','Plumbing','Furniture','HVAC','Other'];

        if (!$newTitle)                          $error = 'Title is required.';
        elseif (!$newDesc)                       $error = 'Description is required.';
        elseif (!in_array($newCategory, $validCats)) $error = 'Please pick a valid category.';

        if (!$error) {
            $db->prepare("UPDATE requests SET title=?, category=?, description=?, updated_at=NOW() WHERE id=? AND student_id=?")
               ->execute([$newTitle, $newCategory, $newDesc, $id, $uid]);
            notifyAdmins($id, "Student edited request \"{$newTitle}\".");
            setFlash('success', 'Request updated. Admin has been notified.');
            redirect(SITE_URL . '/student/request_details.php?id=' . $id);
        }
    }
}

// ── ACTION: Cancel/withdraw request ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    if (!$canEdit) {
        $error = 'You can only cancel a request while it is Pending and not yet assigned.';
    } else {
        $title = $req['title'];
        notifyAdmins($id, "Student cancelled request \"{$title}\".");
        $db->prepare("DELETE FROM requests WHERE id = ? AND student_id = ?")
           ->execute([$id, $uid]);
        setFlash('success', 'Your request has been cancelled and removed.');
        redirect(SITE_URL . '/student/my_requests.php');
    }
}

// ── ACTION: Submit or update review ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if ($req['status'] !== 'Completed') {
        $error = 'You can only review completed requests.';
    } else {
        $rating  = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($rating < 1 || $rating > 5) {
            $error = 'Please select a rating from 1 to 5 stars.';
        } else {
            // Check if this is a new review (vs update) so we only notify admins once per review
            $existed = $db->prepare("SELECT COUNT(*) FROM reviews WHERE request_id = ?");
            $existed->execute([$id]);
            $wasNew = ((int)$existed->fetchColumn() === 0);

            $db->prepare("
                INSERT INTO reviews (request_id, rating, comment) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)
            ")->execute([$id, $rating, $comment]);

            if ($wasNew) {
                notifyReview($id, $rating, $comment);
            }

            setFlash('success', 'Thanks — your review has been saved and shared with admin.');
            redirect(SITE_URL . '/student/request_details.php?id=' . $id);
        }
    }
}

// ── ACTION: Reopen a completed request ─ creates a NEW linked request ─
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reopen'])) {
    if ($req['status'] !== 'Completed') {
        $error = 'Only completed requests can be reopened.';
    } else {
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            $error = 'Please tell us why you are reopening this request.';
        } else {
            // Build a new request that points back to the original via parent_request_id.
            // Original request stays untouched (Completed, review intact, history intact).
            $newTitle = 'Reopened: ' . $req['title'];
            $newDesc  = "[Reopened from request #{$id}]\n"
                      . "Reason given by student: {$reason}\n\n"
                      . "--- Original description ---\n"
                      . $req['description'];

            $db->prepare("
                INSERT INTO requests
                    (student_id, title, description, category, priority, image_path, status, parent_request_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, NOW(), NOW())
            ")->execute([
                $uid, $newTitle, $newDesc, $req['category'],
                $req['priority'] ?: 'Medium',
                $req['image_path'],   // re-attach the original photo so context isn't lost
                $id,
            ]);
            $newId = (int)$db->lastInsertId();

            // Log initial status entry for the new request
            $db->prepare("INSERT INTO status_history (request_id, old_status, new_status, changed_by) VALUES (?, NULL, 'Pending', ?)")
               ->execute([$newId, $uid]);

            // Notify admins — flagged as a reopen for clarity
            notifyAdmins(
                $newId,
                "Reopen of request #{$id}: \"{$req['title']}\". New request #{$newId}. Reason: {$reason}",
                "[HostelIQ] Reopened request — {$req['title']}"
            );
            // Also fire the standard new-request notification so admins get the usual email + in-app
            notifyNewRequest($newId);

            setFlash('success', "We've created a new request linked to your original. The original stays on record with its review.");
            redirect(SITE_URL . '/student/request_details.php?id=' . $newId);
        }
    }
}

// ── ACTION: Send a reminder ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder'])) {
    if (!in_array($req['status'], ['Pending', 'In Progress'], true)) {
        $error = 'You can only remind us about open requests.';
    } else {
        // Rate-limit: one reminder per 24 hours
        $last = $db->prepare("SELECT created_at FROM reminders WHERE request_id = ? ORDER BY created_at DESC LIMIT 1");
        $last->execute([$id]);
        $lastAt = $last->fetchColumn();
        $canSend = !$lastAt || (time() - strtotime($lastAt)) >= 24*3600;

        if (!$canSend) {
            $error = 'You already sent a reminder in the last 24 hours. Please wait before sending another.';
        } else {
            $note = trim($_POST['note'] ?? '');
            $db->prepare("INSERT INTO reminders (request_id, student_id, message) VALUES (?,?,?)")
               ->execute([$id, $uid, $note]);

            $tail = $note ? " — \"{$note}\"" : '';
            notifyAdmins($id, "Student sent a reminder on \"{$req['title']}\"{$tail}");

            setFlash('success', 'Reminder sent to admin. Thanks for the nudge.');
            redirect(SITE_URL . '/student/request_details.php?id=' . $id);
        }
    }
}

$flash = getFlash('success');

// Reload request + history (status may have changed on reopen)
$stmt->execute([$id, $uid]);
$req = $stmt->fetch();

$hist = $db->prepare("SELECT sh.*, u.name AS changed_by_name FROM status_history sh LEFT JOIN users u ON u.id = sh.changed_by WHERE sh.request_id = ? ORDER BY sh.changed_at ASC");
$hist->execute([$id]);
$history = $hist->fetchAll();

// Existing review (if any)
$review = $db->prepare("SELECT * FROM reviews WHERE request_id = ?");
$review->execute([$id]);
$review = $review->fetch();

// Reminders count + last sent
$remStmt = $db->prepare("SELECT COUNT(*) AS cnt, MAX(created_at) AS last_sent FROM reminders WHERE request_id = ?");
$remStmt->execute([$id]);
$remInfo = $remStmt->fetch();
$lastReminderAt = $remInfo['last_sent'] ?? null;
$reminderCooldown = $lastReminderAt ? max(0, 24*3600 - (time() - strtotime($lastReminderAt))) : 0;

// Timeline steps
$steps = ['Pending','Assigned','In Progress','Completed'];
$statusMap = ['Pending'=>0,'Assigned'=>1,'In Progress'=>2,'Completed'=>3];
$currentStep = $statusMap[$req['status']] ?? 0;
if ($req['assigned_to']) $currentStep = max($currentStep, 1);

function renderStars(int $rating): string {
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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Request Details — HostelIQ</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div>
        <div class="page-title">Request Details</div>
        <div class="page-subtitle"><?=sanitize($req['title'])?></div>
      </div>
      <a href="my_requests.php" class="btn btn-outline">← Back to Requests</a>
    </div>
    <div class="page-body">

      <?php if ($flash): ?><div class="alert alert-success" data-auto-dismiss>✓ <?=sanitize($flash)?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger">⚠ <?=sanitize($error)?></div><?php endif; ?>

      <?php if ($parentReq): ?>
      <div class="alert alert-info" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        🔄 <span>This request was reopened from a previous one:</span>
        <a href="request_details.php?id=<?=$parentReq['id']?>" style="font-weight:600;">
          #<?=$parentReq['id']?> — <?=sanitize($parentReq['title'])?>
        </a>
        <?=statusBadge($parentReq['status'])?>
      </div>
      <?php endif; ?>

      <?php if (!empty($childRequests)): ?>
      <div class="alert alert-warning" style="display:flex;flex-direction:column;gap:6px;">
        <div>🔁 <strong>This request has been reopened <?=count($childRequests)?> time<?=count($childRequests)===1?'':'s'?>:</strong></div>
        <?php foreach ($childRequests as $c): ?>
        <a href="request_details.php?id=<?=$c['id']?>" style="font-size:12px;">
          → #<?=$c['id']?> — <?=sanitize($c['title'])?> (<?=date('M j, Y', strtotime($c['created_at']))?>)
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- STATUS TIMELINE -->
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

      <div class="request-layout">

        <!-- MAIN DETAILS -->
        <div class="card request-form">
          <div class="card-body">
            <h2 style="font-size:18px;font-weight:800;margin-bottom:10px;"><?=sanitize($req['title'])?></h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;">
              <?=statusBadge($req['status'])?>
              <?=priorityBadge($req['priority'])?>
              <span class="badge badge-secondary"><?=sanitize($req['category'])?></span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px 24px;margin-bottom:18px;padding:16px;background:var(--bg);border-radius:8px;">
              <?php foreach ([
                ['Category',    sanitize($req['category'])],
                ['Submitted',   date('M j, Y g:ia', strtotime($req['created_at']))],
                ['Assigned To', sanitize($req['staff_name'] ?? 'Not yet assigned')],
                ['Last Updated',date('M j, Y g:ia', strtotime($req['updated_at']))],
              ] as [$l,$v]): ?>
              <div>
                <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;"><?=$l?></div>
                <div style="font-size:13px;font-weight:600;margin-top:2px;"><?=$v?></div>
              </div>
              <?php endforeach; ?>
            </div>

            <div style="margin-bottom:18px;">
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:8px;">Description</div>
              <p style="font-size:13px;line-height:1.7;white-space:pre-wrap;"><?=sanitize($req['description'])?></p>
            </div>

            <?php if ($req['image_path']): ?>
            <div style="margin-bottom:18px;">
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:8px;">Attached Photo</div>
              <img src="<?=UPLOAD_URL.sanitize($req['image_path'])?>" alt="Request photo" style="max-width:100%;max-height:280px;border-radius:8px;border:1px solid var(--border);">
            </div>
            <?php endif; ?>

            <?php if ($review): ?>
            <div style="padding:16px;background:var(--gold-lt);border-radius:8px;border-left:4px solid var(--gold);">
              <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                <strong style="font-size:13px;">⭐ Your review</strong>
                <span style="font-size:11px;color:var(--text-muted);"><?=date('M j, Y', strtotime($review['created_at']))?></span>
              </div>
              <?=renderStars((int)$review['rating'])?>
              <?php if (!empty($review['comment'])): ?>
              <p style="font-size:13px;line-height:1.6;margin-top:8px;white-space:pre-wrap;"><?=sanitize($review['comment'])?></p>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ACTIONS + ACTIVITY -->
        <div class="request-tips">

          <?php if ($canEdit): ?>
          <!-- EDIT / CANCEL — only allowed while Pending + unassigned -->
          <div class="card" style="margin-bottom:16px;border-top:4px solid var(--gold);">
            <div class="card-header"><div class="card-title">✏️ Edit Request</div></div>
            <div class="card-body">
              <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">
                You can edit or cancel this request until staff is assigned.
              </p>
              <form method="POST">
                <div class="form-group">
                  <label class="form-label">Title</label>
                  <input type="text" name="title" class="form-control"
                    value="<?=sanitize($req['title'])?>" required maxlength="200">
                </div>
                <div class="form-group">
                  <label class="form-label">Category</label>
                  <select name="category" class="form-control" required>
                    <?php foreach (['Electrical','Plumbing','Furniture','HVAC','Other'] as $c): ?>
                    <option value="<?=$c?>" <?=$req['category']===$c?'selected':''?>><?=$c?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Description</label>
                  <textarea name="description" class="form-control" rows="4" required><?=sanitize($req['description'])?></textarea>
                </div>
                <button type="submit" name="edit_request" value="1" class="btn btn-primary btn-block">Save Changes</button>
              </form>

              <hr style="border:none;border-top:1px solid var(--border);margin:14px 0;">

              <form method="POST"
                onsubmit="return confirm('Cancel this request? It will be removed completely. This cannot be undone.');">
                <button type="submit" name="cancel_request" value="1" class="btn btn-danger btn-block">🗑 Cancel Request</button>
              </form>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($req['status'] === 'Completed'): ?>
          <!-- REVIEW FORM -->
          <div class="card" style="margin-bottom:16px;border-top:4px solid var(--gold);">
            <div class="card-header"><div class="card-title"><?=$review ? '✏️ Update Review' : '⭐ Rate the Work'?></div></div>
            <div class="card-body">
              <form method="POST">
                <div class="form-group" style="text-align:center;">
                  <div class="star-rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" name="rating" id="star<?=$i?>" value="<?=$i?>"
                      <?=($review && (int)$review['rating']===$i) ? 'checked' : ''?>>
                    <label for="star<?=$i?>" title="<?=$i?> star<?=$i===1?'':'s'?>">★</label>
                    <?php endfor; ?>
                  </div>
                  <p class="form-hint" style="margin-top:6px;">Tap a star — 1 = poor, 5 = excellent</p>
                </div>
                <div class="form-group">
                  <label class="form-label">Comment (optional)</label>
                  <textarea name="comment" class="form-control" rows="3"
                    placeholder="How was the work? Any follow-up notes?"><?=sanitize($review['comment'] ?? '')?></textarea>
                </div>
                <button type="submit" name="submit_review" value="1" class="btn btn-gold btn-block">
                  <?=$review ? 'Update Review' : 'Submit Review'?>
                </button>
              </form>
            </div>
          </div>

          <!-- REOPEN -->
          <div class="card" style="margin-bottom:16px;border-top:4px solid var(--danger);">
            <div class="card-header"><div class="card-title">🔄 Problem Came Back?</div></div>
            <div class="card-body">
              <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">
                If the same issue has returned, reopen this request instead of creating a new one.
              </p>
              <form method="POST" onsubmit="return confirm('Reopen this request? The admin and staff will be notified.');">
                <div class="form-group">
                  <label class="form-label">Reason *</label>
                  <textarea name="reason" class="form-control" rows="3" required
                    placeholder="e.g. The drain is blocked again after a few days."></textarea>
                </div>
                <button type="submit" name="reopen" value="1" class="btn btn-danger btn-block">Reopen Request</button>
              </form>
            </div>
          </div>

          <?php elseif (in_array($req['status'], ['Pending','In Progress'], true)): ?>
          <!-- SEND REMINDER -->
          <div class="card" style="margin-bottom:16px;border-top:4px solid var(--info);">
            <div class="card-header">
              <div class="card-title">⏰ Taking too long?</div>
              <?php if ($remInfo['cnt'] > 0): ?>
              <span class="badge badge-info"><?=$remInfo['cnt']?> sent</span>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">
                Send a gentle nudge to admin without creating a duplicate request.
              </p>
              <?php if ($reminderCooldown > 0): ?>
              <div class="alert alert-warning" style="font-size:12px;padding:10px 12px;margin-bottom:10px;">
                ⏳ You can send another reminder in
                <strong><?=ceil($reminderCooldown / 3600)?>h</strong>.
              </div>
              <button class="btn btn-outline btn-block" disabled>Reminder Sent Recently</button>
              <?php else: ?>
              <form method="POST">
                <div class="form-group">
                  <label class="form-label">Note (optional)</label>
                  <textarea name="note" class="form-control" rows="2"
                    placeholder="Anything that's gotten worse? Any new context?"></textarea>
                </div>
                <button type="submit" name="send_reminder" value="1" class="btn btn-info btn-block">📣 Send Reminder</button>
              </form>
              <?php endif; ?>
              <?php if ($lastReminderAt): ?>
              <p style="font-size:11px;color:var(--text-muted);margin-top:10px;">
                Last reminder: <?=timeAgo($lastReminderAt)?>
              </p>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- ACTIVITY LOG -->
          <div class="card">
            <div class="card-header"><div class="card-title">Activity Log</div></div>
            <div class="card-body" style="padding:16px;max-height:300px;overflow-y:auto;">
              <?php if (empty($history)): ?>
              <p style="font-size:13px;color:var(--text-muted);">No activity yet.</p>
              <?php else: ?>
              <?php foreach (array_reverse($history) as $h): ?>
              <div style="display:flex;gap:10px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--border);">
                <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);margin-top:5px;flex-shrink:0;"></div>
                <div>
                  <div style="font-size:11px;color:var(--text-muted)"><?=date('M j, Y g:ia', strtotime($h['changed_at']))?></div>
                  <div style="font-size:12px;font-weight:500;margin-top:2px;">
                    Status → <strong><?=sanitize($h['new_status'])?></strong>
                  </div>
                  <?php if ($h['changed_by_name']): ?>
                  <div style="font-size:11px;color:var(--text-muted)">by <?=sanitize($h['changed_by_name'])?></div>
                  <?php endif; ?>
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
