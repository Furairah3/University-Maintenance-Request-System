<?php
require_once '../includes/auth.php';
require_once '../includes/schema.php';
require_once '../includes/profile.php';
requireLogin('student');
ensureExtendedSchema();
$activePage = 'settings';
$db  = getDB();
$uid = $_SESSION['user_id'];
$user = getUserById($uid);
$errors = []; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $room = trim($_POST['room_number'] ?? '');

        if (!$name) { $errors['name'] = 'Name is required.'; }

        // Avatar (optional)
        if (empty($errors) && isset($_FILES['avatar'])) {
            $res = handleAvatarUpload($uid, $_FILES['avatar']);
            if ($res !== true) $errors['avatar'] = $res;
        }

        if (empty($errors)) {
            $db->prepare("UPDATE users SET name=?, room_number=? WHERE id=?")->execute([$name, $room, $uid]);
            $_SESSION['name'] = $name;
            $success = 'Profile updated successfully.';
        }
    } elseif ($action === 'remove_avatar') {
        deleteAvatar($uid);
        $success = 'Profile picture removed.';
    } elseif ($action === 'password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $cfm = $_POST['confirm_password'] ?? '';
        $hashStmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $hashStmt->execute([$uid]);
        $currentHash = (string)$hashStmt->fetchColumn();
        if (!verifyPassword($old, $currentHash)) { $errors['old_password'] = 'Current password is incorrect.'; }
        elseif (strlen($new) < 8) { $errors['new_password'] = 'New password must be at least 8 characters.'; }
        elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/\d/', $new) || !preg_match('/[^A-Za-z0-9]/', $new)) {
            $errors['new_password'] = 'Use uppercase, lowercase, a number, and a special character.';
        }
        elseif ($new !== $cfm) { $errors['confirm_password'] = 'Passwords do not match.'; }
        else {
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([hashPassword($new), $uid]);
            $success = 'Password updated.';
        }
    }
    $user = getUserById($uid);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings — HostelIQ</title>
<link rel="stylesheet" href="../assets/css/style.css?v=<?=@filemtime(__DIR__.'/../assets/css/style.css')?>">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div><div class="page-title">Settings</div><div class="page-subtitle">Manage your account</div></div>
    </div>
    <div class="page-body" style="max-width:760px;">
      <?php if ($success): ?><div class="alert alert-success" data-auto-dismiss>✓ <?=sanitize($success)?></div><?php endif; ?>

      <!-- PROFILE HEADER -->
      <div class="profile-header">
        <form method="POST" enctype="multipart/form-data" class="profile-avatar-wrap" id="avatarForm">
          <input type="hidden" name="action" value="profile">
          <input type="hidden" name="name" value="<?=sanitize($user['name'])?>">
          <input type="hidden" name="room_number" value="<?=sanitize($user['room_number'] ?? '')?>">
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
            <span class="badge badge-info">Student</span>
            <?php if (!empty($user['is_verified'])): ?><span class="badge badge-success">✓ Verified</span><?php endif; ?>
          </div>
          <div class="profile-meta">
            <div class="profile-meta-item">
              <div class="profile-meta-label">Member Since</div>
              <div class="profile-meta-value"><?=memberSinceText($user)?></div>
            </div>
            <?php if (!empty($user['room_number'])): ?>
            <div class="profile-meta-item">
              <div class="profile-meta-label">Room</div>
              <div class="profile-meta-value"><?=sanitize($user['room_number'])?></div>
            </div>
            <?php endif; ?>
            <div class="profile-meta-item">
              <div class="profile-meta-label">Role</div>
              <div class="profile-meta-value">Student</div>
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

      <!-- PROFILE FIELDS -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div class="card-title">Profile Information</div></div>
        <div class="card-body">
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="profile">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" value="<?=sanitize($user['name'])?>" required>
                <?php if (isset($errors['name'])): ?><p class="form-error">⚠ <?=sanitize($errors['name'])?></p><?php endif; ?>
              </div>
              <div class="form-group">
                <label class="form-label">Room Number</label>
                <input type="text" name="room_number" class="form-control" value="<?=sanitize($user['room_number']??'')?>" placeholder="e.g. B-214">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="text" class="form-control" value="<?=sanitize($user['email'])?>" disabled style="background:#f8f9fa;color:var(--text-muted);">
              <p class="form-hint">Email cannot be changed. Contact admin if needed.</p>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </form>
        </div>
      </div>

      <!-- PASSWORD -->
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
