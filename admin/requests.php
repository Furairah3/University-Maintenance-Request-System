<?php
require_once __DIR__ . '/../backend/includes/Auth.php';
require_once __DIR__ . '/../backend/includes/helpers.php';
Auth::requireRole('admin');

$db = Database::getInstance();
$pageTitle = 'All Requests';

// Filters
$status      = $_GET['status'] ?? '';
$category    = $_GET['category'] ?? '';
$priority    = $_GET['priority'] ?? '';
$search      = trim($_GET['search'] ?? '');
$showArchive = isset($_GET['archived']) && $_GET['archived'] === '1';

$where = $showArchive ? "r.is_archived = 1" : "r.is_archived = 0";
$params = [];
if ($status && in_array($status, ['Pending','In Progress','Completed'])) { $where .= " AND r.status = ?"; $params[] = $status; }
if ($category) { $where .= " AND r.category_id = ?"; $params[] = (int)$category; }
if ($priority && in_array($priority, ['High','Medium','Low'])) { $where .= " AND r.priority = ?"; $params[] = $priority; }
if ($search) { $where .= " AND (r.title LIKE ? OR creator.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        redirect('requests.php', 'Invalid request. Please try again.', 'error');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        $staffId = (int)($_POST['staff_id'] ?? 0);
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
            redirect('requests.php', 'Request assigned successfully.', 'success');
        } catch (Exception $e) {
            $db->rollBack();
            redirect('requests.php', 'Failed to assign request.', 'error');
        }
    }
    elseif ($action === 'archive') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        $db->execute("UPDATE requests SET is_archived = 1 WHERE id = ?", [$reqId]);
        Logger::activity('archive_request', 'request', $reqId);
        redirect('requests.php', 'Request archived.', 'success');
    }
    elseif ($action === 'unarchive') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        $db->execute("UPDATE requests SET is_archived = 0 WHERE id = ?", [$reqId]);
        Logger::activity('unarchive_request', 'request', $reqId);
        redirect('requests.php?archived=1', 'Request restored.', 'success');
    }
}

$requests = $db->fetchAll(
    "SELECT r.*, c.name as category_name, creator.name as creator_name,
            creator.email as creator_email, COALESCE(assignee.name, 'Unassigned') as assignee_name
     FROM requests r
     JOIN categories c ON r.category_id = c.id
     JOIN users creator ON r.created_by = creator.id
     LEFT JOIN users assignee ON r.assigned_to = assignee.id
     WHERE $where ORDER BY r.created_at DESC LIMIT 200", $params
);

$categories = $db->fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY display_order");
$staffList = $db->fetchAll(
    "SELECT u.id, u.name,
            COALESCE(GROUP_CONCAT(c.id ORDER BY c.display_order), '') AS category_ids,
            COALESCE(GROUP_CONCAT(c.name ORDER BY c.display_order SEPARATOR ', '), '') AS category_names
     FROM users u
     LEFT JOIN staff_categories sc ON sc.user_id = u.id
     LEFT JOIN categories c ON c.id = sc.category_id AND c.is_active = 1
     WHERE u.role='staff' AND u.is_active=1
     GROUP BY u.id, u.name
     ORDER BY u.name"
);

include __DIR__ . '/../frontend/includes/admin-header.php';
$flash = getFlash();
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<div style="display:flex;gap:8px;margin-bottom:16px;">
    <a href="?archived=0" class="btn btn-<?= !$showArchive ? 'primary' : 'secondary' ?> btn-sm">Active</a>
    <a href="?archived=1" class="btn btn-<?= $showArchive ? 'primary' : 'secondary' ?> btn-sm">Archived</a>
</div>

<form class="filters-bar" method="GET">
    <input type="hidden" name="archived" value="<?= $showArchive ? '1' : '0' ?>">
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
    <a href="requests.php" class="btn btn-secondary btn-sm">Clear</a>
</form>

