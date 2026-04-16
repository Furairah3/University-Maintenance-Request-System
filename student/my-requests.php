<?php
require_once __DIR__ . '/../backend/includes/Auth.php';
require_once __DIR__ . '/../backend/includes/helpers.php';
Auth::requireRole('student');

$db = Database::getInstance();
$pageTitle = 'My Requests';

// Filters
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

$where = "r.created_by = ? AND r.is_archived = 0";
$params = [Auth::getUserId()];

if ($statusFilter && in_array($statusFilter, ['Pending', 'In Progress', 'Completed'])) {
    $where .= " AND r.status = ?";
    $params[] = $statusFilter;
}
if ($categoryFilter) {
    $where .= " AND r.category_id = ?";
    $params[] = (int)$categoryFilter;
}

$requests = $db->fetchAll(
    "SELECT r.*, c.name as category_name, COALESCE(u.name, 'Unassigned') as assignee_name
     FROM requests r
     JOIN categories c ON r.category_id = c.id
     LEFT JOIN users u ON r.assigned_to = u.id
     WHERE $where ORDER BY r.created_at DESC", $params
);

$categories = $db->fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY display_order");

include __DIR__ . '/../frontend/includes/student-header.php';
?>

<div class="filters-bar">
    <div class="search-input">
        <input type="text" id="searchInput" placeholder="Search requests..." oninput="filterTable()">
    </div>
    <select class="filter-select" onchange="window.location='?status='+this.value+'&category=<?= $categoryFilter ?>'">
        <option value="">All statuses</option>
        <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
        <option value="In Progress" <?= $statusFilter === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
        <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>Completed</option>
    </select>
    <select class="filter-select" onchange="window.location='?status=<?= $statusFilter ?>&category='+this.value">
        <option value="">All categories</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <a href="new-request.php" class="btn btn-primary btn-sm">+ New Request</a>
</div>

<div class="card">
    <?php if (empty($requests)): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <h3>No requests found</h3>
            <p>No maintenance requests match your current filters.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table" id="requestsTable">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assigned to</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                    <tr>
                        <td><a href="view-request.php?id=<?= $req['id'] ?>" class="request-title"><?= htmlspecialchars($req['title']) ?></a></td>
                        <td><?= htmlspecialchars($req['category_name']) ?></td>
                        <td><?= statusBadge($req['status']) ?></td>
                        <td><?= priorityBadge($req['priority']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($req['assignee_name']) ?></td>
                        <td class="text-muted"><?= formatDate($req['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer text-muted" style="font-size:13px">
            Showing <?= count($requests) ?> request<?= count($requests) !== 1 ? 's' : '' ?>
        </div>
    <?php endif; ?>
</div>

<script>
function filterTable() {
    const query = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#requestsTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}
</script>

<?php include __DIR__ . '/../frontend/includes/footer.php'; ?>
