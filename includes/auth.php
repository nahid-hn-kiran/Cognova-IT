<?php
/**
 * Procedural Authentication & Authorization Helper Functions
 *
 * Provides PSR-4 class autoloading for `App\` namespace and convenient
 * global functional wrappers for `App\Middleware\AuthMiddleware`.
 */

// Register PSR-4 Autoloader for App\* namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Middleware\AuthMiddleware;

if (!function_exists('initSession')) {
    function initSession(): void {
        AuthMiddleware::initSession();
    }
}

if (!function_exists('isAuthenticated')) {
    function isAuthenticated(): bool {
        return AuthMiddleware::isAuthenticated();
    }
}

if (!function_exists('currentUser')) {
    function currentUser(): ?array {
        return AuthMiddleware::currentUser();
    }
}

if (!function_exists('hasRole')) {
    /**
     * @param string|array<string> $roles
     */
    function hasRole($roles): bool {
        return AuthMiddleware::hasRole($roles);
    }
}

if (!function_exists('requireAuth')) {
    function requireAuth(string $redirectUrl = '/login.php'): void {
        AuthMiddleware::requireAuth($redirectUrl);
    }
}

if (!function_exists('requireRole')) {
    /**
     * @param string|array<string> $roles e.g. ['Admin'], ['Store Manager']
     */
    function requireRole($roles): void {
        AuthMiddleware::requireRole($roles);
    }
}

if (!function_exists('attemptLogin')) {
    function attemptLogin(string $email, string $password): array {
        return AuthMiddleware::attemptLogin($email, $password);
    }
}

if (!function_exists('logout')) {
    function logout(): void {
        AuthMiddleware::logout();
    }
}

if (!function_exists('csrfToken')) {
    function csrfToken(): string {
        return AuthMiddleware::csrfToken();
    }
}

if (!function_exists('verifyCsrf')) {
    function verifyCsrf(?string $token): bool {
        return AuthMiddleware::verifyCsrf($token);
    }
}

