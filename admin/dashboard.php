<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../backend/includes/Auth.php';
require_once __DIR__ . '/../backend/includes/helpers.php';
Auth::requireRole('admin');

$db = Database::getInstance();
$pageTitle = 'Admin Dashboard';

// Metrics
$metrics = $db->fetchOne(
    "SELECT COUNT(*) as total,
        COUNT(CASE WHEN status='Pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status='In Progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN status='Completed' THEN 1 END) as completed
     FROM requests WHERE is_archived = 0"
);

$avgResponse = $db->fetchOne(
    "SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, r.created_at, a.assigned_at)),1) as hours
     FROM requests r JOIN assignments a ON r.id = a.request_id WHERE r.is_archived = 0"
);

$avgCompletion = $db->fetchOne(
    "SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, r.created_at, r.completed_at)),1) as hours
     FROM requests r WHERE r.completed_at IS NOT NULL AND r.is_archived = 0"
);

$staffCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role='staff' AND is_active=1");

// Filters
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$priority = $_GET['priority'] ?? '';
$search = $_GET['search'] ?? '';

$where = "r.is_archived = 0";
$params = [];

if ($status && in_array($status, ['Pending','In Progress','Completed'])) { $where .= " AND r.status = ?"; $params[] = $status; }
if ($category) { $where .= " AND r.category_id = ?"; $params[] = (int)$category; }
if ($priority && in_array($priority, ['High','Medium','Low'])) { $where .= " AND r.priority = ?"; $params[] = $priority; }
if ($search) { $where .= " AND (r.title LIKE ? OR creator.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$requests = $db->fetchAll(
    "SELECT r.*, c.name as category_name, creator.name as creator_name, 
            creator.email as creator_email, COALESCE(assignee.name, 'Unassigned') as assignee_name
     FROM requests r
     JOIN categories c ON r.category_id = c.id
     JOIN users creator ON r.created_by = creator.id
     LEFT JOIN users assignee ON r.assigned_to = assignee.id
     WHERE $where ORDER BY r.created_at DESC LIMIT 50", $params
);

$categories = $db->fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY display_order");

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    if (Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        $reqId = (int)$_POST['request_id'];
        $staffId = (int)$_POST['staff_id'];
        $prio = sanitize($_POST['priority'] ?? '');

        $db->beginTransaction();
        try {
            $updates = "assigned_to = ?";
            $updateParams = [$staffId];
            if ($prio && in_array($prio, ['High','Medium','Low'])) {
                $updates .= ", priority = ?";
                $updateParams[] = $prio;
            }
            $updateParams[] = $reqId;
            $db->execute("UPDATE requests SET $updates WHERE id = ?", $updateParams);

            $db->insert("INSERT INTO assignments (request_id, staff_id, assigned_by) VALUES (?, ?, ?)",
                [$reqId, $staffId, Auth::getUserId()]);

            $req = $db->fetchOne("SELECT title, created_by FROM requests WHERE id = ?", [$reqId]);
            $db->insert(
                "INSERT INTO notifications (user_id, request_id, type, title, message) VALUES (?, ?, 'assignment', 'Request Assigned', ?)",
                [$req['created_by'], $reqId, "Your request \"{$req['title']}\" has been assigned to a maintenance staff member."]
            );

            $db->commit();
            Logger::activity('assign_request', 'request', $reqId, ['staff_id' => $staffId]);
            redirect('dashboard.php', 'Request assigned successfully.', 'success');
        } catch (Exception $e) {
            $db->rollBack();
            redirect('dashboard.php', 'Failed to assign request.', 'error');
        }
    }
}

$staffList = $db->fetchAll("SELECT id, name FROM users WHERE role='staff' AND is_active=1 ORDER BY name");

include __DIR__ . '/../frontend/includes/admin-header.php';
$flash = getFlash();
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon blue">📋</div><div class="stat-info"><h4>Total requests</h4><div class="value"><?= $metrics['total'] ?></div></div></div>
    <div class="stat-card"><div class="stat-icon yellow">⏳</div><div class="stat-info"><h4>Pending</h4><div class="value"><?= $metrics['pending'] ?></div></div></div>
    <div class="stat-card"><div class="stat-icon blue">🔧</div><div class="stat-info"><h4>In progress</h4><div class="value"><?= $metrics['in_progress'] ?></div></div></div>
    <div class="stat-card"><div class="stat-icon green">✅</div><div class="stat-info"><h4>Completed</h4><div class="value"><?= $metrics['completed'] ?></div></div></div>
</div>

