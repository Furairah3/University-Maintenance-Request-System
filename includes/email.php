<?php
// =====================================================
// Email Notification Helper (PHPMailer-free fallback)
// Uses PHP mail() — upgrade to PHPMailer for SMTP
// =====================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/smtp.php';

/**
 * Send an email. Tries SMTP first (Gmail/Outlook/etc.) and falls back to PHP mail().
 * Returns true only if the email was accepted for delivery.
 * The last error (if any) is captured in $GLOBALS['LAST_EMAIL_ERROR'] for callers who care.
 */
function sendEmail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
  $GLOBALS['LAST_EMAIL_ERROR'] = null;

  if (smtpConfigured()) {
    $r = smtpSend($toEmail, $toName, $subject, $htmlBody);
    if (!empty($r['ok'])) return true;
    $GLOBALS['LAST_EMAIL_ERROR'] = $r['error'] ?? 'unknown SMTP error';
    // Fall through to mail() as a last resort
  } else {
    $GLOBALS['LAST_EMAIL_ERROR'] = 'SMTP not configured';
  }

  $toHeader = $toName !== '' ? "\"{$toName}\" <{$toEmail}>" : $toEmail;
  $headers  = "MIME-Version: 1.0\r\n";
  $headers .= "Content-type: text/html; charset=UTF-8\r\n";
  $headers .= "From: " . FROM_NAME . " <" . SMTP_USER . ">\r\n";
  $headers .= "Reply-To: " . SMTP_USER . "\r\n";
  $headers .= "X-Mailer: PHP/" . phpversion();

  return @mail($toHeader, $subject, $htmlBody, $headers);
}

