<?php
/**
 * Helper Functions
 * Smart Hostel Maintenance System
 */

/**
 * Sanitize user input
 */
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect with optional flash message
 */
function redirect(string $url, string $message = '', string $type = 'success'): void {
    if ($message) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
    header("Location: $url");
    exit;
}

/**
 * Get and clear flash message
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Format date for display
 */
function formatDate(string $date, string $format = 'M d, Y'): string {
    return date($format, strtotime($date));
}

/**
 * Format relative time (e.g., "2 hours ago")
 */
function timeAgo(string $datetime): string {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Get status badge HTML
 */
function statusBadge(string $status): string {
    $classes = match($status) {
        'Pending'     => 'badge badge-pending',
        'In Progress' => 'badge badge-progress',
        'Completed'   => 'badge badge-completed',
        default       => 'badge'
    };
    return "<span class=\"$classes\">$status</span>";
}

/**
 * Get priority badge HTML
 */
function priorityBadge(?string $priority): string {
    if (!$priority) return '<span class="badge badge-none">Unset</span>';
    $classes = match($priority) {
        'High'   => 'badge badge-high',
        'Medium' => 'badge badge-medium',
        'Low'    => 'badge badge-low',
        default  => 'badge'
    };
    return "<span class=\"$classes\">$priority</span>";
}

/**
 * Handle file upload for request images
 */
function handleImageUpload(array $file, string $prefix = 'req_'): array {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload failed. Please try again.'];
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File size must be under 5MB.'];
    }

    // Validate MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ALLOWED_TYPES)) {
        return ['success' => false, 'error' => 'Only JPEG and PNG images are allowed.'];
    }

    // Validate extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Invalid file extension.'];
    }

    // Verify image integrity
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['success' => false, 'error' => 'File is not a valid image.'];
    }

    // Generate unique filename
    $filename = uniqid($prefix, true) . '.' . $ext;
    $uploadPath = UPLOAD_DIR . $filename;

    // Ensure upload directory exists
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file.'];
    }

    return ['success' => true, 'path' => 'backend/uploads/' . $filename];
}

/**
 * Send JSON response (for AJAX)
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Generate CSRF hidden input for forms
 */
function csrfField(): string {
    $token = Auth::getCSRFToken();
    return "<input type=\"hidden\" name=\"csrf_token\" value=\"$token\">";
}
