<?php
require_once '../includes/auth.php';
require_once '../includes/email.php';
requireLogin('student');
$activePage = 'new_request';
$db  = getDB();
$uid = $_SESSION['user_id'];
$errors = [];

// Load categories from DB (managed by admin)
$categories = $db->query("SELECT name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($categories)) {
    $categories = ['Electrical','Plumbing','Furniture','HVAC','Other'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = $_POST['category'] ?? '';
    $location    = trim($_POST['location'] ?? '');

    $validCats = $categories;

    if (!$title)                        $errors['title']       = 'Title is required.';
    if (!$description)                  $errors['description'] = 'Description is required.';
    if (!in_array($category,$validCats))$errors['category']    = 'Select a valid category.';

    // File upload
    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $file      = $_FILES['image'];
        $allowed   = ['image/jpeg','image/png','image/gif'];
        $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extMap    = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif'];

        if (!in_array($file['type'], $allowed) || !isset($extMap[$ext])) {
            $errors['image'] = 'Only JPEG, PNG, GIF images are allowed.';
        } elseif ($file['size'] > MAX_FILE_SIZE) {
            $errors['image'] = 'Image must be under 5MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['image'] = 'Upload failed. Please try again.';
        }
    }

    if (empty($errors)) {
        if (!empty($_FILES['image']['name']) && !isset($errors['image'])) {
            $filename  = uniqid('req_', true) . '.' . $ext;
            $dest      = UPLOAD_DIR . $filename;
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            move_uploaded_file($file['tmp_name'], $dest);
            $imagePath = $filename;
        }

        $fullDesc = $location ? "Location: {$location}\n\n{$description}" : $description;
        $stmt = $db->prepare("INSERT INTO requests (student_id,title,description,category,image_path) VALUES (?,?,?,?,?)");
        $stmt->execute([$uid, $title, $fullDesc, $category, $imagePath]);
        $newId = $db->lastInsertId();

        // Log initial status
        $db->prepare("INSERT INTO status_history (request_id,new_status,changed_by) VALUES (?,?,?)")
           ->execute([$newId, 'Pending', $uid]);

        // Notify all admins (in-app + email)
        notifyNewRequest($newId);

        setFlash('success', 'Request submitted successfully! Admin has been notified.');
        redirect(SITE_URL . '/student/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>New Request — HostelIQ</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div>
        <div class="page-title">Submit a Maintenance Request</div>
        <div class="page-subtitle">Describe your issue clearly so we can resolve it quickly</div>
      </div>
    </div>
    <div class="page-body">
      <div class="request-layout">

        <div class="card request-form">
          <div class="card-header"><div class="card-title">New Request</div></div>
          <div class="card-body">
            <form method="POST" enctype="multipart/form-data" data-validate>

              <div class="form-group">
                <label class="form-label">Issue Title *</label>
                <input type="text" name="title" class="form-control <?=isset($errors['title'])?'is-invalid':''?>"
                  placeholder="e.g. Broken light switch in bedroom — be specific"
                  value="<?=sanitize($_POST['title']??'')?>" required maxlength="200">
                <?php if (isset($errors['title'])): ?><p class="form-error">⚠ <?=sanitize($errors['title'])?></p><?php endif; ?>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Category *</label>
                  <select name="category" class="form-control <?=isset($errors['category'])?'is-invalid':''?>" required>
                    <option value="">Select category…</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?=sanitize($c)?>" <?=($_POST['category']??'')===$c?'selected':''?>><?=sanitize($c)?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php if (isset($errors['category'])): ?><p class="form-error">⚠ <?=sanitize($errors['category'])?></p><?php endif; ?>
                </div>
                <div class="form-group">
                  <label class="form-label">Room / Location *</label>
                  <input type="text" name="location" class="form-control"
                    placeholder="e.g. Room B-214, bathroom"
                    value="<?=sanitize($_POST['location']??'')?>">
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Description *</label>
                <textarea name="description" class="form-control <?=isset($errors['description'])?'is-invalid':''?>"
                  rows="5" placeholder="Describe the issue in detail: exact location, when it started, how severe it is…" required><?=sanitize($_POST['description']??'')?></textarea>
                <?php if (isset($errors['description'])): ?><p class="form-error">⚠ <?=sanitize($errors['description'])?></p><?php endif; ?>
              </div>

              <div class="form-group">
                <label class="form-label">Attach Photo (Optional)</label>
                <div class="file-drop">
                  <input type="file" name="image" accept="image/jpeg,image/png,image/gif">
                  <div class="file-drop-icon">📷</div>
                  <div class="file-drop-text" id="fileLabel">Click to upload or drag a photo here</div>
                  <div class="file-drop-hint">JPEG, PNG or GIF — max 5MB</div>
                </div>
                <?php if (isset($errors['image'])): ?><p class="form-error">⚠ <?=sanitize($errors['image'])?></p><?php endif; ?>
              </div>

              <div style="display:flex;gap:12px;margin-top:8px;">
                <button type="submit" class="btn btn-primary btn-lg" style="flex:1;">Submit Request</button>
                <a href="dashboard.php" class="btn btn-outline btn-lg">Cancel</a>
              </div>
            </form>
          </div>
        </div>

        <!-- TIPS PANEL -->
        <div class="request-tips">
          <div class="card" style="margin-bottom:16px;">
            <div class="card-body">
              <div style="font-size:14px;font-weight:700;margin-bottom:12px;">💡 Tips for Faster Resolution</div>
              <?php foreach ([
                ['📍','Be specific about location','Mention the room number, floor, and exact spot.'],
                ['📅','Describe when it started','Even an approximate date helps us prioritize.'],
                ['📸','Add a photo','Photos speed up resolution significantly.'],
                ['⚠️','Urgent issues','For emergencies, call the hostel office directly.'],
              ] as [$icon,$t,$d]): ?>
              <div style="display:flex;gap:10px;margin-bottom:14px;">
                <span style="font-size:18px;"><?=$icon?></span>
                <div>
                  <div style="font-size:12px;font-weight:600;"><?=$t?></div>
                  <div style="font-size:11px;color:var(--text-muted)"><?=$d?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="card" style="border-left:4px solid var(--danger);">
            <div class="card-body">
              <div style="font-size:13px;font-weight:700;color:var(--danger);margin-bottom:6px;">🚨 Emergency Contact</div>
              <div style="font-size:12px;color:var(--text-muted)">Hostel Office: +233 30 267 0099</div>
              <div style="font-size:12px;color:var(--text-muted)">Available Mon–Fri, 8am–5pm</div>
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
