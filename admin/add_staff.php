<?php
require_once '../includes/auth.php';
require_once '../includes/schema.php';
requireLogin('admin');
ensureExtendedSchema();
$activePage = 'staff';
$db     = getDB();
$editId = (int)($_GET['edit'] ?? 0);
$editing = false;
$existing = null;

// Available profession / specialization options. Mirrors the request categories so
// admins can match staff to the kinds of jobs they handle.
$professions = ['Electrician', 'Plumber', 'Carpenter', 'HVAC Technician', 'General Maintenance', 'Other'];

if ($editId) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'staff'");
    $stmt->execute([$editId]);
    $existing = $stmt->fetch();
    if ($existing) $editing = true;
}

$errors = []; $values = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $profession = trim($_POST['profession'] ?? '');
    $values     = compact('name', 'email', 'profession');

    if (!$name)  $errors['name']  = 'Full name is required.';
    if (!$email) $errors['email'] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';

    if (!$profession || !in_array($profession, $professions, true)) {
        $errors['profession'] = 'Please select a profession.';
    }

    if (!$editing || $password) {
        if (strlen($password) < 6)  $errors['password'] = 'Password must be at least 6 characters.';
        if ($password !== $confirm)  $errors['confirm_password'] = 'Passwords do not match.';
    }

    // Unique email check
    if (empty($errors['email'])) {
        $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->execute([$email, $editId ?: 0]);
        if ($chk->fetch()) $errors['email'] = 'This email is already in use.';
    }

    if (empty($errors)) {
        if ($editing) {
            if ($password) {
                $db->prepare("UPDATE users SET name=?, email=?, password=?, profession=? WHERE id=?")
                   ->execute([$name, $email, hashPassword($password), $profession, $editId]);
            } else {
                $db->prepare("UPDATE users SET name=?, email=?, profession=? WHERE id=?")
                   ->execute([$name, $email, $profession, $editId]);
            }
            setFlash('success', 'Staff account updated.');
        } else {
            // Staff accounts skip OTP verification — admin has vouched for them
            $db->prepare("INSERT INTO users (name, email, password, role, profession, is_verified) VALUES (?,?,?,'staff',?,1)")
               ->execute([$name, $email, hashPassword($password), $profession]);
            setFlash('success', 'Staff account created. They can now log in.');
        }
        redirect(SITE_URL . '/admin/staff.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=$editing?'Edit':'Add'?> Staff — HostelIQ Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div>
        <div class="page-title"><?=$editing?'Edit Staff Account':'Add New Staff Member'?></div>
        <div class="page-subtitle"><?=$editing?'Update credentials for '.sanitize($existing['name']):'Create a maintenance staff account'?></div>
      </div>
      <a href="staff.php" class="btn btn-outline">← Back to Staff</a>
    </div>
    <div class="page-body">
      <div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;">
        <div class="card">
          <div class="card-header"><div class="card-title"><?=$editing?'Edit':'New'?> Staff Account</div></div>
          <div class="card-body">
            <form method="POST" data-validate>
              <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-control <?=isset($errors['name'])?'is-invalid':''?>"
                  placeholder="e.g. Jonas Mensah"
                  value="<?=sanitize($values['name'] ?? $existing['name'] ?? '')?>" required>
                <?php if (isset($errors['name'])): ?><p class="form-error">⚠ <?=sanitize($errors['name'])?></p><?php endif; ?>
              </div>

              <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control <?=isset($errors['email'])?'is-invalid':''?>"
                  placeholder="staff@ashesi.edu.gh"
                  value="<?=sanitize($values['email'] ?? $existing['email'] ?? '')?>" required>
                <?php if (isset($errors['email'])): ?><p class="form-error">⚠ <?=sanitize($errors['email'])?></p><?php endif; ?>
              </div>

              <div class="form-group">
                <label class="form-label">Profession / Specialization *</label>
                <?php $currentProf = $values['profession'] ?? ($existing['profession'] ?? ''); ?>
                <select name="profession" class="form-control <?=isset($errors['profession'])?'is-invalid':''?>" required>
                  <option value="">— Select profession —</option>
                  <?php foreach ($professions as $p): ?>
                    <option value="<?=sanitize($p)?>" <?=$currentProf === $p ? 'selected' : ''?>><?=sanitize($p)?></option>
                  <?php endforeach; ?>
                </select>
                <p class="form-hint">Helps you assign the right person to the right kind of request.</p>
                <?php if (isset($errors['profession'])): ?><p class="form-error">⚠ <?=sanitize($errors['profession'])?></p><?php endif; ?>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Password <?=$editing?'(leave blank to keep)':' *'?></label>
                  <input type="password" name="password" class="form-control <?=isset($errors['password'])?'is-invalid':''?>"
                    placeholder="Min. 6 characters" <?=$editing?'':'required'?>>
                  <?php if (isset($errors['password'])): ?><p class="form-error">⚠ <?=sanitize($errors['password'])?></p><?php endif; ?>
                </div>
                <div class="form-group">
                  <label class="form-label">Confirm Password</label>
                  <input type="password" name="confirm_password" class="form-control <?=isset($errors['confirm_password'])?'is-invalid':''?>"
                    placeholder="Repeat password">
                  <?php if (isset($errors['confirm_password'])): ?><p class="form-error">⚠ <?=sanitize($errors['confirm_password'])?></p><?php endif; ?>
                </div>
              </div>

              <div style="display:flex;gap:12px;margin-top:8px;">
                <button type="submit" class="btn btn-primary"><?=$editing?'Save Changes':'Create Account'?></button>
                <a href="staff.php" class="btn btn-outline">Cancel</a>
              </div>
            </form>
          </div>
        </div>

        <div class="card" style="border-top:4px solid var(--info);">
          <div class="card-body">
            <div style="font-size:14px;font-weight:700;margin-bottom:12px;">📋 Account Notes</div>
            <?php foreach ([
              ['🔑','Immediate access','The account is active right away after creation.'],
              ['👁','Task visibility','Staff can only see tasks assigned to them.'],
              ['🔒','Security','Passwords are hashed — never stored in plain text.'],
              ['📧','Login email','Staff use their email and password to log in.'],
            ] as [$icon,$t,$d]): ?>
            <div style="display:flex;gap:10px;margin-bottom:14px;">
              <span style="font-size:18px;"><?=$icon?></span>
              <div>
                <div style="font-size:12px;font-weight:600"><?=$t?></div>
                <div style="font-size:11px;color:var(--text-muted)"><?=$d?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
