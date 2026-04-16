<?php
require_once __DIR__ . '/../backend/includes/Auth.php';
require_once __DIR__ . '/../backend/includes/helpers.php';
Auth::requireRole('student');

$db = Database::getInstance();
$userId = Auth::getUserId();
$pageTitle = 'Notifications';

// Handle actions (mark read, mark all read, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        redirect('notifications.php', 'Invalid request. Please try again.', 'error');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->execute(
                "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?",
                [$id, $userId]
            );
        }
        redirect('notifications.php');
    } elseif ($action === 'mark_all_read') {
        $db->execute(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
        redirect('notifications.php', 'All notifications marked as read.', 'success');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->execute(
                "DELETE FROM notifications WHERE id = ? AND user_id = ?",
                [$id, $userId]
            );
        }
        redirect('notifications.php', 'Notification deleted.', 'success');
    }
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$where = "user_id = ?";
$params = [$userId];
if ($filter === 'unread') {
    $where .= " AND is_read = 0";
}

$notifications = $db->fetchAll(
    "SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT 100",
    $params
);

$unreadTotal = (int)($db->fetchOne(
    "SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = 0",
    [$userId]
)['count'] ?? 0);

$flash = getFlash();

include __DIR__ . '/../frontend/includes/student-header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<div class="filters-bar">
    <div style="display:flex;gap:8px;">
        <a href="?filter=all" class="btn btn-<?= $filter === 'all' ? 'primary' : 'secondary' ?> btn-sm">All</a>
        <a href="?filter=unread" class="btn btn-<?= $filter === 'unread' ? 'primary' : 'secondary' ?> btn-sm">
            Unread <?= $unreadTotal > 0 ? "($unreadTotal)" : '' ?>
        </a>
    </div>
    <?php if ($unreadTotal > 0): ?>
        <form method="POST" action="" style="margin-left:auto;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-secondary btn-sm">Mark all as read</button>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <div class="icon">🔔</div>
            <h3>No notifications</h3>
            <p><?= $filter === 'unread' ? "You're all caught up!" : "You'll see updates about your requests here." ?></p>
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;">
            <?php foreach ($notifications as $notif): ?>
                <div style="display:flex;gap:14px;padding:16px;border-bottom:1px solid var(--border, #e5e7eb);<?= $notif['is_read'] ? '' : 'background:#f0f9ff;' ?>">
                    <div style="font-size:24px;flex-shrink:0;">
                        <?php
                        echo match($notif['type']) {
                            'status_change' => '🔄',
                            'assignment'    => '👤',
                            'reopen'        => '↩️',
                            'system'        => '⚙️',
                            default         => '📢',
                        };
                        ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;">
                            <h4 style="margin:0;font-size:14px;font-weight:600;">
                                <?= htmlspecialchars($notif['title']) ?>
                                <?php if (!$notif['is_read']): ?>
                                    <span style="display:inline-block;width:8px;height:8px;background:#3b82f6;border-radius:50%;margin-left:6px;"></span>
                                <?php endif; ?>
                            </h4>
                            <span class="text-muted" style="font-size:12px;white-space:nowrap;"><?= timeAgo($notif['created_at']) ?></span>
                        </div>
                        <p style="margin:4px 0 8px;font-size:13px;color:var(--text-muted, #6b7280);">
                            <?= htmlspecialchars($notif['message']) ?>
                        </p>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <?php if ($notif['request_id']): ?>
                                <a href="view-request.php?id=<?= $notif['request_id'] ?>" class="btn btn-outline btn-sm">View request</a>
                            <?php endif; ?>
                            <?php if (!$notif['is_read']): ?>
                                <form method="POST" action="" style="display:inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="id" value="<?= $notif['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">Mark as read</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this notification?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $notif['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card-footer text-muted" style="font-size:13px;padding:12px 16px;">
            Showing <?= count($notifications) ?> notification<?= count($notifications) !== 1 ? 's' : '' ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../frontend/includes/footer.php'; ?>
