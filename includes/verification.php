<?php
// =====================================================
// Email verification — OTP creation, send, check
// =====================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/schema.php';

function generateOTP(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Create/replace a verification code for a user, send it by email.
 * Returns an array: ['code' => '123456', 'sent' => bool, 'error' => string|null]
 */
function createAndSendOTP(int $userId): array {
    ensureExtendedSchema();
    $db = getDB();

    $code    = generateOTP();
    $expires = date('Y-m-d H:i:s', time() + OTP_TTL_MINUTES * 60);

    $db->prepare("
        INSERT INTO email_verifications (user_id, code, expires_at, attempts) VALUES (?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE code = VALUES(code), expires_at = VALUES(expires_at), attempts = 0
    ")->execute([$userId, $code, $expires]);

    $sent  = false;
    $error = null;

    $u = $db->prepare("SELECT name, email FROM users WHERE id = ?");
    $u->execute([$userId]);
    $user = $u->fetch();
    if ($user) {
        $subject = "[HostelIQ] Your verification code: {$code}";
        $html    = verificationEmailTemplate($user['name'], $code);
        $sent    = sendEmail($user['email'], $user['name'], $subject, $html);
        if (!$sent) {
            $error = $GLOBALS['LAST_EMAIL_ERROR'] ?? 'Email delivery failed';
        }
    }

    return ['code' => $code, 'sent' => $sent, 'error' => $error];
}

/**
 * Attempt to verify a user-supplied code. Returns one of:
 *   ['ok'     => true]                                — verified successfully
 *   ['error'  => 'expired' | 'invalid' | 'no_code' | 'too_many_attempts']
 */
function verifyOTP(int $userId, string $code): array {
    ensureExtendedSchema();
    $db = getDB();

    $stmt = $db->prepare("SELECT code, expires_at, attempts FROM email_verifications WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) return ['error' => 'no_code'];

    if ($row['attempts'] >= 5) {
        return ['error' => 'too_many_attempts'];
    }
    if (strtotime($row['expires_at']) < time()) {
        return ['error' => 'expired'];
    }

    if (hash_equals($row['code'], trim($code))) {
        $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$userId]);
        $db->prepare("DELETE FROM email_verifications WHERE user_id = ?")->execute([$userId]);
        return ['ok' => true];
    }

    $db->prepare("UPDATE email_verifications SET attempts = attempts + 1 WHERE user_id = ?")->execute([$userId]);
    return ['error' => 'invalid'];
}

/**
 * Seconds until the user is allowed to request another code.
 * Returns 0 if they can resend now.
 */
function resendCooldownSeconds(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT updated_at FROM email_verifications WHERE user_id = ?");
    $stmt->execute([$userId]);
    $last = $stmt->fetchColumn();
    if (!$last) return 0;
    $elapsed = time() - strtotime($last);
    return max(0, OTP_RESEND_COOLDOWN_SECONDS - $elapsed);
}

function verificationEmailTemplate(string $name, string $code): string {
    return <<<HTML
    <!DOCTYPE html><html><body style="font-family:sans-serif;background:#f4f4f4;padding:24px;">
    <div style="max-width:520px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;">
      <div style="background:#8B0000;padding:24px;text-align:center;">
        <h2 style="color:#fff;margin:0;">HostelIQ — Email Verification</h2>
      </div>
      <div style="padding:28px;text-align:center;">
        <p style="text-align:left;">Hi <strong>{$name}</strong>,</p>
        <p style="text-align:left;">Welcome! Use the code below to finish creating your account:</p>
        <div style="font-size:34px;font-weight:800;letter-spacing:8px;background:#fef2f2;color:#8B0000;padding:18px 22px;border-radius:10px;display:inline-block;margin:16px 0;">
          {$code}
        </div>
        <p style="color:#555;font-size:13px;text-align:left;">This code will expire in 10 minutes. If you didn't request it, ignore this email.</p>
        <p style="color:#999;font-size:12px;margin-top:24px;">Ashesi University — Smart Hostel Management System</p>
      </div>
    </div></body></html>
HTML;
}
