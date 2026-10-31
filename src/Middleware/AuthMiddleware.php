<?php

namespace App\Middleware;

use App\Config\Database;
use PDO;
use Exception;

/**
 * Authentication and Role-Based Authorization Middleware
 *
 * Provides hardened session lifecycle management, credential verification
 * against bcrypt hashes, and role-based route guard enforcement.
 */
class AuthMiddleware
{
    private const SESSION_NAME = 'CONGOVA_SESSION';
    private const SESSION_LIFETIME = 7200; // 2 hours

    /**
     * Start and configure a secure session if not already active
     */
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        session_name(self::SESSION_NAME);

        session_set_cookie_params([
            'lifetime' => self::SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string)self::SESSION_LIFETIME);

        session_start();

        // Expire inactive sessions
        if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > self::SESSION_LIFETIME)) {
            self::logout();
            return;
        }
        $_SESSION['LAST_ACTIVITY'] = time();
    }

    /**
     * Attempt login verification with email and password
     *
     * @param string $email
     * @param string $password
     * @return array{success: bool, message: string, user: ?array}
     */
    public static function attemptLogin(string $email, string $password): array
    {
        self::initSession();

        $email = trim(filter_var($email, FILTER_SANITIZE_EMAIL));
        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Email and password are required.',
                'user' => null,
            ];
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('
                SELECT 
                    u.id,
                    u.role_id,
                    u.name,
                    u.email,
                    u.password,
                    u.status,
                    r.name AS role_name,
                    r.slug AS role_slug
                FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                WHERE u.email = :email
                LIMIT 1
            ');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid email or password.',
                    'user' => null,
                ];
            }

            if ($user['status'] !== 'active') {
                return [
                    'success' => false,
                    'message' => sprintf('Account is currently %s. Please contact your administrator.', $user['status']),
                    'user' => null,
                ];
            }

            if (!password_verify($password, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid email or password.',
                    'user' => null,
                ];
            }

            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            // Establish authenticated session data
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'role_id' => (int)$user['role_id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role_name'],
                'role_slug' => $user['role_slug'],
            ];
            $_SESSION['LAST_ACTIVITY'] = time();

            // Record audit ledger entry if desired (non-blocking)
            self::logLoginAudit((int)$user['id']);

            return [
                'success' => true,
                'message' => 'Authentication successful.',
                'user' => $_SESSION['user'],
            ];

        } catch (Exception $e) {
            error_log('[Auth Error] ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Authentication system error: ' . $e->getMessage(),
                'user' => null,
            ];
        }
    }

    /**
     * Determine if a user is currently logged in
     */
    public static function isAuthenticated(): bool
    {
        self::initSession();
        return !empty($_SESSION['user']) && !empty($_SESSION['user']['id']);
    }

    /**
     * Get the currently authenticated user record
     *
     * @return array{id: int, role_id: int, name: string, email: string, role: string, role_slug: string}|null
     */
    public static function currentUser(): ?array
    {
        self::initSession();
        return $_SESSION['user'] ?? null;
    }

    /**
     * Check if the authenticated user has any of the specified roles
     *
     * @param string|array<string> $allowedRoles Role names or slugs (e.g., 'Admin', 'Store Manager')
     */
    public static function hasRole($allowedRoles): bool
    {
        if (!self::isAuthenticated()) {
            return false;
        }

        $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
        $current = self::currentUser();
        if (!$current) {
            return false;
        }

        $userRole = strtolower(trim($current['role']));
        $userSlug = strtolower(trim($current['role_slug']));

        foreach ($roles as $role) {
            $normalized = strtolower(trim($role));
            if ($userRole === $normalized || $userSlug === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * Guard route: Require user to be authenticated
     *
     * @param string $redirectUrl
     */
    public static function requireAuth(string $redirectUrl = '/login.php'): void
    {
        self::initSession();

        if (!self::isAuthenticated()) {
            if (self::isAjaxOrApiRequest()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication required to access this resource.',
                ]);
                exit;
            }

            $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
            $target = $redirectUrl . '?redirect=' . urlencode($currentUri);
            header("Location: {$target}");
            exit;
        }
    }

    /**
     * Guard route: Enforce role-based access control
     *
     * @param string|array<string> $allowedRoles e.g., ['Admin'] or ['Admin', 'Store Manager']
     */
    public static function requireRole($allowedRoles): void
    {
        self::requireAuth();

        if (!self::hasRole($allowedRoles)) {
            if (self::isAjaxOrApiRequest()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'error' => 'Forbidden',
                    'message' => 'You do not have permission to access this resource.',
                    'required_roles' => (array)$allowedRoles,
                ]);
                exit;
            }

            http_response_code(403);
            self::renderForbiddenPage((array)$allowedRoles);
            exit;
        }
    }

    /**
     * Terminate the user session and clear session cookies
     */
    public static function logout(): void
    {
        self::initSession();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Generate or fetch current CSRF token
     */
    public static function csrfToken(): string
    {
        self::initSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token from input against session
     */
    public static function verifyCsrf(?string $token): bool
    {
        self::initSession();
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Detect AJAX or JSON API requests
     */
    private static function isAjaxOrApiRequest(): bool
    {
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return strtolower($requestedWith) === 'xmlhttprequest'
            || str_contains($acceptHeader, 'application/json')
            || str_starts_with($uri, '/api/');
    }

    /**
     * Render a styled 403 Forbidden page
     *
     * @param array<string> $requiredRoles
     */
    private static function renderForbiddenPage(array $requiredRoles): void
    {
        $user = self::currentUser();
        $userRole = htmlspecialchars($user['role'] ?? 'Unknown');
        $required = htmlspecialchars(implode(', ', $requiredRoles));

        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden - Access Denied</title>
    <style>
        body {
            background: #0f172a;
            color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            padding: 1rem;
        }
        .box {
            background: #1e293b;
            border: 1px solid #ef4444;
            border-radius: 8px;
            padding: 2.5rem;
            max-width: 480px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        h1 { font-size: 2rem; color: #ef4444; margin-bottom: 0.5rem; }
        p { color: #94a3b8; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .details {
            background: #334155;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }
        .btn {
            display: inline-block;
            background: #3b82f6;
            color: #fff;
            padding: 0.65rem 1.25rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }
        .btn:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="box">
        <h1>403 Forbidden</h1>
        <p>Access Denied: You do not possess the required credentials to access this department or operational view.</p>
        <div class="details">
            <div><strong>Your Role:</strong> {$userRole}</div>
            <div><strong>Required Role(s):</strong> {$required}</div>
        </div>
        <a href="/" class="btn">&larr; Return to Dashboard</a>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Non-blocking internal audit log on user sign in
     */
    private static function logLoginAudit(int $userId): void
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('
                INSERT INTO audit_ledger (
                    transaction_type,
                    entry_category,
                    entity_type,
                    entity_id,
                    user_id,
                    notes,
                    created_at
                ) VALUES (
                    "ADJUSTMENT",
                    "FINANCIAL",
                    "users",
                    :user_id,
                    :user_id,
                    "User authentication successful",
                    NOW()
                )
            ');
            $stmt->execute([':user_id' => $userId]);
        } catch (\Throwable $e) {
            // Never break login on audit write failure
        }
    }
}

