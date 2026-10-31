<?php
/**
 * Mini Enterprise Inventory & Financial Ledger Engine
 * Authentication Login Portal
 */

require_once __DIR__ . '/../includes/auth.php';

use App\Middleware\AuthMiddleware;

// If user is already authenticated, redirect to home/dashboard
if (isAuthenticated()) {
    header('Location: /index.php');
    exit;
}

$error = null;
$infoMessage = null;
$redirect = filter_input(INPUT_GET, 'redirect', FILTER_SANITIZE_URL) ?: '/index.php';

// Check for logout or message query params
if (isset($_GET['logged_out'])) {
    $infoMessage = 'You have been successfully signed out.';
} elseif (isset($_GET['session_expired'])) {
    $infoMessage = 'Your session has expired. Please sign in again.';
}

// Process POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';
    $postRedirect = $_POST['redirect'] ?? '/index.php';

    // Verify CSRF
    if (!verifyCsrf($csrf)) {
        $error = 'Invalid security token (CSRF). Please refresh and try again.';
    } else {
        $result = attemptLogin($email, $password);

        if ($result['success']) {
            // Validate safe local redirect target
            $target = (str_starts_with($postRedirect, '/') && !str_starts_with($postRedirect, '//')) 
                ? $postRedirect 
                : '/index.php';
            header("Location: {$target}");
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Mini Enterprise Inventory & Ledger</title>
    <style>
        :root {
            --bg: #0b1120;
            --surface: #1e293b;
            --surface-hover: #334155;
            --border: #334155;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --danger: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.15);
            --success: #10b981;
            --success-bg: rgba(16, 185, 129, 0.15);
            --accent: #6366f1;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: radial-gradient(circle at top, #1e293b 0%, #0b1120 100%);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        .card-header {
            padding: 2rem 2rem 1.5rem 2rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(59, 130, 246, 0.15);
            color: var(--primary);
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .card-header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.35rem;
        }
        .card-header p {
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        .card-body {
            padding: 2rem;
        }
        .alert {
            padding: 0.85rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .alert-danger {
            background: var(--danger-bg);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
        }
        .alert-info {
            background: var(--success-bg);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #6ee7b7;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem 1rem;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: #fff;
            font-size: 0.95rem;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }
        .btn-submit {
            width: 100%;
            padding: 0.85rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s;
            margin-top: 0.5rem;
        }
        .btn-submit:hover {
            background: var(--primary-hover);
        }
        .quick-accounts {
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }
        .quick-accounts-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
            font-weight: 700;
            text-align: center;
        }
        .account-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
        }
        .btn-account {
            background: var(--surface-hover);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            padding: 0.5rem 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            text-align: center;
            transition: all 0.15s ease;
        }
        .btn-account:hover {
            border-color: var(--primary);
            background: rgba(59, 130, 246, 0.2);
            color: #fff;
        }
        .btn-account span {
            display: block;
            font-weight: 700;
            font-size: 0.8rem;
            color: #fff;
        }
        .footer-note {
            text-align: center;
            padding: 1rem;
            background: #0f172a;
            color: var(--text-muted);
            font-size: 0.75rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="card-header">
        <div class="brand-badge">Congova IT Engine</div>
        <h1>Enterprise Access Portal</h1>
        <p>Inventory Control & Financial Audit Ledger</p>
    </div>

    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span>&#9888;</span>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($infoMessage): ?>
            <div class="alert alert-info">
                <span>&#10003;</span>
                <div><?= htmlspecialchars($infoMessage) ?></div>
            </div>
        <?php endif; ?>

        <form action="/login.php" method="POST" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <div class="form-group">
                <label for="email">Work Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    placeholder="name@congova.local" 
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    autocomplete="username"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    placeholder="Enter password"
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn-submit">Authenticate</button>
        </form>

        <div class="quick-accounts">
            <div class="quick-accounts-title">Quick Fill Seeded Accounts</div>
            <div class="account-buttons">
                <button 
                    type="button" 
                    class="btn-account"
                    onclick="fillAccount('admin@congova.local', 'Password123!')"
                >
                    <span>Admin</span>
                    Executive
                </button>
                <button 
                    type="button" 
                    class="btn-account"
                    onclick="fillAccount('manager@congova.local', 'Password123!')"
                >
                    <span>Manager</span>
                    Store / Inventory
                </button>
                <button 
                    type="button" 
                    class="btn-account"
                    onclick="fillAccount('operator@congova.local', 'Password123!')"
                >
                    <span>Operator</span>
                    Sales & POS
                </button>
            </div>
        </div>
    </div>

    <div class="footer-note">
        Protected by session regeneration & bcrypt hashing
    </div>
</div>

<script>
function fillAccount(email, password) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
}
</script>

</body>
</html>

