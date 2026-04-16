<?php
/**
 * Structured Logger
 * Smart Hostel Maintenance System
 */

class Logger {
    private static string $logDir = __DIR__ . '/../../logs/';

    private static function write(string $level, string $message, array $context = []): void {
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }

        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => $level,
            'message'   => $message,
            'user_id'   => $_SESSION['user_id'] ?? null,
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'route'     => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'method'    => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
        ];

        if (!empty($context)) {
            // Never log passwords or tokens
            unset($context['password'], $context['password_hash'], $context['token']);
            $entry['context'] = $context;
        }

        $filename = self::$logDir . date('Y-m-d') . '.log';
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($filename, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::write('ERROR', $message, $context);
    }

    public static function activity(string $action, string $entityType, ?int $entityId = null, ?array $details = null): void {
        try {
            $db = Database::getInstance();
            $db->insert(
                "INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, ip_address) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $_SESSION['user_id'] ?? null,
                    $action,
                    $entityType,
                    $entityId,
                    $details ? json_encode($details) : null,
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]
            );
        } catch (Exception $e) {
            self::error('Failed to log activity', ['error' => $e->getMessage()]);
        }
    }
}
