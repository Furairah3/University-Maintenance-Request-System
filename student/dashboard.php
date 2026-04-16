<?php
require_once __DIR__ . '/../backend/includes/Auth.php';
require_once __DIR__ . '/../backend/includes/helpers.php';
Auth::requireRole('student');

$db = Database::getInstance();
$userId = Auth::getUserId();
$pageTitle = 'Dashboard';

// Get student stats
$stats = $db->fetchOne(
    "SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed
     FROM requests WHERE created_by = ? AND is_archived = 0", [$userId]
);

// Get recent requests (last 5)
$recentRequests = $db->fetchAll(
    "SELECT r.*, c.name as category_name, 
            COALESCE(u.name, 'Unassigned') as assignee_name
     FROM requests r
     JOIN categories c ON r.category_id = c.id
     LEFT JOIN users u ON r.assigned_to = u.id
     WHERE r.created_by = ? AND r.is_archived = 0
     ORDER BY r.created_at DESC LIMIT 5", [$userId]
);

include __DIR__ . '/../frontend/includes/student-header.php';
?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">📋</div>
        <div class="stat-info">
            <h4>Total requests</h4>
            <div class="value"><?= $stats['total'] ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">⏳</div>
        <div class="stat-info">
            <h4>Pending</h4>
            <div class="value"><?= $stats['pending'] ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">🔧</div>
        <div class="stat-info">
            <h4>In progress</h4>
            <div class="value"><?= $stats['in_progress'] ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">✅</div>
        <div class="stat-info">
            <h4>Completed</h4>
            <div class="value"><?= $stats['completed'] ?></div>
        </div>
    </div>
</div>

<!-- Quick Action -->
<div class="mb-3">
    <a href="new-request.php" class="btn btn-primary">+ Submit New Request</a>
</div>

<!-- Recent Requests -->
<div class="card">
    <div class="card-header">
        <h3>Recent requests</h3>
        <a href="my-requests.php" class="btn btn-secondary btn-sm">View all</a>
    </div>
    <?php if (empty($recentRequests)): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <h3>No requests yet</h3>
            <p>Submit your first maintenance request and track it right here.</p>
            <a href="new-request.php" class="btn btn-primary">Submit Request</a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assigned to</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentRequests as $req): ?>
                    <tr>
                        <td>
                            <a href="view-request.php?id=<?= $req['id'] ?>" class="request-title">
                                <?= htmlspecialchars($req['title']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($req['category_name']) ?></td>
                        <td><?= statusBadge($req['status']) ?></td>
                        <td><?= priorityBadge($req['priority']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($req['assignee_name']) ?></td>
                        <td class="text-muted"><?= timeAgo($req['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../frontend/includes/footer.php'; ?>
