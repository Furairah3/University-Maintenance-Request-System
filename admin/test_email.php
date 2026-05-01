<?php
// =====================================================
// Email diagnostic — admin-only. Sends a test email and
// shows the raw SMTP outcome so you can debug delivery.
// =====================================================
require_once '../includes/auth.php';
require_once '../includes/schema.php';
require_once '../includes/smtp.php';
require_once '../includes/email.php';
requireLogin('admin');
ensureExtendedSchema();

$activePage = 'settings';

$to        = '';
$ok        = null;
$error     = null;
$transport = null;
$log       = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $subject = '[HostelIQ] Test email — ' . date('Y-m-d H:i:s');
        $html    = '<html><body style="font-family:sans-serif;padding:20px;">
            <h2 style="color:#8B0000;">It works! 🎉</h2>
            <p>This is a test email from your HostelIQ installation.</p>
            <p>If you can see this, SMTP is correctly configured and email is being delivered.</p>
            <p style="color:#888;font-size:12px;">Sent at ' . date('r') . '</p>
            </body></html>';

        if (smtpConfigured()) {
            $r         = smtpSend($to, 'Test Recipient', $subject, $html);
            $ok        = !empty($r['ok']);
            $error     = $r['error'] ?? null;
            $log       = $r['log']   ?? [];
            $transport = 'SMTP (' . SMTP_HOST . ':' . SMTP_PORT . ')';
        } else {
            $ok        = @mail($to, $subject, $html, "Content-type: text/html; charset=UTF-8\r\n");
            $error     = $ok ? null : 'PHP mail() returned false (XAMPP has no sendmail configured by default).';
            $transport = 'PHP mail() fallback';
        }
    }
}

// Environment sanity checks
$envChecks = [
    ['PHP version',           PHP_VERSION,                             true],
    ['openssl extension',     extension_loaded('openssl') ? 'loaded' : 'MISSING', extension_loaded('openssl')],
    ['allow_url_fopen',       ini_get('allow_url_fopen') ? 'on' : 'off', true],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Email Test — HostelIQ Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <main class="main-content">
    <div class="top-bar">
      <div><div class="page-title">Email Diagnostic</div><div class="page-subtitle">Verify SMTP is sending real emails</div></div>
      <a href="settings.php" class="btn btn-outline">← Settings</a>
    </div>
    <div class="page-body" style="max-width:680px;">

      <!-- Environment checks -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div class="card-title">Environment</div></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:180px 1fr;gap:6px 14px;font-size:12px;">
            <?php foreach ($envChecks as [$label, $value, $ok_]): ?>
            <strong><?=$label?>:</strong>
            <code style="color:<?=$ok_ ? 'var(--success)' : 'var(--danger)'?>"><?=sanitize($value)?></code>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Current configuration -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div class="card-title">Current SMTP Configuration</div></div>
        <div class="card-body">
          <?php if (smtpConfigured()): ?>
            <p style="font-size:13px;margin-bottom:10px;">
              <span class="badge badge-success">✓ Configured</span>
              Sending via real SMTP.
            </p>
            <div style="display:grid;grid-template-columns:160px 1fr;gap:8px 14px;font-size:12px;padding:12px;background:var(--bg);border-radius:8px;">
              <strong>Host:</strong>       <code><?=sanitize(SMTP_HOST)?></code>
              <strong>Port:</strong>       <code><?=SMTP_PORT?></code>
              <strong>User:</strong>       <code><?=sanitize(SMTP_USER)?></code>
              <strong>From name:</strong>  <code><?=sanitize(FROM_NAME)?></code>
              <strong>Password:</strong>   <code>•••••••••••• (set)</code>
            </div>
          <?php else: ?>
            <p style="font-size:13px;">
              <span class="badge badge-warning">⚠ Not configured</span>
              Emails will silently fail via PHP <code>mail()</code>. Edit <code>config/db.php</code> and set real SMTP credentials.
            </p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Test form -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div class="card-title">Send a test email</div></div>
        <div class="card-body">
          <form method="POST">
            <div class="form-group">
              <label class="form-label">Recipient email</label>
              <input type="email" name="to" class="form-control"
                value="<?=sanitize($to ?: ($_SESSION['email'] ?? ''))?>"
                placeholder="where should we send the test?" required>
              <p class="form-hint">Tip: test with a gmail address first, then with the student's <code>@ashesi.edu.gh</code>.</p>
            </div>
            <button type="submit" class="btn btn-primary">Send Test Email</button>
          </form>
        </div>
      </div>

      <!-- Result -->
      <?php if ($ok !== null): ?>
      <div class="card" style="border-left:4px solid <?=$ok ? 'var(--success)' : 'var(--danger)'?>;">
        <div class="card-body">
          <div style="font-size:15px;font-weight:700;margin-bottom:8px;">
            <?php if ($ok): ?>
              ✓ Email accepted for delivery
            <?php else: ?>
              ✗ Email failed to send
            <?php endif; ?>
          </div>
          <div style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">
            Transport: <strong><?=sanitize($transport)?></strong><br>
            Sent to: <strong><?=sanitize($to)?></strong>
          </div>
          <?php if ($error): ?>
          <div class="alert alert-danger" style="font-size:12px;white-space:pre-wrap;"><strong>SMTP error:</strong> <?=sanitize($error)?></div>
          <?php endif; ?>

          <?php if (!empty($log)): ?>
          <div style="margin-top:14px;">
            <div style="font-size:12px;font-weight:700;margin-bottom:6px;">SMTP conversation transcript</div>
            <pre style="background:#1a0000;color:#eee;padding:14px;border-radius:8px;font-size:11px;line-height:1.45;max-height:400px;overflow:auto;white-space:pre-wrap;word-break:break-word;"><?php
              // Use htmlspecialchars directly — sanitize() strips tag-like <email> addresses
              foreach ($log as $l) echo htmlspecialchars($l, ENT_QUOTES, 'UTF-8') . "\n";
            ?></pre>
            <p style="font-size:11px;color:var(--text-muted);margin-top:6px;">
              Legend: <code>→</code> we sent, <code>←</code> server replied, <code>··</code> client note.
              Passwords are masked.
            </p>
          </div>
          <?php endif; ?>
          <?php if ($ok): ?>
          <div class="alert alert-info" style="font-size:12px;">
            The server accepted the message. If the recipient didn't get it:
            <ul style="margin:6px 0 0 20px;">
              <li>Check the <strong>spam / junk</strong> folder.</li>
              <li>Some university mail systems block unverified Gmail senders via SPF/DMARC. Try sending to a gmail address to confirm SMTP itself is fine.</li>
              <li>Gmail sometimes delays new-sender mails by 1–5 minutes.</li>
            </ul>
          </div>
          <?php else: ?>
          <div class="alert alert-warning" style="font-size:12px;">
            Common fixes:
            <ul style="margin:6px 0 0 20px;">
              <li>In <code>config/db.php</code>: check <code>SMTP_USER</code> and <code>SMTP_PASS</code> are correct. The password must be a 16-char Gmail <strong>App Password</strong>, not your normal password.</li>
              <li>Make sure <strong>openssl</strong> is enabled in <code>php.ini</code> (STARTTLS needs it).</li>
              <li>Check that outbound port <code>587</code> isn't blocked by firewall / antivirus.</li>
            </ul>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
