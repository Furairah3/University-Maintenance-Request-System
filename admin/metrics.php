<?php
require_once __DIR__ . '/../backend/includes/Auth.php';
require_once __DIR__ . '/../backend/includes/helpers.php';
Auth::requireRole('admin');

$db = Database::getInstance();
$pageTitle = 'Metrics';

// Top-line KPIs from the admin metrics view
$kpi = $db->fetchOne("SELECT * FROM vw_admin_metrics") ?: [];
$totalRequests   = (int)($kpi['total_requests'] ?? 0);
$pendingCount    = (int)($kpi['pending_count'] ?? 0);
$inProgressCount = (int)($kpi['in_progress_count'] ?? 0);
$completedCount  = (int)($kpi['completed_count'] ?? 0);
$avgResponse     = $kpi['avg_response_hours'] ?? null;
$avgCompletion   = $kpi['avg_completion_hours'] ?? null;

$completionRate = $totalRequests > 0 ? round(($completedCount / $totalRequests) * 100, 1) : 0;

// Category breakdown
$byCategory = $db->fetchAll("SELECT * FROM vw_requests_by_category");

// Priority breakdown (active requests only)
$byPriority = $db->fetchAll(
    "SELECT COALESCE(priority, 'Unset') AS priority, COUNT(*) AS count
     FROM requests WHERE is_archived = 0
     GROUP BY priority
     ORDER BY FIELD(priority, 'High','Medium','Low','Unset')"
);
$priorityTotal = array_sum(array_column($byPriority, 'count')) ?: 1;

// Daily submissions — last 14 days
$daily = $db->fetchAll(
    "SELECT DATE(created_at) AS day, COUNT(*) AS count
     FROM requests
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(created_at)
     ORDER BY day ASC"
);
// Fill missing days with 0
$dailyMap = [];
foreach ($daily as $d) { $dailyMap[$d['day']] = (int)$d['count']; }
$dailySeries = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $dailySeries[] = ['day' => $day, 'count' => $dailyMap[$day] ?? 0];
}
$dailyMax = max(array_column($dailySeries, 'count')) ?: 1;

// Top staff by completed tasks
$topStaff = $db->fetchAll(
    "SELECT * FROM vw_staff_workload ORDER BY completed_tasks DESC, active_tasks ASC LIMIT 5"
);

// Recently completed
$recentCompleted = $db->fetchAll(
    "SELECT r.id, r.title, r.completed_at, c.name AS category_name, u.name AS assignee
     FROM requests r
     JOIN categories c ON r.category_id = c.id
     LEFT JOIN users u ON r.assigned_to = u.id
     WHERE r.status = 'Completed' AND r.is_archived = 0
     ORDER BY r.completed_at DESC LIMIT 5"
);

include __DIR__ . '/../frontend/includes/admin-header.php';
?>

<!-- Top KPIs -->
<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon blue">📋</div><div class="stat-info"><h4>Total requests</h4><div class="value"><?= $totalRequests ?></div></div></div>
    <div class="stat-card"><div class="stat-icon green">✅</div><div class="stat-info"><h4>Completion rate</h4><div class="value"><?= $completionRate ?>%</div></div></div>
    <div class="stat-card"><div class="stat-icon yellow">⏱</div><div class="stat-info"><h4>Avg response</h4><div class="value" style="font-size:1.3rem"><?= $avgResponse !== null ? $avgResponse . ' hrs' : 'N/A' ?></div></div></div>
    <div class="stat-card"><div class="stat-icon blue">🏁</div><div class="stat-info"><h4>Avg completion</h4><div class="value" style="font-size:1.3rem"><?= $avgCompletion !== null ? $avgCompletion . ' hrs' : 'N/A' ?></div></div></div>
</div>

