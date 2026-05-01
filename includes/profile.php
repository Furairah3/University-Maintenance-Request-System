<?php
// =====================================================
// Profile helpers — avatar upload, avatar URL, member-since
// =====================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

function avatarDir(): string {
    return rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . 'avatars' . DIRECTORY_SEPARATOR;
}

function avatarUrl(?array $user): string {
    if (!empty($user['avatar_path'])) {
        return rtrim(UPLOAD_URL, '/') . '/avatars/' . rawurlencode($user['avatar_path']);
    }
    // Default placeholder — a data-URI SVG with the user's initials in the brand color
    $initials = '';
    if (!empty($user['name'])) {
        $parts = preg_split('/\s+/', trim($user['name']));
        $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
    }
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160">'
        . '<rect width="160" height="160" fill="#8B0000"/>'
        . '<text x="50%" y="54%" text-anchor="middle" font-family="Poppins,Arial,sans-serif" font-size="64" font-weight="700" fill="#fff">'
        . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8')
        . '</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function memberSinceText(?array $user): string {
    if (empty($user['created_at'])) return '—';
    return date('F Y', strtotime($user['created_at']));
}

/**
 * Handle an avatar upload. Returns the stored filename on success, or an error string.
 * Pass $_FILES['avatar'] as $file.
 */
function handleAvatarUpload(int $userId, array $file): string|true {
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return true; // nothing to do — not an error
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Upload failed. Please try again.';
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return 'Profile picture must be under 2MB.';
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $mime = mime_content_type($file['tmp_name']) ?: $file['type'];
    if (!isset($allowed[$mime])) {
        return 'Only JPEG, PNG, GIF, or WEBP images are allowed.';
    }

    $dir = avatarDir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return 'Server is not configured to store avatars.';
    }

    $ext      = $allowed[$mime];
    $filename = 'avatar_' . $userId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest     = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return 'Could not save the uploaded file.';
    }

    // Remove previous avatar file (best-effort)
    $db = getDB();
    $old = $db->prepare("SELECT avatar_path FROM users WHERE id = ?");
    $old->execute([$userId]);
    $oldPath = $old->fetchColumn();
    if ($oldPath) {
        $oldFile = $dir . $oldPath;
        if (is_file($oldFile)) @unlink($oldFile);
    }

    $db->prepare("UPDATE users SET avatar_path = ? WHERE id = ?")->execute([$filename, $userId]);
    return true;
}

function deleteAvatar(int $userId): void {
    $db = getDB();
    $stmt = $db->prepare("SELECT avatar_path FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $path = $stmt->fetchColumn();
    if ($path) {
        $file = avatarDir() . $path;
        if (is_file($file)) @unlink($file);
    }
    $db->prepare("UPDATE users SET avatar_path = NULL WHERE id = ?")->execute([$userId]);
}
