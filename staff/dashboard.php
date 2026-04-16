<?php
require_once __DIR__ . '/../backend/includes/Auth.php';
require_once __DIR__ . '/../backend/includes/helpers.php';
Auth::requireRole('staff');

$db = Database::getInstance();
$pageTitle = 'My Tasks';
$staffId = Auth::getUserId();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        $reqId = (int)$_POST['request_id'];
        $newStatus = sanitize($_POST['new_status']);

        // Validate this task belongs to this staff member
        $task = $db->fetchOne("SELECT id, status, title, created_by FROM requests WHERE id = ? AND assigned_to = ?", [$reqId, $staffId]);

        if ($task) {
            // Enforce strict transition: Pending → In Progress → Completed
            $validTransitions = [
                'Pending' => 'In Progress',
                'In Progress' => 'Completed'
            ];

            if (isset($validTransitions[$task['status']]) && $validTransitions[$task['status']] === $newStatus) {
                $db->beginTransaction();
                try {
                    $db->execute("UPDATE requests SET status = ? WHERE id = ?", [$newStatus, $reqId]);
                    $db->insert(
                        "INSERT INTO status_history (request_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)",
                        [$reqId, $task['status'], $newStatus, $staffId]
                    );
                    // Notify student
                    $db->insert(
                        "INSERT INTO notifications (user_id, request_id, type, title, message) VALUES (?, ?, 'status_change', ?, ?)",
                        [$task['created_by'], $reqId, "Status Updated", "Your request \"{$task['title']}\" is now $newStatus."]
                    );
                    $db->commit();
                    Logger::activity('update_status', 'request', $reqId, ['from' => $task['status'], 'to' => $newStatus]);
                    redirect('dashboard.php', "Status updated to $newStatus.", 'success');
                } catch (Exception $e) {
                    $db->rollBack();
                    redirect('dashboard.php', 'Failed to update status.', 'error');
                }
            } else {
                redirect('dashboard.php', "Invalid transition: cannot go from {$task['status']} to $newStatus. Follow the sequence: Pending → In Progress → Completed.", 'error');
            }
        }
    }
}

// Stats
$stats = $db->fetchOne(
    "SELECT COUNT(CASE WHEN status IN ('Pending','In Progress') THEN 1 END) as active,
            COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as in_progress,
            COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed
     FROM requests WHERE assigned_to = ? AND is_archived = 0", [$staffId]
);

// Get tasks
$tasks = $db->fetchAll(
    "SELECT r.*, c.name as category_name, u.name as student_name
     FROM requests r
     JOIN categories c ON r.category_id = c.id
     JOIN users u ON r.created_by = u.id
     WHERE r.assigned_to = ? AND r.is_archived = 0
     ORDER BY FIELD(r.priority, 'High', 'Medium', 'Low', NULL), r.created_at DESC", [$staffId]
);

include __DIR__ . '/../frontend/includes/staff-header.php';
$flash = getFlash();
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon yellow">📋</div><div class="stat-info"><h4>Active tasks</h4><div class="value"><?= $stats['active'] ?></div></div></div>
    <div class="stat-card"><div class="stat-icon yellow">⏳</div><div class="stat-info"><h4>Pending</h4><div class="value"><?= $stats['pending'] ?></div></div></div>
    <div class="stat-card"><div class="stat-icon blue">🔧</div><div class="stat-info"><h4>In progress</h4><div class="value"><?= $stats['in_progress'] ?></div></div></div>
    <div class="stat-card"><div class="stat-icon green">✅</div><div class="stat-info"><h4>Completed</h4><div class="value"><?= $stats['completed'] ?></div></div></div>
</div>

<!-- Tasks -->
<div class="card">
    <div class="card-header"><h3>Assigned tasks (<?= count($tasks) ?>)</h3></div>
    <?php if (empty($tasks)): ?>
        <div class="empty-state"><div class="icon">✨</div><h3>No tasks assigned</h3><p>You're all caught up! Check back later for new assignments.</p></div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead><tr><th>Title</th><th>Category</th><th>Student</th><th>Priority</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td>
                            <span class="request-title" onclick="openTaskModal(<?= htmlspecialchars(json_encode($task)) ?>)">
                                <?= htmlspecialchars($task['title']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($task['category_name']) ?></td>
                        <td style="font-size:13px"><?= htmlspecialchars($task['student_name']) ?></td>
                        <td><?= priorityBadge($task['priority']) ?></td>
                        <td><?= statusBadge($task['status']) ?></td>
                        <td class="text-muted" style="font-size:13px"><?= timeAgo($task['created_at']) ?></td>
                        <td>
                            <?php if ($task['status'] === 'Pending'): ?>
                                <form method="POST" style="display:inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="request_id" value="<?= $task['id'] ?>">
                                    <input type="hidden" name="new_status" value="In Progress">
                                    <input type="hidden" name="update_status" value="1">
                                    <button class="btn btn-primary btn-sm" onclick="return confirm('Start working on this task?')">Start</button>
                                </form>
                            <?php elseif ($task['status'] === 'In Progress'): ?>
                                <form method="POST" style="display:inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="request_id" value="<?= $task['id'] ?>">
                                    <input type="hidden" name="new_status" value="Completed">
                                    <input type="hidden" name="update_status" value="1">
                                    <button class="btn btn-success btn-sm" onclick="return confirm('Mark this task as completed?')">Complete</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:12px">Done</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Task Detail Modal -->
<div class="modal-overlay" id="taskModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="taskModalTitle">Task Details</h3>
            <button class="modal-close" onclick="document.getElementById('taskModal').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body" id="taskModalBody"></div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('taskModal').classList.remove('show')">Close</button>
        </div>
    </div>
</div>

<script>
function openTaskModal(task) {
    document.getElementById('taskModalTitle').textContent = task.title;
    document.getElementById('taskModalBody').innerHTML = `
        <p><strong>Student:</strong> ${task.student_name}</p>
        <p><strong>Category:</strong> ${task.category_name}</p>
        <p><strong>Priority:</strong> ${task.priority || 'Not set'}</p>
        <p><strong>Status:</strong> ${task.status}</p>
        <p><strong>Location:</strong> ${task.location || 'N/A'}, Room ${task.room_number || 'N/A'}</p>
        <p><strong>Submitted:</strong> ${task.created_at}</p>
        <hr style="margin:12px 0;border:none;border-top:1px solid #e5e7eb">
        <p><strong>Description:</strong></p>
        <p style="color:#6b7280;line-height:1.7;margin-top:4px">${task.description}</p>
        ${task.image_url ? '<div class="image-preview mt-1"><img src="<?= APP_URL ?>/' + task.image_url + '"></div>' : ''}
    `;
    document.getElementById('taskModal').classList.add('show');
}
document.getElementById('taskModal').addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); });
</script>

<?php include __DIR__ . '/../frontend/includes/footer.php'; ?>