<div class="stats-grid stats-grid-compact">
    <div class="stat-card"><div class="stat-info"><h4>Avg response time</h4><div class="value stat-value-lg"><?= $avgResponse['hours'] ?? 'N/A' ?> hrs</div></div></div>
    <div class="stat-card"><div class="stat-info"><h4>Avg completion time</h4><div class="value stat-value-lg"><?= $avgCompletion['hours'] ?? 'N/A' ?> hrs</div></div></div>
    <div class="stat-card"><div class="stat-info"><h4>Active staff</h4><div class="value stat-value-lg"><?= $staffCount['count'] ?></div></div></div>
</div>

<!-- Filters -->
<form class="filters-bar" method="GET">
    <div class="search-input">
        <input type="text" name="search" placeholder="Search by title or student name..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="status" class="filter-select">
        <option value="">All statuses</option>
        <option value="Pending" <?= $status==='Pending'?'selected':'' ?>>Pending</option>
        <option value="In Progress" <?= $status==='In Progress'?'selected':'' ?>>In Progress</option>
        <option value="Completed" <?= $status==='Completed'?'selected':'' ?>>Completed</option>
    </select>
    <select name="category" class="filter-select">
        <option value="">All categories</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $category==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="priority" class="filter-select">
        <option value="">All priorities</option>
        <option value="High" <?= $priority==='High'?'selected':'' ?>>High</option>
        <option value="Medium" <?= $priority==='Medium'?'selected':'' ?>>Medium</option>
        <option value="Low" <?= $priority==='Low'?'selected':'' ?>>Low</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">Clear</a>
</form>

<!-- Requests Table -->
<div class="card">
    <div class="card-header">
        <h3>All requests (<?= count($requests) ?>)</h3>
    </div>
    <?php if (empty($requests)): ?>
        <div class="empty-state"><div class="icon">📭</div><h3>No requests found</h3><p>No requests match your filters.</p></div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead><tr><th>Title</th><th>Student</th><th>Category</th><th>Status</th><th>Priority</th><th>Assigned</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                    <tr>
                        <td><span class="request-title" onclick="openAssignModal(<?= htmlspecialchars(json_encode($req)) ?>)"><?= htmlspecialchars($req['title']) ?></span></td>
                        <td class="text-sm"><?= htmlspecialchars($req['creator_name']) ?></td>
                        <td><?= htmlspecialchars($req['category_name']) ?></td>
                        <td><?= statusBadge($req['status']) ?></td>
                        <td><?= priorityBadge($req['priority']) ?></td>
                        <td class="text-muted text-sm"><?= htmlspecialchars($req['assignee_name']) ?></td>
                        <td class="text-muted text-sm"><?= timeAgo($req['created_at']) ?></td>
                        <td><button class="btn btn-outline btn-sm" onclick="openAssignModal(<?= htmlspecialchars(json_encode($req)) ?>)">View / Assign</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Assign Modal -->
<div class="modal-overlay" id="assignModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Request Details</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modalDetails"></div>
            <hr class="modal-separator">
            <form method="POST" id="assignForm">
                <?= csrfField() ?>
                <input type="hidden" name="request_id" id="modalReqId">
                <input type="hidden" name="assign" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Set priority</label>
                        <select name="priority" class="form-control" id="modalPriority">
                            <option value="">-- Select --</option>
                            <option value="High">High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Assign to staff</label>
                        <select name="staff_id" class="form-control" required>
                            <option value="">-- Select staff --</option>
                            <?php foreach ($staffList as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('assignForm').submit()">Assign & Save</button>
        </div>
    </div>
</div>

<script>
function openAssignModal(req) {
    document.getElementById('modalTitle').textContent = req.title;
    document.getElementById('modalReqId').value = req.id;
    document.getElementById('modalPriority').value = req.priority || '';
    document.getElementById('modalDetails').innerHTML = `
        <p><strong>Student:</strong> ${req.creator_name} (${req.creator_email})</p>
        <p><strong>Category:</strong> ${req.category_name}</p>
        <p><strong>Status:</strong> ${req.status}</p>
        <p><strong>Location:</strong> ${req.location || 'N/A'}, Room ${req.room_number || 'N/A'}</p>
        <div class="modal-description">
            <p><strong>Description:</strong></p>
            <p>${req.description}</p>
        </div>
        ${req.image_url ? '<div class="image-preview mt-1"><img src="/' + req.image_url + '"></div>' : ''}
    `;
    document.getElementById('assignModal').classList.add('show');
}

function closeModal() {
    document.getElementById('assignModal').classList.remove('show');
}

document.getElementById('assignModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include __DIR__ . '/../frontend/includes/footer.php'; ?>