<!-- Status distribution bar -->
<div class="card">
    <div class="card-header"><h3>Status distribution</h3></div>
    <div class="card-body">
        <?php $statusTotal = max(1, $pendingCount + $inProgressCount + $completedCount); ?>
        <div style="display:flex;height:28px;border-radius:8px;overflow:hidden;margin-bottom:12px;background:var(--border);">
            <div style="width:<?= ($pendingCount / $statusTotal) * 100 ?>%;background:#d97706;" title="Pending: <?= $pendingCount ?>"></div>
            <div style="width:<?= ($inProgressCount / $statusTotal) * 100 ?>%;background:#1a56db;" title="In Progress: <?= $inProgressCount ?>"></div>
            <div style="width:<?= ($completedCount / $statusTotal) * 100 ?>%;background:#059669;" title="Completed: <?= $completedCount ?>"></div>
        </div>
        <div style="display:flex;gap:20px;font-size:13px;flex-wrap:wrap;">
            <span><span style="display:inline-block;width:10px;height:10px;background:#d97706;border-radius:2px;"></span> Pending: <strong><?= $pendingCount ?></strong></span>
            <span><span style="display:inline-block;width:10px;height:10px;background:#1a56db;border-radius:2px;"></span> In Progress: <strong><?= $inProgressCount ?></strong></span>
            <span><span style="display:inline-block;width:10px;height:10px;background:#059669;border-radius:2px;"></span> Completed: <strong><?= $completedCount ?></strong></span>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(360px, 1fr));gap:20px;margin-top:20px;">

    <!-- Requests by category -->
    <div class="card">
        <div class="card-header"><h3>Requests by category</h3></div>
        <div class="card-body">
            <?php if (empty($byCategory)): ?>
                <p class="text-muted">No data yet.</p>
            <?php else: ?>
                <?php
                $catMax = max(array_column($byCategory, 'request_count')) ?: 1;
                foreach ($byCategory as $row):
                    $pct = ($row['request_count'] / $catMax) * 100;
                ?>
                <div style="margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                        <span><?= htmlspecialchars($row['category_name']) ?></span>
                        <span class="text-muted"><?= $row['request_count'] ?> total · <?= $row['completed'] ?> done</span>
                    </div>
                    <div style="height:10px;background:var(--border);border-radius:5px;overflow:hidden;">
                        <div style="width:<?= $pct ?>%;height:100%;background:linear-gradient(90deg,#3b82f6,#8b5cf6);border-radius:5px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Priority distribution -->
    <div class="card">
        <div class="card-header"><h3>Priority distribution</h3></div>
        <div class="card-body">
            <?php foreach ($byPriority as $row):
                $pct = ($row['count'] / $priorityTotal) * 100;
                $color = match($row['priority']) {
                    'High' => '#dc2626', 'Medium' => '#d97706', 'Low' => '#059669', default => '#9ca3af'
                };
            ?>
            <div style="margin-bottom:14px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                    <span><?= htmlspecialchars($row['priority']) ?></span>
                    <span class="text-muted"><?= $row['count'] ?> (<?= round($pct, 1) ?>%)</span>
                </div>
                <div style="height:10px;background:var(--border);border-radius:5px;overflow:hidden;">
                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:5px;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Daily submissions (last 14 days) -->
<div class="card" style="margin-top:20px;">
    <div class="card-header"><h3>Submissions — last 14 days</h3></div>
    <div class="card-body">
        <div style="display:flex;align-items:flex-end;gap:6px;height:160px;padding:8px 0;">
            <?php foreach ($dailySeries as $d):
                $height = ($d['count'] / $dailyMax) * 100;
            ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;">
                    <div style="flex:1;display:flex;align-items:flex-end;width:100%;">
                        <div title="<?= $d['day'] ?>: <?= $d['count'] ?>"
                             style="width:100%;height:<?= max(2, $height) ?>%;background:linear-gradient(180deg,#60a5fa,#3b82f6);border-radius:4px 4px 0 0;transition:all 0.3s;"></div>
                    </div>
                    <div class="text-muted" style="font-size:10px;"><?= date('d', strtotime($d['day'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-muted" style="font-size:12px;margin-top:8px;text-align:center;">
            <?= date('M j', strtotime($dailySeries[0]['day'])) ?> — <?= date('M j', strtotime(end($dailySeries)['day'])) ?>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(360px, 1fr));gap:20px;margin-top:20px;">

    <!-- Top staff -->
    <div class="card">
        <div class="card-header"><h3>Top performers</h3></div>
        <?php if (empty($topStaff)): ?>
            <div class="card-body"><p class="text-muted">No staff data yet.</p></div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead><tr><th>Staff</th><th>Completed</th><th>Active</th></tr></thead>
                    <tbody>
                    <?php foreach ($topStaff as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                            <td><span class="badge badge-completed"><?= $s['completed_tasks'] ?></span></td>
                            <td><span class="badge badge-progress"><?= $s['active_tasks'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recently completed -->
    <div class="card">
        <div class="card-header"><h3>Recently completed</h3></div>
        <?php if (empty($recentCompleted)): ?>
            <div class="card-body"><p class="text-muted">No completed requests yet.</p></div>
        <?php else: ?>
            <div style="padding:8px 0;">
                <?php foreach ($recentCompleted as $r): ?>
                    <div style="padding:12px 16px;border-bottom:1px solid var(--border);">
                        <div style="font-size:14px;font-weight:500;"><?= htmlspecialchars($r['title']) ?></div>
                        <div class="text-muted" style="font-size:12px;margin-top:2px;">
                            <?= htmlspecialchars($r['category_name']) ?>
                            <?= $r['assignee'] ? ' · by ' . htmlspecialchars($r['assignee']) : '' ?>
                            · <?= timeAgo($r['completed_at']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../frontend/includes/footer.php'; ?>
