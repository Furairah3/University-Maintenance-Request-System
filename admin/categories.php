<?php
require_once '../includes/auth.php';
requireLogin('admin');
$activePage = 'categories';
$db = getDB();

$error = '';

// Add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $error = 'Category name is required.';
    } elseif (strlen($name) > 50) {
        $error = 'Category name must be 50 characters or fewer.';
    } else {
        $exists = $db->prepare("SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(?)");
        $exists->execute([$name]);
        if ($exists->fetchColumn() > 0) {
            $error = 'A category with that name already exists.';
        } else {
            $db->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$name]);
            setFlash('success', "Category \"{$name}\" added.");
            redirect(SITE_URL . '/admin/categories.php');
        }
    }
}

// Rename category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename'])) {
    $cid     = (int)($_POST['id'] ?? 0);
    $newName = trim($_POST['new_name'] ?? '');

    if ($cid <= 0 || $newName === '') {
        $error = 'Invalid rename request.';
    } elseif (strlen($newName) > 50) {
        $error = 'Category name must be 50 characters or fewer.';
    } else {
        $cur = $db->prepare("SELECT name FROM categories WHERE id = ?");
        $cur->execute([$cid]);
        $old = $cur->fetchColumn();
        if (!$old) {
            $error = 'Category not found.';
        } else {
            $exists = $db->prepare("SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(?) AND id != ?");
            $exists->execute([$newName, $cid]);
            if ($exists->fetchColumn() > 0) {
                $error = 'Another category with that name already exists.';
            } else {
                $db->prepare("UPDATE categories SET name = ? WHERE id = ?")->execute([$newName, $cid]);
                // Update requests that used the old category name so filters/reports stay consistent
                $db->prepare("UPDATE requests SET category = ? WHERE category = ?")->execute([$newName, $old]);
                setFlash('success', "Renamed \"{$old}\" to \"{$newName}\".");
                redirect(SITE_URL . '/admin/categories.php');
            }
        }
    }
}

// Delete category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $cid = (int)($_POST['id'] ?? 0);
    if ($cid > 0) {
        $cur = $db->prepare("SELECT name FROM categories WHERE id = ?");
        $cur->execute([$cid]);
        $name = $cur->fetchColumn();
        if ($name) {
            $used = $db->prepare("SELECT COUNT(*) FROM requests WHERE category = ?");
            $used->execute([$name]);
            $count = (int)$used->fetchColumn();
            if ($count > 0) {
                $error = "Cannot delete \"{$name}\" — {$count} existing request"
                       . ($count === 1 ? '' : 's') . " use this category. Reassign them first.";
            } else {
                $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$cid]);
                setFlash('success', "Category \"{$name}\" deleted.");
                redirect(SITE_URL . '/admin/categories.php');
            }
        }
    }
}

$flash = getFlash('success');

// Fetch categories with usage count
$rows = $db->query("
    SELECT c.id, c.name, COUNT(r.id) AS usage_count
    FROM categories c
    LEFT JOIN requests r ON r.category = c.name
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Categories — HostelIQ Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div>
        <div class="page-title">Request Categories</div>
        <div class="page-subtitle">Manage the categories students pick from when reporting issues</div>
      </div>
      <a href="dashboard.php" class="btn btn-outline">← Dashboard</a>
    </div>
    <div class="page-body">

      <?php if ($flash): ?><div class="alert alert-success" data-auto-dismiss>✓ <?=sanitize($flash)?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger">⚠ <?=sanitize($error)?></div><?php endif; ?>

      <div class="request-layout">

        <!-- EXISTING CATEGORIES -->
        <div class="card request-form">
          <div class="card-header">
            <div class="card-title">Existing Categories</div>
            <span class="card-subtitle"><?=count($rows)?> total</span>
          </div>
          <div class="card-body" style="padding: 0;">
            <?php if (empty($rows)): ?>
              <div class="empty-state">
                <div class="empty-icon">📁</div>
                <div class="empty-title">No categories yet</div>
                <p style="font-size:12px;">Add your first category using the form.</p>
              </div>
            <?php else: ?>
            <div class="table-wrapper">
              <table class="table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Requests</th>
                    <th style="width:200px;text-align:right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $c): ?>
                <tr>
                  <td>
                    <form method="POST" style="display:flex;gap:8px;align-items:center;">
                      <input type="hidden" name="id" value="<?=$c['id']?>">
                      <input type="text" name="new_name" class="form-control"
                        value="<?=sanitize($c['name'])?>" maxlength="50"
                        style="padding:6px 10px;font-size:13px;min-width:0;">
                      <button type="submit" name="rename" value="1" class="btn btn-outline btn-sm">Rename</button>
                    </form>
                  </td>
                  <td>
                    <span class="badge badge-secondary"><?=$c['usage_count']?></span>
                  </td>
                  <td style="text-align:right;">
                    <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Delete category <?=sanitize($c['name'])?>? This cannot be undone.');">
                      <input type="hidden" name="id" value="<?=$c['id']?>">
                      <button type="submit" name="delete" value="1" class="btn btn-danger btn-sm"
                        <?=$c['usage_count']>0?'disabled title="Reassign existing requests before deleting"':''?>>
                        🗑 Delete
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
        </div>

        <!-- ADD NEW CATEGORY -->
        <div class="request-tips">
          <div class="card" style="margin-bottom:16px;border-top:4px solid var(--gold);">
            <div class="card-header"><div class="card-title">➕ Add New Category</div></div>
            <div class="card-body">
              <form method="POST" data-validate>
                <div class="form-group">
                  <label class="form-label">Category Name</label>
                  <input type="text" name="name" class="form-control"
                    placeholder="e.g. Pest Control" required maxlength="50"
                    value="<?=sanitize($_POST['name'] ?? '')?>">
                  <p class="form-hint">Short, clear label students will see when picking a category.</p>
                </div>
                <button type="submit" name="add" value="1" class="btn btn-gold btn-block">Add Category</button>
              </form>
            </div>
          </div>

          <div class="card">
            <div class="card-body">
              <div style="font-size:13px;font-weight:700;margin-bottom:10px;">ℹ️ About Categories</div>
              <ul style="font-size:12px;color:var(--text-muted);line-height:1.7;padding-left:18px;">
                <li>Students pick a category when reporting an issue.</li>
                <li>Renaming a category updates every existing request using it.</li>
                <li>You can only delete a category that has no requests attached.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
