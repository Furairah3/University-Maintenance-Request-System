<?php
// =====================================================
// Minimal SMTP client — no dependencies, supports STARTTLS + AUTH LOGIN
// Good enough for Gmail, Outlook, Mailtrap, Brevo, etc.
// =====================================================
require_once __DIR__ . '/../config/db.php';

function smtpConfigured(): bool {
    return defined('SMTP_HOST') && defined('SMTP_USER') && defined('SMTP_PASS')
        && SMTP_HOST !== ''
        && SMTP_USER !== ''
        && SMTP_USER !== 'your_email@gmail.com'  // unchanged placeholder
        && SMTP_PASS !== ''
        && SMTP_PASS !== 'your_app_password';
}

/**
 * Send an email via SMTP. Returns:
 *   ['ok' => true,  'log' => [..., ...]]
 *   ['ok' => false, 'error' => '...reason...', 'log' => [..., ...]]
 *
 * 'log' contains the full conversation so callers can show it for debugging.
 */
function smtpSend(string $toEmail, string $toName, string $subject, string $htmlBody): array {
    $log = [];
    $logLine = function (string $dir, string $line) use (&$log) {
        // Strip trailing CRLF for cleaner display
        $line = rtrim($line, "\r\n");
        // Hide the base64-encoded password
        if (preg_match('/^[A-Za-z0-9+\/=]{20,}$/', $line) && count($log) >= 2) {
            $prev = $log[count($log) - 1] ?? '';
            if (strpos($prev, '334') !== false) $line = '(base64 credential — hidden)';
        }
        $log[] = $dir . ' ' . $line;
    };

    if (!smtpConfigured()) {
        return ['ok' => false, 'error' => 'SMTP is not configured in config/db.php', 'log' => $log];
    }
    if (!extension_loaded('openssl')) {
        return ['ok' => false, 'error' => 'PHP openssl extension is not loaded — required for STARTTLS. Enable ;extension=openssl in php.ini and restart Apache.', 'log' => $log];
    }

    $host     = SMTP_HOST;
    $port     = (int)SMTP_PORT;
    $user     = SMTP_USER;
    $pass     = SMTP_PASS;
    $from     = SMTP_USER;
    $fromName = FROM_NAME;

    $errno = 0; $errstr = '';
    $logLine('··', "Connecting to tcp://{$host}:{$port} ...");
    $fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 15);
    if (!$fp) {
        return [
            'ok'    => false,
            'error' => "Connect to {$host}:{$port} failed — {$errstr} (errno {$errno}). Firewall or antivirus may be blocking outbound port {$port}.",
            'log'   => $log,
        ];
    }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp, $logLine) {
        $resp = '';
        while (!feof($fp) && ($line = fgets($fp, 4096)) !== false) {
            $resp .= $line;
            $logLine('←', $line);
            // SMTP multi-line: "250-..." means more; "250 ..." means last
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $resp;
    };

    $write = function (string $cmd) use ($fp, $logLine) {
        $logLine('→', $cmd);
        fwrite($fp, $cmd . "\r\n");
    };

    $expect = function (string $code) use ($read) {
        $resp = $read();
        if (strncmp($resp, $code, 3) !== 0) {
            throw new RuntimeException("Expected {$code}, got: " . trim(substr($resp, 0, 300)));
        }
        return $resp;
    };

    try {
        $expect('220');
        $write("EHLO localhost");  $expect('250');
        $write("STARTTLS");         $expect('220');

        $cryptoOk = stream_socket_enable_crypto(
            $fp, true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );
        $logLine('··', 'TLS handshake: ' . ($cryptoOk ? 'OK' : 'FAILED'));
        if (!$cryptoOk) {
            throw new RuntimeException('TLS handshake failed after STARTTLS. Check that OpenSSL works and the system clock is correct.');
        }

        // Re-announce EHLO after TLS
        $write("EHLO localhost"); $expect('250');

        $write("AUTH LOGIN");        $expect('334');
        $write(base64_encode($user));  $expect('334');
        $write(base64_encode($pass));  $expect('235');

        $write("MAIL FROM: <{$from}>");            $expect('250');
        $write("RCPT TO: <{$toEmail}>");           $expect('250');
        $write("DATA");                             $expect('354');

        $msgId = '<' . bin2hex(random_bytes(8)) . '@' . parse_url(SITE_URL, PHP_URL_HOST) . '>';
        $headers  = "From: \"" . addslashes($fromName) . "\" <{$from}>\r\n";
        $headers .= "To: \"" . addslashes($toName) . "\" <{$toEmail}>\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: {$msgId}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $body = preg_replace("/\r?\n/", "\r\n", $htmlBody);
        $body = preg_replace('/^\./m', '..', $body);

        $logLine('→', '(headers + ' . strlen($body) . '-byte body)');
        fwrite($fp, $headers . "\r\n" . $body . "\r\n.\r\n");
        $expect('250');

        $write("QUIT");
        @fclose($fp);
        return ['ok' => true, 'log' => $log];
    } catch (Throwable $e) {
        @fclose($fp);
        return ['ok' => false, 'error' => $e->getMessage(), 'log' => $log];
    }
}
