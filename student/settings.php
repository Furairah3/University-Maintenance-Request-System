<?php
require_once __DIR__ . '/../backend/includes/Auth.php';
require_once __DIR__ . '/../backend/includes/helpers.php';
Auth::requireRole('student');

$db = Database::getInstance();
$userId = Auth::getUserId();
$pageTitle = 'Settings';

$profileError = '';
$profileSuccess = '';
$passwordError = '';
$passwordSuccess = '';
$prefsError = '';
$prefsSuccess = '';
$photoError = '';
$photoSuccess = '';

// Load current user
$user = $db->fetchOne(
    "SELECT id, name, email, email_notifications, profile_image, created_at, last_login FROM users WHERE id = ?",
    [$userId]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
        $profileError = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // ---------- Update profile (name) ----------
        if ($action === 'update_profile') {
            $name = sanitize($_POST['name'] ?? '');
            if (strlen($name) < 2 || strlen($name) > 100) {
                $profileError = 'Name must be between 2 and 100 characters.';
            } else {
                try {
                    $db->execute("UPDATE users SET name = ? WHERE id = ?", [$name, $userId]);
                    $_SESSION['user_name'] = $name;
                    $user['name'] = $name;
                    Logger::activity('update_profile', 'user', $userId);
                    $profileSuccess = 'Profile updated successfully.';
                } catch (Exception $e) {
                    Logger::error('Profile update failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
                    $profileError = 'Failed to update profile. Please try again.';
                }
            }
        }

        // ---------- Change password ----------
        elseif ($action === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            $row = $db->fetchOne("SELECT password_hash FROM users WHERE id = ?", [$userId]);
            if (!$row || !password_verify($current, $row['password_hash'])) {
                $passwordError = 'Current password is incorrect.';
            } elseif (strlen($new) < 8) {
                $passwordError = 'New password must be at least 8 characters long.';
            } elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
                $passwordError = 'Password must contain at least one uppercase letter and one number.';
            } elseif ($new !== $confirm) {
                $passwordError = 'New password and confirmation do not match.';
            } elseif ($new === $current) {
                $passwordError = 'New password must be different from your current password.';
            } else {
                try {
                    $newHash = password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                    $db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$newHash, $userId]);
                    Logger::info('Password changed', ['user_id' => $userId]);
                    Logger::activity('change_password', 'user', $userId);
                    $passwordSuccess = 'Password changed successfully.';
                } catch (Exception $e) {
                    Logger::error('Password change failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
                    $passwordError = 'Failed to change password. Please try again.';
                }
            }
        }

        // ---------- Upload profile photo ----------
        elseif ($action === 'upload_photo') {
            if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
                $photoError = 'Please choose an image to upload.';
            } else {
                $uploadResult = handleImageUpload($_FILES['photo'], 'prof_');
                if (!$uploadResult['success']) {
                    $photoError = $uploadResult['error'];
                } else {
                    $newPath = $uploadResult['path'];
                    $oldPath = $user['profile_image'];
                    try {
                        $db->execute("UPDATE users SET profile_image = ? WHERE id = ?", [$newPath, $userId]);
                        // Delete old file if it existed
                        if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath)) {
                            @unlink(__DIR__ . '/../' . $oldPath);
                        }
                        $_SESSION['user_profile_image'] = $newPath;
                        $user['profile_image'] = $newPath;
                        Logger::activity('upload_profile_photo', 'user', $userId);
                        $photoSuccess = 'Profile photo updated.';
                    } catch (Exception $e) {
                        Logger::error('Profile photo update failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
                        $photoError = 'Failed to save profile photo. Please try again.';
                    }
                }
            }
        }

        // ---------- Remove profile photo ----------
        elseif ($action === 'remove_photo') {
            $oldPath = $user['profile_image'];
            try {
                $db->execute("UPDATE users SET profile_image = NULL WHERE id = ?", [$userId]);
                if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath)) {
                    @unlink(__DIR__ . '/../' . $oldPath);
                }
                $_SESSION['user_profile_image'] = null;
                $user['profile_image'] = null;
                Logger::activity('remove_profile_photo', 'user', $userId);
                $photoSuccess = 'Profile photo removed.';
            } catch (Exception $e) {
                Logger::error('Profile photo removal failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
                $photoError = 'Failed to remove profile photo. Please try again.';
            }
        }

        // ---------- Notification preferences ----------
        elseif ($action === 'update_prefs') {
            $emailNotif = isset($_POST['email_notifications']) ? 1 : 0;
            try {
                $db->execute(
                    "UPDATE users SET email_notifications = ? WHERE id = ?",
                    [$emailNotif, $userId]
                );
                $user['email_notifications'] = $emailNotif;
                Logger::activity('update_prefs', 'user', $userId);
                $prefsSuccess = 'Notification preferences saved.';
            } catch (Exception $e) {
                Logger::error('Prefs update failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
                $prefsError = 'Failed to save preferences. Please try again.';
            }
        }
    }
}

