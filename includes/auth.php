<?php
// =====================================================
// Auth & Session Helper
// =====================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/schema.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(string $role = ''): void {
    // Make sure the DB schema is up to date before any auth-protected page runs —
    // this adds avatar_path, is_verified, email_verifications, reviews, reminders
    // on the first visit after deployment.
    ensureExtendedSchema();

    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
    if ($role && $_SESSION['role'] !== $role) {
        header('Location: ' . SITE_URL . '/login.php?error=unauthorized');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION ?? [];
}

function getUserById(int $id): ?array {
    $db = getDB();
    try {
        $stmt = $db->prepare("
            SELECT id, name, email, role, room_number, is_active, created_at,
                   avatar_path, is_verified
            FROM users WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        // Fallback if the new columns haven't been migrated yet (e.g. DB user lacks ALTER rights)
        $stmt = $db->prepare("SELECT id, name, email, role, room_number, is_active, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch() ?: null;
        if ($user) {
            $user['avatar_path'] = null;
            $user['is_verified'] = 1;
        }
        return $user;
    }
}

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function setFlash(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

function getFlash(string $key): ?string {
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function statusBadge(string $status): string {
    $classes = [
        'Pending'     => 'badge-warning',
        'In Progress' => 'badge-info',
        'Completed'   => 'badge-success',
    ];
    $cls = $classes[$status] ?? 'badge-secondary';
    return "<span class='badge {$cls}'>{$status}</span>";
}

function priorityBadge(?string $priority): string {
    if (!$priority) return '<span class="badge badge-secondary">Not Set</span>';
    $classes = ['High' => 'badge-danger', 'Medium' => 'badge-warning', 'Low' => 'badge-success'];
    $cls = $classes[$priority] ?? 'badge-secondary';
    return "<span class='badge {$cls}'>{$priority}</span>";
}

function timeAgo(string $datetime): string {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    return floor($time/86400) . 'd ago';
}