<div class="card">
    <div class="card-header">
        <h3><?= $showArchive ? 'Archived' : 'All' ?> requests (<?= count($requests) ?>)</h3>
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
                        <td><span class="request-title" style="cursor:pointer" onclick="openAssignModal(<?= htmlspecialchars(json_encode($req)) ?>)"><?= htmlspecialchars($req['title']) ?></span></td>
                        <td style="font-size:13px"><?= htmlspecialchars($req['creator_name']) ?></td>
                        <td><?= htmlspecialchars($req['category_name']) ?></td>
                        <td><?= statusBadge($req['status']) ?></td>
                        <td><?= priorityBadge($req['priority']) ?></td>
                        <td class="text-muted" style="font-size:13px"><?= htmlspecialchars($req['assignee_name']) ?></td>
                        <td class="text-muted" style="font-size:13px"><?= timeAgo($req['created_at']) ?></td>
                        <td style="display:flex;gap:6px;">
                            <button class="btn btn-outline btn-sm" onclick="openAssignModal(<?= htmlspecialchars(json_encode($req)) ?>)">View</button>
                            <?php if (!$showArchive): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Archive this request?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="archive">
                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">Archive</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="unarchive">
                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">Restore</button>
                                </form>
                            <?php endif; ?>
                        </td>
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
            <hr style="margin:16px 0;border:none;border-top:1px solid var(--border)">
            <form method="POST" id="assignForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="request_id" id="modalReqId">
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
                        <select name="staff_id" class="form-control" id="modalStaffSelect" required>
                            <option value="">-- Select staff --</option>
                            <?php foreach ($staffList as $s): ?>
                                <option value="<?= $s['id'] ?>" data-categories="<?= htmlspecialchars($s['category_ids']) ?>" data-name="<?= htmlspecialchars($s['name']) ?>" data-specs="<?= htmlspecialchars($s['category_names']) ?>">
                                    <?= htmlspecialchars($s['name']) ?><?= $s['category_names'] ? ' — ' . htmlspecialchars($s['category_names']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted" style="font-size:12px;display:block;margin-top:4px;">★ = specialty matches this request's category.</small>
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
function rankStaffByCategory(categoryId) {
    const select = document.getElementById('modalStaffSelect');
    if (!select) return;
    const placeholder = select.querySelector('option[value=""]');
    const options = Array.from(select.querySelectorAll('option[value]:not([value=""])'));
    const matches = [], others = [];
    options.forEach(opt => {
        const cats = (opt.dataset.categories || '').split(',').filter(Boolean);
        const isMatch = cats.includes(String(categoryId));
        const name = opt.dataset.name;
        const specs = opt.dataset.specs;
        opt.textContent = (isMatch ? '★ ' : '') + name + (specs ? ' — ' + specs : '');
        (isMatch ? matches : others).push(opt);
    });
    select.innerHTML = '';
    if (placeholder) select.appendChild(placeholder);
    matches.concat(others).forEach(o => select.appendChild(o));
}

function openAssignModal(req) {
    document.getElementById('modalTitle').textContent = req.title;
    document.getElementById('modalReqId').value = req.id;
    document.getElementById('modalPriority').value = req.priority || '';
    rankStaffByCategory(req.category_id);
    document.getElementById('modalDetails').innerHTML = `
        <p><strong>Student:</strong> ${req.creator_name} (${req.creator_email})</p>
        <p><strong>Category:</strong> ${req.category_name}</p>
        <p><strong>Status:</strong> ${req.status}</p>
        <p><strong>Location:</strong> ${req.location || 'N/A'}, Room ${req.room_number || 'N/A'}</p>
        <p style="margin-top:8px"><strong>Description:</strong></p>
        <p style="color:var(--text-secondary);line-height:1.6">${req.description}</p>
        ${req.image_url ? '<div class="image-preview mt-1"><img src="' + '<?= APP_URL ?>/' + req.image_url + '"></div>' : ''}
    `;
    document.getElementById('assignModal').classList.add('show');
}
function closeModal() { document.getElementById('assignModal').classList.remove('show'); }
document.getElementById('assignModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
</script>

<?php include __DIR__ . '/../frontend/includes/footer.php'; ?>