include __DIR__ . '/../frontend/includes/student-header.php';
?>

<div style="display:flex;flex-direction:column;gap:20px;max-width:700px;">

    <!-- Profile picture -->
    <div class="card">
        <div class="card-header"><h3>Profile picture</h3></div>
        <div class="card-body">
            <?php if ($photoError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($photoError) ?></div>
            <?php endif; ?>
            <?php if ($photoSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($photoSuccess) ?></div>
            <?php endif; ?>

            <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;">
                <div style="width:96px;height:96px;border-radius:50%;overflow:hidden;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:36px;font-weight:600;flex-shrink:0;">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="<?= APP_URL . '/' . htmlspecialchars($user['profile_image']) ?>?v=<?= time() ?>"
                             alt="Profile picture" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    <?php endif; ?>
                </div>

                <div style="flex:1;min-width:240px;">
                    <form method="POST" action="" enctype="multipart/form-data" style="margin-bottom:8px;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="upload_photo">
                        <div class="form-group" style="margin-bottom:10px;">
                            <input type="file" name="photo" accept="image/jpeg,image/png" required>
                            <small class="text-muted" style="display:block;font-size:12px;margin-top:4px;">JPEG or PNG, max 5 MB.</small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Upload photo</button>
                    </form>

                    <?php if (!empty($user['profile_image'])): ?>
                        <form method="POST" action="" onsubmit="return confirm('Remove your profile picture?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="remove_photo">
                            <button type="submit" class="btn btn-secondary btn-sm">Remove current photo</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Account info -->
    <div class="card">
        <div class="card-header"><h3>Account</h3></div>
        <div class="card-body">
            <div class="form-row mb-2">
                <div>
                    <div class="text-muted" style="font-size:12px;">Email</div>
                    <div><?= htmlspecialchars($user['email']) ?></div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:12px;">Role</div>
                    <div>Student</div>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <div class="text-muted" style="font-size:12px;">Member since</div>
                    <div><?= formatDate($user['created_at']) ?></div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:12px;">Last login</div>
                    <div><?= $user['last_login'] ? formatDate($user['last_login'], 'M d, Y H:i') : '—' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile -->
    <div class="card">
        <div class="card-header"><h3>Profile</h3></div>
        <div class="card-body">
            <?php if ($profileError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($profileError) ?></div>
            <?php endif; ?>
            <?php if ($profileSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($profileSuccess) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label class="form-label" for="name">Full name</label>
                    <input type="text" id="name" name="name" class="form-control"
                           value="<?= htmlspecialchars($user['name']) ?>"
                           required minlength="2" maxlength="100">
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    <small class="text-muted" style="font-size:12px;">Email cannot be changed. Contact an administrator if needed.</small>
                </div>

                <button type="submit" class="btn btn-primary">Save changes</button>
            </form>
        </div>
    </div>

    <!-- Password -->
    <div class="card">
        <div class="card-header"><h3>Change password</h3></div>
        <div class="card-body">
            <?php if ($passwordError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($passwordError) ?></div>
            <?php endif; ?>
            <?php if ($passwordSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($passwordSuccess) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label class="form-label" for="current_password">Current password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="new_password">New password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control"
                           required minlength="8">
                    <small class="text-muted" style="font-size:12px;">At least 8 characters, one uppercase letter, and one number.</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm new password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                           required minlength="8">
                </div>

                <button type="submit" class="btn btn-primary">Change password</button>
            </form>
        </div>
    </div>

    <!-- Preferences -->
    <div class="card">
        <div class="card-header"><h3>Notification preferences</h3></div>
        <div class="card-body">
            <?php if ($prefsError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($prefsError) ?></div>
            <?php endif; ?>
            <?php if ($prefsSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($prefsSuccess) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_prefs">

                <div class="form-group">
                    <label style="display:flex;gap:10px;align-items:center;cursor:pointer;">
                        <input type="checkbox" name="email_notifications" value="1"
                               <?= $user['email_notifications'] ? 'checked' : '' ?>
                               style="width:18px;height:18px;">
                        <span>
                            <strong>Email notifications</strong><br>
                            <small class="text-muted" style="font-size:12px;">Receive email updates when your request status changes or a staff member is assigned.</small>
                        </span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">Save preferences</button>
            </form>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../frontend/includes/footer.php'; ?>
