<?php
require_once __DIR__ . '/../backend/includes/Auth.php';
require_once __DIR__ . '/../backend/includes/helpers.php';
Auth::requireRole('student');

$db = Database::getInstance();
$requestId = (int)($_GET['id'] ?? 0);
$pageTitle = 'Request Details';

// Fetch request (must belong to current student)
$request = $db->fetchOne(
    "SELECT r.*, c.name as category_name, 
            COALESCE(u.name, 'Unassigned') as assignee_name
     FROM requests r
     JOIN categories c ON r.category_id = c.id
     LEFT JOIN users u ON r.assigned_to = u.id
     WHERE r.id = ? AND r.created_by = ?", [$requestId, Auth::getUserId()]
);

if (!$request) {
    redirect('dashboard.php', 'Request not found.', 'error');
}

// Handle re-open action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reopen'])) {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        redirect("view-request.php?id=$requestId", 'Invalid request.', 'error');
    }
    
    if ($request['status'] === 'Completed' && $request['completed_at']) {
        $completedTime = strtotime($request['completed_at']);
        $hoursSince = (time() - $completedTime) / 3600;
        
        if ($hoursSince <= 48) {
            $db->beginTransaction();
            try {
                $db->execute("UPDATE requests SET status = 'In Progress', completed_at = NULL WHERE id = ?", [$requestId]);
                $db->insert(
                    "INSERT INTO status_history (request_id, old_status, new_status, changed_by, change_reason) VALUES (?, 'Completed', 'In Progress', ?, 'Re-opened by student')",
                    [$requestId, Auth::getUserId()]
                );
                if ($request['assigned_to']) {
                    $db->insert(
                        "INSERT INTO notifications (user_id, request_id, type, title, message) VALUES (?, ?, 'reopen', 'Request Re-opened', ?)",
                        [$request['assigned_to'], $requestId, "Request \"{$request['title']}\" has been re-opened by the student."]
                    );
                }
                $db->commit();
                Logger::activity('reopen_request', 'request', $requestId);
                redirect("view-request.php?id=$requestId", 'Request has been re-opened.', 'success');
            } catch (Exception $e) {
                $db->rollBack();
                redirect("view-request.php?id=$requestId", 'Failed to re-open request.', 'error');
            }
        }
    }
}

// Get status history
$history = $db->fetchAll(
    "SELECT sh.*, u.name as changed_by_name
     FROM status_history sh
     JOIN users u ON sh.changed_by = u.id
     WHERE sh.request_id = ?
     ORDER BY sh.changed_at ASC", [$requestId]
);

// Check if re-open is available (within 48 hours of completion)
$canReopen = false;
if ($request['status'] === 'Completed' && $request['completed_at']) {
    $hoursSince = (time() - strtotime($request['completed_at'])) / 3600;
    $canReopen = $hoursSince <= 48;
    $hoursLeft = max(0, 48 - $hoursSince);
}

include __DIR__ . '/../frontend/includes/student-header.php';
$flash = getFlash();
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<a href="dashboard.php" class="btn btn-secondary btn-sm mb-2">&larr; Back to Dashboard</a>

<div class="card mb-3">
    <div class="card-header">
        <h3><?= htmlspecialchars($request['title']) ?></h3>
        <div class="flex gap-1">
            <?= statusBadge($request['status']) ?>
            <?= priorityBadge($request['priority']) ?>
        </div>
    </div>
    <div class="card-body">
        <div class="form-row mb-2">
            <div>
                <strong style="font-size:13px;color:var(--text-secondary)">Category</strong>
                <p><?= htmlspecialchars($request['category_name']) ?></p>
            </div>
            <div>
                <strong style="font-size:13px;color:var(--text-secondary)">Assigned to</strong>
                <p><?= htmlspecialchars($request['assignee_name']) ?></p>
            </div>
        </div>
        <div class="form-row mb-2">
            <div>
                <strong style="font-size:13px;color:var(--text-secondary)">Location</strong>
                <p><?= htmlspecialchars($request['location'] ?: 'Not specified') ?>, Room <?= htmlspecialchars($request['room_number'] ?: 'N/A') ?></p>
            </div>
            <div>
                <strong style="font-size:13px;color:var(--text-secondary)">Submitted</strong>
                <p><?= formatDate($request['created_at'], 'M d, Y \a\t h:i A') ?></p>
            </div>
        </div>
        <div class="mb-2">
            <strong style="font-size:13px;color:var(--text-secondary)">Description</strong>
            <p style="margin-top:6px;line-height:1.7"><?= nl2br(htmlspecialchars($request['description'])) ?></p>
        </div>

        <?php if ($request['image_url']): ?>
            <div class="mb-2">
                <strong style="font-size:13px;color:var(--text-secondary)">Attached photo</strong>
                <div class="image-preview">
                    <img src="<?= APP_URL . '/' . htmlspecialchars($request['image_url']) ?>" alt="Request image">
                </div>
            </div>
        <?php endif; ?>

        <?php if ($canReopen): ?>
            <div class="alert alert-warning mt-2">
                <div>
                    <strong>Not resolved?</strong> You can re-open this request within the next <?= round($hoursLeft) ?> hours.
                    <form method="POST" style="display:inline;margin-left:12px;">
                        <?= csrfField() ?>
                        <button type="submit" name="reopen" value="1" class="btn btn-danger btn-sm"
                                onclick="return confirm('Are you sure you want to re-open this request?')">
                            Re-open Request
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Status Timeline -->
<div class="card">
    <div class="card-header"><h3>Status history</h3></div>
    <div class="card-body">
        <?php if (empty($history)): ?>
            <p class="text-muted">No status changes recorded yet.</p>
        <?php else: ?>
            <?php foreach ($history as $i => $h): ?>
                <div style="display:flex;gap:16px;padding:12px 0;<?= $i < count($history)-1 ? 'border-bottom:1px solid var(--border)' : '' ?>">
                    <div style="width:10px;display:flex;flex-direction:column;align-items:center;">
                        <div style="width:10px;height:10px;border-radius:50%;background:<?= $h['new_status'] === 'Completed' ? 'var(--success)' : ($h['new_status'] === 'In Progress' ? 'var(--primary)' : 'var(--warning)') ?>;flex-shrink:0"></div>
                        <?php if ($i < count($history)-1): ?>
                            <div style="width:2px;flex:1;background:var(--border);margin-top:4px"></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-size:14px;font-weight:500">
                            <?= $h['old_status'] ? htmlspecialchars($h['old_status']) . ' → ' : '' ?>
                            <?= htmlspecialchars($h['new_status']) ?>
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
                            by <?= htmlspecialchars($h['changed_by_name']) ?> &middot; <?= formatDate($h['changed_at'], 'M d, Y \a\t h:i A') ?>
                        </div>
                        <?php if ($h['change_reason']): ?>
                            <div style="font-size:13px;color:var(--text-secondary);margin-top:4px;font-style:italic">
                                <?= htmlspecialchars($h['change_reason']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../frontend/includes/footer.php'; ?>