function notifyStatusChange(int $requestId, string $oldStatus, string $newStatus): void
{
  $db = getDB();
  $stmt = $db->prepare("
        SELECT r.title, r.student_id, u.email, u.name
        FROM requests r
        JOIN users u ON u.id = r.student_id
        WHERE r.id = ?
    ");
  $stmt->execute([$requestId]);
  $data = $stmt->fetch();
  if (!$data) return;

  // Save in-app notification
  $msg = "Your request \"{$data['title']}\" status changed from {$oldStatus} to {$newStatus}.";
  $ins = $db->prepare("INSERT INTO notifications (user_id, request_id, message) VALUES (?,?,?)");
  $ins->execute([$data['student_id'], $requestId, $msg]);

  // Send email
  $subject = "[HostelIQ] Request Update — {$data['title']}";
  $html = emailTemplate($data['name'], $data['title'], $oldStatus, $newStatus, $requestId);
  sendEmail($data['email'], $data['name'], $subject, $html);
}

function notifyAssignment(int $requestId, int $staffId): void
{
  $db = getDB();
  $stmt = $db->prepare("
        SELECT r.title, r.student_id, u.email, u.name, s.name AS staff_name, s.email AS staff_email
        FROM requests r
        JOIN users u ON u.id = r.student_id
        JOIN users s ON s.id = ?
        WHERE r.id = ?
    ");
  $stmt->execute([$staffId, $requestId]);
  $data = $stmt->fetch();
  if (!$data) return;

  // Notify student (in-app + email)
  $msg = "Your request \"{$data['title']}\" has been assigned to {$data['staff_name']}.";
  $ins = $db->prepare("INSERT INTO notifications (user_id, request_id, message) VALUES (?,?,?)");
  $ins->execute([$data['student_id'], $requestId, $msg]);

  $subject = "[HostelIQ] Staff Assigned — {$data['title']}";
  $html = assignmentEmailTemplate($data['name'], $data['title'], $data['staff_name'], $requestId);
  sendEmail($data['email'], $data['name'], $subject, $html);

  // Notify staff (in-app + email) — they've been given a new task
  $staffMsg = "New task assigned to you: \"{$data['title']}\".";
  $ins->execute([$staffId, $requestId, $staffMsg]);

  $staffSubject = "[HostelIQ] New Task Assigned — {$data['title']}";
  $staffHtml = staffAssignmentEmailTemplate($data['staff_name'], $data['title'], $requestId);
  sendEmail($data['staff_email'], $data['staff_name'], $staffSubject, $staffHtml);
}

/** Student submitted a new maintenance request — notify all admins. */
function notifyNewRequest(int $requestId): void
{
  $db = getDB();
  $stmt = $db->prepare("
        SELECT r.title, r.category, r.priority, u.name AS student_name
        FROM requests r
        JOIN users u ON u.id = r.student_id
        WHERE r.id = ?
    ");
  $stmt->execute([$requestId]);
  $data = $stmt->fetch();
  if (!$data) return;

  $admins = $db->query("SELECT id, name, email FROM users WHERE role='admin' AND is_active=1")->fetchAll();
  if (!$admins) return;

  $msg = "New request from {$data['student_name']}: \"{$data['title']}\" ({$data['category']}).";
  $ins = $db->prepare("INSERT INTO notifications (user_id, request_id, message) VALUES (?,?,?)");
  $subject = "[HostelIQ] New Maintenance Request — {$data['title']}";
  foreach ($admins as $a) {
    $ins->execute([$a['id'], $requestId, $msg]);
    $html = adminNewRequestEmailTemplate($a['name'], $data['student_name'], $data['title'], $data['category'], $requestId);
    sendEmail($a['email'], $a['name'], $subject, $html);
  }
}

/** Staff or anyone changed request status — notify admins (student is already covered by notifyStatusChange). */
function notifyAdminsOfStatus(int $requestId, string $newStatus, string $byRole = 'staff'): void
{
  $db = getDB();
  $stmt = $db->prepare("
        SELECT r.title, u.name AS student_name
        FROM requests r JOIN users u ON u.id = r.student_id
        WHERE r.id = ?
    ");
  $stmt->execute([$requestId]);
  $data = $stmt->fetch();
  if (!$data) return;

  $admins = $db->query("SELECT id, name, email FROM users WHERE role='admin' AND is_active=1")->fetchAll();
  if (!$admins) return;

  $msg = ucfirst($byRole) . " updated \"{$data['title']}\" to {$newStatus}.";
  $ins = $db->prepare("INSERT INTO notifications (user_id, request_id, message) VALUES (?,?,?)");
  $subject = "[HostelIQ] Request {$newStatus} — {$data['title']}";
  foreach ($admins as $a) {
    $ins->execute([$a['id'], $requestId, $msg]);
    $html = adminStatusEmailTemplate($a['name'], $data['student_name'], $data['title'], $newStatus, $requestId);
    sendEmail($a['email'], $a['name'], $subject, $html);
  }
}

/** Student left a review — notify all admins AND the staff member who did the work. */
function notifyReview(int $requestId, int $rating, string $comment = ''): void
{
  $db = getDB();
  $stmt = $db->prepare("
        SELECT r.title, r.assigned_to, u.name AS student_name,
               s.id AS staff_id, s.name AS staff_name, s.email AS staff_email
        FROM requests r
        JOIN users u ON u.id = r.student_id
        LEFT JOIN users s ON s.id = r.assigned_to
        WHERE r.id = ?
    ");
  $stmt->execute([$requestId]);
  $data = $stmt->fetch();
  if (!$data) return;

  $stars   = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
  $msg     = "{$data['student_name']} reviewed \"{$data['title']}\": {$stars} ({$rating}/5).";
  $ins     = $db->prepare("INSERT INTO notifications (user_id, request_id, message) VALUES (?,?,?)");
  $subject = "[HostelIQ] New Review ({$rating}/5) — {$data['title']}";

  // 1) Notify all admins
  $admins = $db->query("SELECT id, name, email FROM users WHERE role='admin' AND is_active=1")->fetchAll();
  foreach ($admins as $a) {
    $ins->execute([$a['id'], $requestId, $msg]);
    $html = reviewEmailTemplate($a['name'], $data['student_name'], $data['title'], $rating, $comment, $requestId);
    sendEmail($a['email'], $a['name'], $subject, $html);
  }

  // 2) Notify the staff member who did the work, with a slightly different message
  if (!empty($data['staff_id'])) {
    $staffMsg = "{$data['student_name']} reviewed your work on \"{$data['title']}\": {$stars} ({$rating}/5).";
    $ins->execute([$data['staff_id'], $requestId, $staffMsg]);
    $staffSubject = "[HostelIQ] You got a {$rating}-star review — {$data['title']}";
    $staffHtml    = staffReviewEmailTemplate($data['staff_name'], $data['student_name'], $data['title'], $rating, $comment, $requestId);
    sendEmail($data['staff_email'], $data['staff_name'], $staffSubject, $staffHtml);
  }
}

function staffReviewEmailTemplate(string $staffName, string $studentName, string $title, int $rating, string $comment, int $reqId): string
{
  $url   = SITE_URL . "/staff/task_details.php?id={$reqId}";
  $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
  $commentBlock = $comment !== ''
    ? '<p style="background:#fffbeb;padding:12px;border-left:4px solid #D4AF37;border-radius:4px;font-style:italic;">'
    . htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') . '</p>'
    : '';
  return <<<HTML
    <!DOCTYPE html><html><body style="font-family:sans-serif;background:#f4f4f4;padding:24px;">
    <div style="max-width:560px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;">
      <div style="background:#8B0000;padding:24px;text-align:center;">
        <h2 style="color:#fff;margin:0;">HostelIQ — You got a review</h2>
      </div>
      <div style="padding:28px;">
        <p>Hi <strong>{$staffName}</strong>,</p>
        <p><strong>{$studentName}</strong> just rated the work you completed:</p>
        <div style="background:#f8f9fa;padding:16px;border-radius:6px;margin:16px 0;">
          <strong>{$title}</strong><br>
          <span style="color:#D4AF37;font-size:20px;letter-spacing:2px;">{$stars}</span>
          <span style="color:#555;margin-left:6px;">({$rating}/5)</span>
        </div>
        {$commentBlock}
        <a href="{$url}" style="display:inline-block;background:#8B0000;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;">View Task</a>
        <p style="color:#999;font-size:12px;margin-top:24px;">Ashesi University — Smart Hostel Management System</p>
      </div>
    </div></body></html>
HTML;
}

function emailTemplate(string $name, string $title, string $oldStatus, string $newStatus, int $reqId): string
{
  $url = SITE_URL . "/student/request_details.php?id={$reqId}";
  $colors = ['Pending' => '#FFC107', 'In Progress' => '#007BFF', 'Completed' => '#28A745'];
  $color = $colors[$newStatus] ?? '#8B0000';
  return <<<HTML
    <!DOCTYPE html><html><body style="font-family:sans-serif;background:#f4f4f4;padding:24px;">
    <div style="max-width:560px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;">
      <div style="background:#8B0000;padding:24px;text-align:center;">
        <h2 style="color:#fff;margin:0;">HostelIQ — Request Update</h2>
      </div>
      <div style="padding:28px;">
        <p>Hi <strong>{$name}</strong>,</p>
        <p>Your maintenance request has been updated:</p>
        <div style="background:#f8f9fa;padding:16px;border-radius:6px;margin:16px 0;">
          <strong>{$title}</strong><br>
          <span style="color:#555;">Status changed:</span>
          <span style="text-decoration:line-through;color:#999;">{$oldStatus}</span> →
          <span style="background:{$color};color:#fff;padding:2px 10px;border-radius:99px;font-size:13px;">{$newStatus}</span>
        </div>
        <a href="{$url}" style="display:inline-block;background:#8B0000;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;margin-top:8px;">View Request</a>
        <p style="color:#999;font-size:12px;margin-top:24px;">Ashesi University — Smart Hostel Management System</p>
      </div>
    </div></body></html>
HTML;
}

function assignmentEmailTemplate(string $name, string $title, string $staffName, int $reqId): string
{
  $url = SITE_URL . "/student/request_details.php?id={$reqId}";
  return <<<HTML
    <!DOCTYPE html><html><body style="font-family:sans-serif;background:#f4f4f4;padding:24px;">
    <div style="max-width:560px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;">
      <div style="background:#8B0000;padding:24px;text-align:center;">
        <h2 style="color:#fff;margin:0;">HostelIQ — Staff Assigned</h2>
      </div>
      <div style="padding:28px;">
        <p>Hi <strong>{$name}</strong>,</p>
        <p>A maintenance staff member has been assigned to your request:</p>
        <div style="background:#f8f9fa;padding:16px;border-radius:6px;margin:16px 0;">
          <strong>{$title}</strong><br>
          <span style="color:#555;">Assigned to:</span> <strong>{$staffName}</strong>
        </div>
        <a href="{$url}" style="display:inline-block;background:#8B0000;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;">View Request</a>
        <p style="color:#999;font-size:12px;margin-top:24px;">Ashesi University — Smart Hostel Management System</p>
      </div>
    </div></body></html>
HTML;
}

function staffAssignmentEmailTemplate(string $staffName, string $title, int $reqId): string
{
  $url = SITE_URL . "/staff/task_details.php?id={$reqId}";
  return <<<HTML
    <!DOCTYPE html><html><body style="font-family:sans-serif;background:#f4f4f4;padding:24px;">
    <div style="max-width:560px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;">
      <div style="background:#8B0000;padding:24px;text-align:center;">
        <h2 style="color:#fff;margin:0;">HostelIQ — New Task Assigned</h2>
      </div>
      <div style="padding:28px;">
        <p>Hi <strong>{$staffName}</strong>,</p>
        <p>You've been assigned a new maintenance task:</p>
        <div style="background:#f8f9fa;padding:16px;border-radius:6px;margin:16px 0;">
          <strong>{$title}</strong>
        </div>
        <a href="{$url}" style="display:inline-block;background:#8B0000;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;">Open Task</a>
        <p style="color:#999;font-size:12px;margin-top:24px;">Ashesi University — Smart Hostel Management System</p>
      </div>
    </div></body></html>
HTML;
}

function adminNewRequestEmailTemplate(string $adminName, string $studentName, string $title, string $category, int $reqId): string
{
  $url = SITE_URL . "/admin/request_details.php?id={$reqId}";
  return <<<HTML
    <!DOCTYPE html><html><body style="font-family:sans-serif;background:#f4f4f4;padding:24px;">
    <div style="max-width:560px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;">
      <div style="background:#8B0000;padding:24px;text-align:center;">
        <h2 style="color:#fff;margin:0;">HostelIQ — New Maintenance Request</h2>
      </div>
      <div style="padding:28px;">
        <p>Hi <strong>{$adminName}</strong>,</p>
        <p>A new request was just submitted:</p>
        <div style="background:#f8f9fa;padding:16px;border-radius:6px;margin:16px 0;">
          <strong>{$title}</strong><br>
          <span style="color:#555;">Submitted by:</span> {$studentName}<br>
          <span style="color:#555;">Category:</span> {$category}
        </div>
        <a href="{$url}" style="display:inline-block;background:#8B0000;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;">Review & Assign</a>
        <p style="color:#999;font-size:12px;margin-top:24px;">Ashesi University — Smart Hostel Management System</p>
      </div>
    </div></body></html>
HTML;
}

function adminStatusEmailTemplate(string $adminName, string $studentName, string $title, string $newStatus, int $reqId): string
{
  $url = SITE_URL . "/admin/request_details.php?id={$reqId}";
  $colors = ['Pending' => '#FFC107', 'In Progress' => '#007BFF', 'Completed' => '#28A745'];
  $color = $colors[$newStatus] ?? '#8B0000';
  return <<<HTML
    <!DOCTYPE html><html><body style="font-family:sans-serif;background:#f4f4f4;padding:24px;">
    <div style="max-width:560px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;">
      <div style="background:#8B0000;padding:24px;text-align:center;">
        <h2 style="color:#fff;margin:0;">HostelIQ — Status Update</h2>
      </div>
      <div style="padding:28px;">
        <p>Hi <strong>{$adminName}</strong>,</p>
        <p>A request assigned to staff has changed status:</p>
        <div style="background:#f8f9fa;padding:16px;border-radius:6px;margin:16px 0;">
          <strong>{$title}</strong><br>
          <span style="color:#555;">Student:</span> {$studentName}<br>
          <span style="color:#555;">New status:</span>
          <span style="background:{$color};color:#fff;padding:2px 10px;border-radius:99px;font-size:13px;">{$newStatus}</span>
        </div>
        <a href="{$url}" style="display:inline-block;background:#8B0000;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;">View Request</a>
        <p style="color:#999;font-size:12px;margin-top:24px;">Ashesi University — Smart Hostel Management System</p>
      </div>
    </div></body></html>
HTML;
}

/** Admin nudges the assigned staff member to act on this request. */
function remindStaff(int $requestId, string $message = ''): array
{
  $db = getDB();
  $stmt = $db->prepare("
        SELECT r.title, r.student_id, r.assigned_to,
               s.name AS staff_name, s.email AS staff_email
        FROM requests r
        LEFT JOIN users s ON s.id = r.assigned_to
        WHERE r.id = ?
    ");
  $stmt->execute([$requestId]);
  $data = $stmt->fetch();
  if (!$data || !$data['assigned_to']) {
    return ['ok' => false, 'error' => 'No staff is assigned to this request yet.'];
  }

  $tail = $message !== '' ? " — \"{$message}\"" : '';

  // Notify staff (in-app + email)
  $db->prepare("INSERT INTO notifications (user_id, request_id, message) VALUES (?,?,?)")
    ->execute([
      $data['assigned_to'],
      $requestId,
      "Admin reminder on task \"{$data['title']}\"{$tail}"
    ]);

  $subject = "[HostelIQ] Admin reminder — {$data['title']}";
  $html    = staffReminderEmailTemplate($data['staff_name'], $data['title'], $message, $requestId);
  @sendEmail($data['staff_email'], $data['staff_name'], $subject, $html);

  // Tell the student their admin is pushing things forward
  if ($data['student_id']) {
    $db->prepare("INSERT INTO notifications (user_id, request_id, message) VALUES (?,?,?)")
      ->execute([
        $data['student_id'],
        $requestId,
        "Admin followed up with staff on your request \"{$data['title']}\"."
      ]);
  }

  return ['ok' => true];
}

function staffReminderEmailTemplate(string $staffName, string $title, string $note, int $reqId): string
{
  $url      = SITE_URL . "/staff/task_details.php?id={$reqId}";
  $noteHtml = $note !== ''
    ? '<p style="background:#fffbeb;padding:12px;border-left:4px solid #D4AF37;border-radius:4px;font-style:italic;">'
    . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</p>'
    : '';
  return <<<HTML
    <!DOCTYPE html><html><body style="font-family:sans-serif;background:#f4f4f4;padding:24px;">
    <div style="max-width:560px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;">
      <div style="background:#8B0000;padding:24px;text-align:center;">
        <h2 style="color:#fff;margin:0;">HostelIQ — Admin Reminder</h2>
      </div>
      <div style="padding:28px;">
        <p>Hi <strong>{$staffName}</strong>,</p>
        <p>An admin has sent a reminder about a task assigned to you:</p>
        <div style="background:#f8f9fa;padding:16px;border-radius:6px;margin:16px 0;">
          <strong>{$title}</strong>
        </div>
        {$noteHtml}
        <a href="{$url}" style="display:inline-block;background:#8B0000;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;">Open Task</a>
        <p style="color:#999;font-size:12px;margin-top:24px;">Ashesi University — Smart Hostel Management System</p>
      </div>
    </div></body></html>
HTML;
}

function reviewEmailTemplate(string $adminName, string $studentName, string $title, int $rating, string $comment, int $reqId): string
{
  $url = SITE_URL . "/admin/request_details.php?id={$reqId}";
  $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
  $commentBlock = $comment ? "<p style=\"background:#fffbeb;padding:12px;border-left:4px solid #D4AF37;border-radius:4px;font-style:italic;\">" . htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') . "</p>" : '';
  return <<<HTML
    <!DOCTYPE html><html><body style="font-family:sans-serif;background:#f4f4f4;padding:24px;">
    <div style="max-width:560px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;">
      <div style="background:#8B0000;padding:24px;text-align:center;">
        <h2 style="color:#fff;margin:0;">HostelIQ — New Review</h2>
      </div>
      <div style="padding:28px;">
        <p>Hi <strong>{$adminName}</strong>,</p>
        <p><strong>{$studentName}</strong> just reviewed a completed request:</p>
        <div style="background:#f8f9fa;padding:16px;border-radius:6px;margin:16px 0;">
          <strong>{$title}</strong><br>
          <span style="color:#D4AF37;font-size:20px;letter-spacing:2px;">{$stars}</span>
          <span style="color:#555;margin-left:6px;">({$rating}/5)</span>
        </div>
        {$commentBlock}
        <a href="{$url}" style="display:inline-block;background:#8B0000;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;">View Request</a>
        <p style="color:#999;font-size:12px;margin-top:24px;">Ashesi University — Smart Hostel Management System</p>
      </div>
    </div></body></html>
HTML;
}
