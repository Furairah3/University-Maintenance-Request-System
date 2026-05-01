<?php
require_once '../includes/auth.php';
requireLogin('student');
$activePage = 'my_requests';
$db  = getDB();
$uid = $_SESSION['user_id'];

$status   = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$search   = trim($_GET['q'] ?? '');

$sql    = "SELECT * FROM requests WHERE student_id = ?";
$params = [$uid];
if ($status)   { $sql .= " AND status = ?";   $params[] = $status; }
if ($category) { $sql .= " AND category = ?";  $params[] = $category; }
if ($search)   { $sql .= " AND (title LIKE ? OR description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$catList = $db->query("SELECT name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($catList)) {
    $catList = ['Electrical','Plumbing','Furniture','HVAC','Other'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Requests — HostelIQ</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div>
        <div class="page-title">My Requests</div>
        <div class="page-subtitle"><?=count($requests)?> request<?=count($requests)!==1?'s':''?> found</div>
      </div>
      <a href="new_request.php" class="btn btn-primary">➕ New Request</a>
    </div>
    <div class="page-body">

      <!-- FILTERS -->
      <form method="GET" class="filters-bar">
        <div class="search-box">
          <input type="text" name="q" class="form-control" placeholder="Search requests…" value="<?=sanitize($search)?>">
        </div>
        <select name="status" class="form-control">
          <option value="">All Status</option>
          <?php foreach (['Pending','In Progress','Completed'] as $st): ?>
          <option value="<?=$st?>" <?=$status===$st?'selected':''?>><?=$st?></option>
          <?php endforeach; ?>
        </select>
        <select name="category" class="form-control">
          <option value="">All Categories</option>
          <?php foreach ($catList as $c): ?>
          <option value="<?=sanitize($c)?>" <?=$category===$c?'selected':''?>><?=sanitize($c)?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline">Filter</button>
        <?php if ($status||$category||$search): ?><a href="my_requests.php" class="btn btn-outline">Clear</a><?php endif; ?>
      </form>

      <div class="card">
        <div class="table-wrapper">
          <?php if (empty($requests)): ?>
          <div class="empty-state">
            <div class="empty-icon">📭</div>
            <div class="empty-title">No requests found</div>
            <p style="font-size:13px;margin-bottom:16px;color:var(--text-muted)">
              <?=($status||$category||$search)?'Try adjusting your filters.':'Submit your first maintenance request.'?>
            </p>
            <a href="new_request.php" class="btn btn-primary">➕ New Request</a>
          </div>
          <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>#</th>
                <th>Title</th>
                <th>Category</th>
                <th>Submitted</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Assigned To</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="tableBody">
              <?php foreach ($requests as $i=>$r): ?>
              <tr>
                <td style="color:var(--text-muted)"><?=$i+1?></td>
                <td><strong><?=sanitize($r['title'])?></strong></td>
                <td><?=sanitize($r['category'])?></td>
                <td style="white-space:nowrap;color:var(--text-muted)"><?=date('M j, Y', strtotime($r['created_at']))?></td>
                <td><?=statusBadge($r['status'])?></td>
                <td><?=priorityBadge($r['priority'])?></td>
                <td>
                  <?php if ($r['assigned_to']): ?>
                    <?php $st=$db->prepare("SELECT name FROM users WHERE id=?"); $st->execute([$r['assigned_to']]); $sn=$st->fetchColumn(); ?>
                    <span style="font-size:12px;"><?=sanitize($sn)?></span>
                  <?php else: ?>
                    <span style="color:var(--text-muted);font-size:12px;">—</span>
                  <?php endif; ?>
                </td>
                <td><a href="request_details.php?id=<?=$r['id']?>" class="btn btn-outline btn-sm">View</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
