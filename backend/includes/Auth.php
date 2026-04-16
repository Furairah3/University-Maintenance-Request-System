<?php
/**
 * Authentication & Authorization Class
 * Handles login, registration, session management, RBAC
 * Smart Hostel Maintenance System
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

class Auth {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Initialize secure session
     */
    public static function initSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
            ini_set('session.use_strict_mode', 1);
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
            session_name(SESSION_NAME);
            session_start();
        }

        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            $elapsed = time() - $_SESSION['last_activity'];
            if ($elapsed > SESSION_LIFETIME) {
                self::destroySession();
                header('Location: ' . APP_URL . '/login.php?timeout=1');
                exit;
            }
        }
        $_SESSION['last_activity'] = time();
    }

    /**
     * Register a new student
     */
    public function register(string $name, string $email, string $password): array {
        // Validate email format (must be university email)
        if (!$this->isValidUniversityEmail($email)) {
            return ['success' => false, 'error' => 'Please use a valid university email address (@ashesi.edu.gh).'];
        }

        // Check password strength
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters long.'];
        }

        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return ['success' => false, 'error' => 'Password must contain at least one uppercase letter and one number.'];
        }

        // Check if email already exists
        $existing = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            return ['success' => false, 'error' => 'An account with this email already exists.'];
        }

        // Sanitize name
        $name = htmlspecialchars(strip_tags(trim($name)), ENT_QUOTES, 'UTF-8');
        if (strlen($name) < 2 || strlen($name) > 100) {
            return ['success' => false, 'error' => 'Name must be between 2 and 100 characters.'];
        }

        // Hash password with bcrypt
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        try {
            $userId = $this->db->insert(
                "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')",
                [$name, strtolower(trim($email)), $passwordHash]
            );

            Logger::info('New student registered', ['user_id' => $userId, 'email' => $email]);
            Logger::activity('register', 'user', $userId);

            return ['success' => true, 'user_id' => $userId, 'message' => 'Registration successful! You can now log in.'];
        } catch (Exception $e) {
            Logger::error('Registration failed', ['email' => $email, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Registration failed. Please try again.'];
        }
    }

    /**
     * Login user
     */
    public function login(string $email, string $password): array {
        $email = strtolower(trim($email));

        // Fetch user
        $user = $this->db->fetchOne(
            "SELECT id, name, email, password_hash, role, is_active, profile_image FROM users WHERE email = ?",
            [$email]
        );

        if (!$user) {
            Logger::warning('Login failed: unknown email', ['email' => $email]);
            return ['success' => false, 'error' => 'Invalid email or password.'];
        }

        // Check if account is active
        if (!$user['is_active']) {
            Logger::warning('Login attempt on deactivated account', ['user_id' => $user['id']]);
            return ['success' => false, 'error' => 'This account has been deactivated. Contact an administrator.'];
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            Logger::warning('Login failed: wrong password', ['user_id' => $user['id']]);
            return ['success' => false, 'error' => 'Invalid email or password.'];
        }

        // Rehash if needed (cost upgrade)
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $this->db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$newHash, $user['id']]);
        }

        // Update last login
        $this->db->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

        // Set session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_profile_image'] = $user['profile_image'];
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        Logger::info('User logged in', ['user_id' => $user['id'], 'role' => $user['role']]);
        Logger::activity('login', 'user', $user['id']);

        // Determine redirect based on role
        $redirect = match($user['role']) {
            'admin' => APP_URL . '/admin/dashboard.php',
            'staff' => APP_URL . '/staff/dashboard.php',
            default => APP_URL . '/student/dashboard.php',
        };

        return ['success' => true, 'redirect' => $redirect, 'role' => $user['role']];
    }

    /**
     * Logout
     */
    public static function logout(): void {
        if (isset($_SESSION['user_id'])) {
            Logger::info('User logged out', ['user_id' => $_SESSION['user_id']]);
            Logger::activity('logout', 'user', $_SESSION['user_id']);
        }
        self::destroySession();
    }

    /**
     * Destroy session completely
     */
    private static function destroySession(): void {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"],
                $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
    }

    /**
     * Get current user's role
     */
    public static function getRole(): ?string {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Get current user's ID
     */
    public static function getUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user's name
     */
    public static function getUserName(): ?string {
        return $_SESSION['user_name'] ?? null;
    }

    /**
     * Get current user's profile image path (relative to APP_URL), or null
     */
    public static function getProfileImage(): ?string {
        return $_SESSION['user_profile_image'] ?? null;
    }

    /**
     * Enforce role-based access control (middleware)
     * Call at the top of protected pages
     */
    public static function requireRole(string ...$allowedRoles): void {
        self::initSession();

        if (!self::isLoggedIn()) {
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }

        $userRole = self::getRole();
        if (!in_array($userRole, $allowedRoles)) {
            Logger::warning('Unauthorized access attempt', [
                'user_id' => self::getUserId(),
                'role' => $userRole,
                'required' => $allowedRoles,
                'page' => $_SERVER['REQUEST_URI']
            ]);
            http_response_code(403);
            include __DIR__ . '/../../frontend/errors/403.php';
            exit;
        }
    }

    /**
     * Validate CSRF token
     */
    public static function validateCSRF(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Get CSRF token for forms
     */
    public static function getCSRFToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate university email format
     */
    private function isValidUniversityEmail(string $email): bool {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        // Must end with @ashesi.edu.gh
        return str_ends_with(strtolower($email), '@ashesi.edu.gh');
    }

    /**
     * Create a staff account (admin only)
     */
    public function createStaffAccount(string $name, string $email, string $password): array {
        $name = htmlspecialchars(strip_tags(trim($name)), ENT_QUOTES, 'UTF-8');
        $email = strtolower(trim($email));

        $existing = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            return ['success' => false, 'error' => 'An account with this email already exists.'];
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        try {
            $userId = $this->db->insert(
                "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'staff')",
                [$name, $email, $passwordHash]
            );

            Logger::info('Staff account created', ['staff_id' => $userId, 'created_by' => self::getUserId()]);
            Logger::activity('create_staff', 'user', $userId);

            return ['success' => true, 'user_id' => $userId, 'message' => 'Staff account created successfully.'];
        } catch (Exception $e) {
            Logger::error('Staff creation failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to create staff account.'];
        }
    }

    /**
     * Get unread notification count for current user
     */
    public static function getUnreadNotificationCount(): int {
        try {
            $db = Database::getInstance();
            $result = $db->fetchOne(
                "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
                [self::getUserId()]
            );
            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
}
