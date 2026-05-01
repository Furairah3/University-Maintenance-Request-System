<?php
require_once '../includes/auth.php';
require_once '../includes/schema.php';
require_once '../includes/profile.php';
requireLogin('staff');
ensureExtendedSchema();
$activePage = 'settings';
$db  = getDB();
$uid = $_SESSION['user_id'];
$user = getUserById($uid);
$errors = []; $success = '';

// Quick stats for the profile header (tasks completed, active)
$taskStats = $db->prepare("
    SELECT
        SUM(status='Completed') AS done,
        SUM(status!='Completed') AS active
    FROM requests WHERE assigned_to = ?
");
$taskStats->execute([$uid]);
$ts = $taskStats->fetch() ?: ['done' => 0, 'active' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'password';
    if ($action === 'profile_picture') {
        if (isset($_FILES['avatar'])) {
            $res = handleAvatarUpload($uid, $_FILES['avatar']);
            if ($res !== true) $errors['avatar'] = $res;
            else $success = 'Profile picture updated.';
        }
    } elseif ($action === 'remove_avatar') {
        deleteAvatar($uid);
        $success = 'Profile picture removed.';
    } else {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $cfm = $_POST['confirm_password'] ?? '';
        $hashStmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $hashStmt->execute([$uid]);
        $currentHash = (string)$hashStmt->fetchColumn();
        if (!verifyPassword($old, $currentHash)) $errors['old_password'] = 'Current password incorrect.';
        elseif (strlen($new) < 8) $errors['new_password'] = 'Minimum 8 characters.';
        elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/\d/', $new) || !preg_match('/[^A-Za-z0-9]/', $new)) {
            $errors['new_password'] = 'Use uppercase, lowercase, a number, and a special character.';
        }
        elseif ($new !== $cfm)   $errors['confirm_password'] = 'Passwords do not match.';
        else {
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([hashPassword($new), $uid]);
            $success = 'Password updated successfully.';
        }
    }
    $user = getUserById($uid);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings — HostelIQ Staff</title>
<link rel="stylesheet" href="../assets/css/style.css?v=<?=@filemtime(__DIR__.'/../assets/css/style.css')?>">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar"><div><div class="page-title">Settings</div><div class="page-subtitle">Your account</div></div></div>
    <div class="page-body" style="max-width:700px;">
      <?php if ($success): ?><div class="alert alert-success" data-auto-dismiss>✓ <?=sanitize($success)?></div><?php endif; ?>

      <!-- PROFILE HEADER -->
      <div class="profile-header">
        <form method="POST" enctype="multipart/form-data" class="profile-avatar-wrap" id="avatarForm">
          <input type="hidden" name="action" value="profile_picture">
          <img src="<?=avatarUrl($user)?>" alt="Profile picture" class="profile-avatar">
          <label class="profile-avatar-edit" title="Change profile picture">
            📷
            <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp"
              class="profile-avatar-input"
              onchange="document.getElementById('avatarForm').submit();">
          </label>
        </form>
        <div class="profile-info">
          <div class="profile-name"><?=sanitize($user['name'])?></div>
          <div class="profile-email"><?=sanitize($user['email'])?></div>
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <span class="badge badge-info">Maintenance Staff</span>
            <?php if (!empty($user['is_verified'])): ?><span class="badge badge-success">✓ Verified</span><?php endif; ?>
            <?php if (empty($user['is_active'])): ?><span class="badge badge-danger">Inactive</span><?php endif; ?>
          </div>
          <div class="profile-meta">
            <div class="profile-meta-item">
              <div class="profile-meta-label">Member Since</div>
              <div class="profile-meta-value"><?=memberSinceText($user)?></div>
            </div>
            <div class="profile-meta-item">
              <div class="profile-meta-label">Tasks Completed</div>
              <div class="profile-meta-value"><?=(int)$ts['done']?></div>
            </div>
            <div class="profile-meta-item">
              <div class="profile-meta-label">Active Tasks</div>
              <div class="profile-meta-value"><?=(int)$ts['active']?></div>
            </div>
          </div>
          <?php if (!empty($user['avatar_path'])): ?>
          <form method="POST" style="margin-top:14px;">
            <input type="hidden" name="action" value="remove_avatar">
            <button type="submit" class="btn btn-outline btn-sm"
              onclick="return confirm('Remove profile picture?');">🗑 Remove picture</button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if (isset($errors['avatar'])): ?>
      <div class="alert alert-danger">⚠ <?=sanitize($errors['avatar'])?></div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header"><div class="card-title">Change Password</div></div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="password">
            <div class="form-group">
              <label class="form-label">Current Password</label>
              <input type="password" name="old_password" class="form-control" required>
              <?php if (isset($errors['old_password'])): ?><p class="form-error">⚠ <?=sanitize($errors['old_password'])?></p><?php endif; ?>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="8">
                <p class="form-hint">Min 8 chars, with upper, lower, number and symbol.</p>
                <?php if (isset($errors['new_password'])): ?><p class="form-error">⚠ <?=sanitize($errors['new_password'])?></p><?php endif; ?>
              </div>
              <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
                <?php if (isset($errors['confirm_password'])): ?><p class="form-error">⚠ <?=sanitize($errors['confirm_password'])?></p><?php endif; ?>
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Update Password</button>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
