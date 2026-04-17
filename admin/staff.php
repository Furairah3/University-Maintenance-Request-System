<?php
require_once __DIR__ . '/../backend/includes/Auth.php';
require_once __DIR__ . '/../backend/includes/helpers.php';
Auth::requireRole('admin');

$db = Database::getInstance();
$pageTitle = 'Staff Management';
$createError = '';
$createPrefillName = '';
$createPrefillEmail = '';
$createPrefillCats = [];

// Pull all categories for specialty pickers
$categories = $db->fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY display_order");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        redirect('staff.php', 'Invalid request. Please try again.', 'error');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'create_staff') {
        $name = sanitize($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $specialties = array_map('intval', $_POST['specialties'] ?? []);

        if (strlen($name) < 2) {
            $createError = 'Name is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $createError = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $createError = 'Password must be at least 8 characters with one uppercase letter and one number.';
        } else {
            $auth = new Auth();
            $result = $auth->createStaffAccount($name, $email, $password);
            if ($result['success']) {
                $newId = (int)$result['user_id'];
                foreach ($specialties as $catId) {
                    $db->execute(
                        "INSERT IGNORE INTO staff_categories (user_id, category_id) VALUES (?, ?)",
                        [$newId, $catId]
                    );
                }
                redirect('staff.php', 'Staff account created.', 'success');
            } else {
                $createError = $result['error'];
                $createPrefillName = $name;
                $createPrefillEmail = $email;
                $createPrefillCats = $specialties;
            }
        }
    }
    elseif ($action === 'toggle_active') {
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $db->execute("UPDATE users SET is_active = NOT is_active WHERE id = ? AND role = 'staff'", [$staffId]);
        Logger::activity('toggle_staff_active', 'user', $staffId);
        redirect('staff.php', 'Staff status updated.', 'success');
    }
    elseif ($action === 'reset_password') {
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';
        if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            redirect('staff.php', 'Password must be at least 8 characters with one uppercase and one number.', 'error');
        }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $db->execute("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'staff'", [$hash, $staffId]);
        Logger::info('Staff password reset by admin', ['staff_id' => $staffId, 'by' => Auth::getUserId()]);
        Logger::activity('reset_staff_password', 'user', $staffId);
        redirect('staff.php', 'Password reset successfully.', 'success');
    }
    elseif ($action === 'update_specialties') {
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $specialties = array_map('intval', $_POST['specialties'] ?? []);

        $db->beginTransaction();
        try {
            $db->execute("DELETE FROM staff_categories WHERE user_id = ?", [$staffId]);
            foreach ($specialties as $catId) {
                $db->execute(
                    "INSERT IGNORE INTO staff_categories (user_id, category_id) VALUES (?, ?)",
                    [$staffId, $catId]
                );
            }
            $db->commit();
            Logger::activity('update_staff_specialties', 'user', $staffId, ['count' => count($specialties)]);
            redirect('staff.php', 'Specialties updated.', 'success');
        } catch (Exception $e) {
            $db->rollBack();
            redirect('staff.php', 'Failed to update specialties.', 'error');
        }
    }
}

// Staff list with workload (direct query, no view needed)
$staff = $db->fetchAll(
    "SELECT u.id, u.name, u.email, u.is_active, u.last_login, u.created_at,
            COUNT(CASE WHEN r.status IN ('Pending','In Progress') THEN 1 END) AS active_tasks,
            COUNT(CASE WHEN r.status = 'Completed' THEN 1 END) AS completed_tasks,
            COUNT(r.id) AS total_tasks
     FROM users u
     LEFT JOIN requests r ON u.id = r.assigned_to AND r.is_archived = 0
     WHERE u.role = 'staff'
     GROUP BY u.id, u.name, u.email, u.is_active, u.last_login, u.created_at
     ORDER BY u.is_active DESC, u.name ASC"
);

// Build specialties map: user_id => [ {id, name}, ... ]
$specialtiesMap = [];
if (!empty($staff)) {
    $staffIds = array_column($staff, 'id');
    $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
    $rows = $db->fetchAll(
        "SELECT sc.user_id, c.id, c.name
         FROM staff_categories sc
         JOIN categories c ON c.id = sc.category_id
         WHERE sc.user_id IN ($placeholders)
         ORDER BY c.display_order",
        $staffIds
    );
    foreach ($rows as $r) {
        $specialtiesMap[$r['user_id']][] = ['id' => (int)$r['id'], 'name' => $r['name']];
    }
}

$totalStaff = count($staff);
$activeStaff = count(array_filter($staff, fn($s) => (int)$s['is_active'] === 1));
$totalActiveTasks = array_sum(array_column($staff, 'active_tasks'));

include __DIR__ . '/../frontend/includes/admin-header.php';
$flash = getFlash();
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<!-- Summary -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <div class="stat-card"><div class="stat-icon blue">👥</div><div class="stat-info"><h4>Total staff</h4><div class="value"><?= $totalStaff ?></div></div></div>
    <div class="stat-card"><div class="stat-icon green">✅</div><div class="stat-info"><h4>Active</h4><div class="value"><?= $activeStaff ?></div></div></div>
    <div class="stat-card"><div class="stat-icon yellow">📋</div><div class="stat-info"><h4>Open tasks</h4><div class="value"><?= $totalActiveTasks ?></div></div></div>
</div>

<!-- Actions -->
<div style="margin-bottom:16px;">
    <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('show')">+ Create staff account</button>
</div>

