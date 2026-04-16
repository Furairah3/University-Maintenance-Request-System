<?php
require_once __DIR__ . '/../backend/includes/Auth.php';
require_once __DIR__ . '/../backend/includes/helpers.php';
Auth::requireRole('admin');

$db = Database::getInstance();
$pageTitle = 'Categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        redirect('categories.php', 'Invalid request. Please try again.', 'error');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        if (strlen($name) < 2) {
            redirect('categories.php', 'Category name is required.', 'error');
        }
        $existing = $db->fetchOne("SELECT id FROM categories WHERE name = ?", [$name]);
        if ($existing) {
            redirect('categories.php', 'A category with that name already exists.', 'error');
        }
        $order = $db->fetchOne("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM categories");
        $db->insert(
            "INSERT INTO categories (name, description, display_order) VALUES (?, ?, ?)",
            [$name, $description ?: null, $order['next_order']]
        );
        Logger::activity('create_category', 'category', 0, ['name' => $name]);
        redirect('categories.php', 'Category created.', 'success');
    }
    elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        if (strlen($name) < 2) {
            redirect('categories.php', 'Category name is required.', 'error');
        }
        $db->execute(
            "UPDATE categories SET name = ?, description = ? WHERE id = ?",
            [$name, $description ?: null, $id]
        );
        Logger::activity('update_category', 'category', $id);
        redirect('categories.php', 'Category updated.', 'success');
    }
    elseif ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        $db->execute("UPDATE categories SET is_active = NOT is_active WHERE id = ?", [$id]);
        Logger::activity('toggle_category', 'category', $id);
        redirect('categories.php', 'Category status updated.', 'success');
    }
}

$categories = $db->fetchAll(
    "SELECT c.*, COUNT(r.id) AS request_count
     FROM categories c
     LEFT JOIN requests r ON r.category_id = c.id AND r.is_archived = 0
     GROUP BY c.id
     ORDER BY c.display_order ASC, c.name ASC"
);

include __DIR__ . '/../frontend/includes/admin-header.php';
$flash = getFlash();
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<div style="margin-bottom:16px;">
    <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('show')">+ New category</button>
</div>

<div class="card">
    <div class="card-header"><h3>Maintenance categories</h3></div>
    <?php if (empty($categories)): ?>
        <div class="empty-state"><div class="icon">🏷</div><h3>No categories</h3><p>Create a category for students to classify their requests.</p></div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr><th>Order</th><th>Name</th><th>Description</th><th>Active requests</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td class="text-muted"><?= $cat['display_order'] ?></td>
                        <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                        <td class="text-muted" style="font-size:13px;"><?= htmlspecialchars($cat['description'] ?? '—') ?></td>
                        <td><?= $cat['request_count'] ?></td>
                        <td>
                            <?php if ($cat['is_active']): ?>
                                <span class="badge badge-completed">Active</span>
                            <?php else: ?>
                                <span class="badge badge-none">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap;">
                            <button class="btn btn-outline btn-sm"
                                    onclick='openEditModal(<?= json_encode($cat) ?>)'>
                                Edit
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Toggle active status?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">
                                    <?= $cat['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Create Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <div class="modal-header">
            <h3>New category</h3>
            <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="createCatForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label class="form-label">Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" required minlength="2" maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" maxlength="255">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('createModal').classList.remove('show')">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('createCatForm').submit()">Create</button>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Edit category</h3>
            <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="editCatForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editCatId">
                <div class="form-group">
                    <label class="form-label">Name <span class="required">*</span></label>
                    <input type="text" name="name" id="editCatName" class="form-control" required minlength="2" maxlength="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" id="editCatDescription" class="form-control" maxlength="255">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('editModal').classList.remove('show')">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('editCatForm').submit()">Save changes</button>
        </div>
    </div>
</div>

<script>
function openEditModal(cat) {
    document.getElementById('editCatId').value = cat.id;
    document.getElementById('editCatName').value = cat.name;
    document.getElementById('editCatDescription').value = cat.description || '';
    document.getElementById('editModal').classList.add('show');
}
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); });
});
</script>

<?php include __DIR__ . '/../frontend/includes/footer.php'; ?>
