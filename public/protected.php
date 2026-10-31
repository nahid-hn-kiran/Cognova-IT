<?php
/**
 * Mini Enterprise Inventory & Financial Ledger Engine
 * Role-Based Access Guard Test Page
 */

require_once __DIR__ . '/../includes/auth.php';

$requestedRole = $_GET['role'] ?? 'Admin';

// Map 'Staff' to all roles
if ($requestedRole === 'Staff') {
    requireRole(['Admin', 'Store Manager', 'Sales Operator']);
} else {
    requireRole([$requestedRole]);
}

$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authorized Access - <?= htmlspecialchars($requestedRole) ?></title>
    <style>
        :root {
            --bg: #0f172a;
            --surface: #1e293b;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
            --border: #334155;
            --primary: #3b82f6;
        }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 2rem;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
        }
        .badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }
        h1 { font-size: 1.5rem; color: #fff; margin-bottom: 0.5rem; }
        p { color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem; }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.9rem;
        }
        .info-row:last-child { border-bottom: none; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace; }
        .actions { margin-top: 1.5rem; display: flex; gap: 0.75rem; }
        .btn {
            background: var(--primary);
            color: #fff;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }
    </style>
</head>
<body>
<div class="card">
    <div class="badge">&#10003; Access Granted</div>
    <h1><?= htmlspecialchars($requestedRole) ?> Clearance Confirmed</h1>
    <p>Role guard verification succeeded. Your credentials satisfy the required security tier.</p>

    <div style="background: rgba(0,0,0,0.25); border-radius: 6px; padding: 1rem; margin-bottom: 1.5rem;">
        <div class="info-row">
            <span style="color: var(--text-muted);">User Name</span>
            <strong><?= htmlspecialchars($user['name']) ?></strong>
        </div>
        <div class="info-row">
            <span style="color: var(--text-muted);">Email</span>
            <span class="mono"><?= htmlspecialchars($user['email']) ?></span>
        </div>
        <div class="info-row">
            <span style="color: var(--text-muted);">Verified Role</span>
            <span style="color: var(--success); font-weight: bold;"><?= htmlspecialchars($user['role']) ?></span>
        </div>
        <div class="info-row">
            <span style="color: var(--text-muted);">Role Slug</span>
            <span class="mono"><?= htmlspecialchars($user['role_slug']) ?></span>
        </div>
    </div>

    <div class="actions">
        <a href="/index.php" class="btn">&larr; Dashboard</a>
        <a href="/logout.php" class="btn btn-outline">Sign Out</a>
    </div>
</div>
</body>
</html>