<!-- Staff Table -->
<div class="card">
    <div class="card-header"><h3>Maintenance staff</h3></div>
    <?php if (empty($staff)): ?>
        <div class="empty-state"><div class="icon">👥</div><h3>No staff yet</h3><p>Create your first staff account to start assigning tasks.</p></div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Specialties</th>
                        <th>Active</th>
                        <th>Done</th>
                        <th>Status</th>
                        <th>Last login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($staff as $s):
                    $specs = $specialtiesMap[$s['id']] ?? [];
                    $specIds = array_column($specs, 'id');
                ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($s['name']) ?></strong><br>
                            <span class="text-muted" style="font-size:12px;"><?= htmlspecialchars($s['email']) ?></span>
                        </td>
                        <td>
                            <?php if (empty($specs)): ?>
                                <span class="text-muted" style="font-size:12px;">— none —</span>
                            <?php else: ?>
                                <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                    <?php foreach ($specs as $sp): ?>
                                        <span class="badge badge-progress" style="font-size:11px;"><?= htmlspecialchars($sp['name']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-progress"><?= $s['active_tasks'] ?></span></td>
                        <td class="text-muted"><?= $s['completed_tasks'] ?></td>
                        <td>
                            <?php if ($s['is_active']): ?>
                                <span class="badge badge-completed">Active</span>
                            <?php else: ?>
                                <span class="badge badge-none">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted" style="font-size:13px"><?= $s['last_login'] ? timeAgo($s['last_login']) : 'Never' ?></td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap;">
                            <button class="btn btn-outline btn-sm"
                                    onclick='openSpecialtiesModal(<?= $s['id'] ?>, <?= htmlspecialchars(json_encode($s['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($specIds), ENT_QUOTES) ?>)'>
                                Edit specialties
                            </button>
                            <button class="btn btn-outline btn-sm"
                                    onclick='openResetModal(<?= $s['id'] ?>, <?= htmlspecialchars(json_encode($s['name']), ENT_QUOTES) ?>)'>
                                Reset password
                            </button>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('<?= $s['is_active'] ? 'Deactivate' : 'Activate' ?> this staff account?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-<?= $s['is_active'] ? 'secondary' : 'primary' ?> btn-sm">
                                    <?= $s['is_active'] ? 'Deactivate' : 'Activate' ?>
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
<div class="modal-overlay <?= $createError ? 'show' : '' ?>" id="createModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Create Staff Account</h3>
            <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body">
            <?php if ($createError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($createError) ?></div>
            <?php endif; ?>
            <form method="POST" id="createStaffForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_staff">
                <div class="form-group">
                    <label class="form-label">Full name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" required minlength="2" maxlength="100" value="<?= htmlspecialchars($createPrefillName) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($createPrefillEmail) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Temporary password <span class="required">*</span></label>
                    <input type="text" name="password" class="form-control" required minlength="8">
                    <small class="text-muted" style="font-size:12px;">At least 8 chars, one uppercase, one number. Share with staff securely.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Specialties</label>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        <?php foreach ($categories as $cat): ?>
                            <label style="display:flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid var(--border);border-radius:6px;cursor:pointer;">
                                <input type="checkbox" name="specialties[]" value="<?= $cat['id'] ?>" <?= in_array($cat['id'], $createPrefillCats) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted" style="font-size:12px;">Which categories is this staff member trained for?</small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('createModal').classList.remove('show')">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('createStaffForm').submit()">Create account</button>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Reset Password</h3>
            <button class="modal-close" onclick="document.getElementById('resetModal').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom:12px;">Resetting password for <strong id="resetStaffName"></strong>.</p>
            <form method="POST" id="resetForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="staff_id" id="resetStaffId">
                <div class="form-group">
                    <label class="form-label">New password <span class="required">*</span></label>
                    <input type="text" name="new_password" class="form-control" required minlength="8">
                    <small class="text-muted" style="font-size:12px;">At least 8 chars, one uppercase, one number.</small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('resetModal').classList.remove('show')">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('resetForm').submit()">Reset password</button>
        </div>
    </div>
</div>

<!-- Specialties Modal -->
<div class="modal-overlay" id="specialtiesModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Edit Specialties</h3>
            <button class="modal-close" onclick="document.getElementById('specialtiesModal').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom:12px;">Categories <strong id="specStaffName"></strong> can handle.</p>
            <form method="POST" id="specForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_specialties">
                <input type="hidden" name="staff_id" id="specStaffId">
                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                    <?php foreach ($categories as $cat): ?>
                        <label style="display:flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid var(--border);border-radius:6px;cursor:pointer;">
                            <input type="checkbox" name="specialties[]" value="<?= $cat['id'] ?>" data-cat-id="<?= $cat['id'] ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="document.getElementById('specialtiesModal').classList.remove('show')">Cancel</button>
            <button class="btn btn-primary" onclick="document.getElementById('specForm').submit()">Save</button>
        </div>
    </div>
</div>

<script>
function openResetModal(id, name) {
    document.getElementById('resetStaffId').value = id;
    document.getElementById('resetStaffName').textContent = name;
    document.getElementById('resetModal').classList.add('show');
}
function openSpecialtiesModal(id, name, currentIds) {
    document.getElementById('specStaffId').value = id;
    document.getElementById('specStaffName').textContent = name;
    const set = new Set((currentIds || []).map(String));
    document.querySelectorAll('#specForm input[data-cat-id]').forEach(cb => {
        cb.checked = set.has(cb.dataset.catId);
    });
    document.getElementById('specialtiesModal').classList.add('show');
}
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); });
});
</script>

<?php include __DIR__ . '/../frontend/includes/footer.php'; ?>
