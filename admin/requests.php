<?php
require_once '../includes/auth.php';
requireLogin('admin');
$activePage = 'requests';
$db = getDB();

$status   = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$priority = $_GET['priority'] ?? '';
$search   = trim($_GET['q'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';

$sql = "SELECT r.*, u.name AS student_name, u.room_number, s.name AS staff_name
        FROM requests r
        JOIN users u ON u.id = r.student_id
        LEFT JOIN users s ON s.id = r.assigned_to
        WHERE 1=1";
$params = [];
if ($status)   { $sql .= " AND r.status=?";   $params[] = $status; }
if ($category) { $sql .= " AND r.category=?"; $params[] = $category; }
if ($priority) { $sql .= " AND r.priority=?"; $params[] = $priority; }
if ($search)   { $sql .= " AND (r.title LIKE ? OR u.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($dateFrom) { $sql .= " AND DATE(r.created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $sql .= " AND DATE(r.created_at) <= ?"; $params[] = $dateTo; }
$sql .= " ORDER BY r.created_at DESC";

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
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>All Requests — HostelIQ Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div>
        <div class="page-title">All Maintenance Requests</div>
        <div class="page-subtitle"><?=count($requests)?> request<?=count($requests)!==1?'s':''?> found</div>
      </div>
    </div>
    <div class="page-body">

      <!-- FILTERS -->
      <form method="GET">
        <div class="filters-bar">
          <div class="search-box">
            <input type="text" name="q" class="form-control" placeholder="Search by title or student…" value="<?=sanitize($search)?>">
          </div>
          <select name="status" class="form-control">
            <option value="">All Status</option>
            <?php foreach (['Pending','In Progress','Completed'] as $st): ?><option value="<?=$st?>" <?=$status===$st?'selected':''?>><?=$st?></option><?php endforeach; ?>
          </select>
          <select name="category" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($catList as $c): ?><option value="<?=sanitize($c)?>" <?=$category===$c?'selected':''?>><?=sanitize($c)?></option><?php endforeach; ?>
          </select>
          <select name="priority" class="form-control">
            <option value="">All Priorities</option>
            <?php foreach (['High','Medium','Low'] as $p): ?><option value="<?=$p?>" <?=$priority===$p?'selected':''?>><?=$p?></option><?php endforeach; ?>
          </select>
          <input type="date" name="date_from" class="form-control" value="<?=sanitize($dateFrom)?>" title="From date">
          <input type="date" name="date_to"   class="form-control" value="<?=sanitize($dateTo)?>"   title="To date">
          <button type="submit" class="btn btn-primary">Filter</button>
          <?php if ($status||$category||$priority||$search||$dateFrom||$dateTo): ?>
          <a href="requests.php" class="btn btn-outline">Clear</a>
          <?php endif; ?>
        </div>
      </form>

      <div class="card">
        <div class="table-wrapper">
          <?php if (empty($requests)): ?>
          <div class="empty-state"><div class="empty-icon">📭</div><div class="empty-title">No requests found</div><p style="color:var(--text-muted);font-size:13px">Adjust your filters to see results.</p></div>
          <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>#</th><th>Title</th><th>Student</th><th>Category</th><th>Priority</th><th>Status</th><th>Assigned To</th><th>Submitted</th><th></th>
              </tr>
            </thead>
            <tbody id="tableBody">
              <?php foreach ($requests as $i=>$r): ?>
              <tr>
                <td style="color:var(--text-muted)"><?=$i+1?></td>
                <td style="max-width:200px"><strong><?=sanitize($r['title'])?></strong></td>
                <td>
                  <div style="font-size:12px;font-weight:600"><?=sanitize($r['student_name'])?></div>
                  <?php if ($r['room_number']): ?><div style="font-size:11px;color:var(--text-muted)">Room <?=sanitize($r['room_number'])?></div><?php endif; ?>
                </td>
                <td><span class="badge badge-secondary"><?=sanitize($r['category'])?></span></td>
                <td><?=priorityBadge($r['priority'])?></td>
                <td><?=statusBadge($r['status'])?></td>
                <td>
                  <?php if ($r['staff_name']): ?>
                    <span style="font-size:12px"><?=sanitize($r['staff_name'])?></span>
                  <?php else: ?>
                    <span style="color:var(--danger);font-size:11px;font-weight:600">Unassigned</span>
                  <?php endif; ?>
                </td>
                <td style="white-space:nowrap;color:var(--text-muted);font-size:12px"><?=date('M j, Y', strtotime($r['created_at']))?></td>
                <td><a href="request_details.php?id=<?=$r['id']?>" class="btn btn-primary btn-sm">Manage</a></td>
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
