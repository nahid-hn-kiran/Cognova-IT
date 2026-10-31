<?php
/**
 * Shared Top Navigation Bar
 */

require_once __DIR__ . '/auth.php';

use App\Services\ProductService;

$navUser = currentUser();
$navIsLoggedIn = isAuthenticated();
$lowStockCount = 0;

if ($navIsLoggedIn) {
    try {
        $productService = new ProductService();
        $lowStockCount = $productService->getLowStockCount();
    } catch (\Throwable $e) {
        $lowStockCount = 0;
    }
}

$currentPath = $_SERVER['SCRIPT_NAME'] ?? '';
?>
<header class="app-navbar">
    <div class="nav-container">
        <div class="nav-brand">
            <a href="/index.php" class="brand-link">
                <span class="brand-icon">&#9638;</span>
                <span class="brand-title">Cognova Ledger</span>
            </a>
            <span class="env-badge">Enterprise Engine</span>
        </div>

        <nav class="nav-links">
            <a href="/index.php" class="nav-item <?= $currentPath === '/index.php' ? 'active' : '' ?>">
                Dashboard
            </a>

            <?php if (hasRole(['Admin', 'Store Manager'])): ?>
                <a href="/inventory/index.php" class="nav-item <?= str_contains($currentPath, '/inventory/index.php') ? 'active' : '' ?>">
                    Products
                    <?php if ($lowStockCount > 0): ?>
                        <span class="badge-alert-pill" title="<?= $lowStockCount ?> items require reorder">
                            <?= $lowStockCount ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a href="/inventory/stock-in.php" class="nav-item <?= str_contains($currentPath, '/inventory/stock-in.php') ? 'active' : '' ?>">
                    Stock Inbound
                </a>
            <?php endif; ?>

            <?php if (hasRole(['Admin', 'Sales Operator'])): ?>
                <a href="/sales/index.php" class="nav-item <?= str_contains($currentPath, '/sales/index.php') ? 'active' : '' ?>">
                    Sales POS
                </a>
            <?php endif; ?>

            <?php if (hasRole(['Admin', 'Sales Operator', 'Store Manager'])): ?>
                <a href="/sales/invoices.php" class="nav-item <?= (str_contains($currentPath, '/sales/invoices.php') || str_contains($currentPath, '/sales/invoice.php')) ? 'active' : '' ?>">
                    Invoices
                </a>
            <?php endif; ?>

            <?php if (hasRole(['Admin'])): ?>
                <a href="/admin/audit-ledger.php" class="nav-item <?= str_contains($currentPath, '/admin/audit-ledger.php') ? 'active' : '' ?>">
                    Audit Ledger
                </a>
                <a href="/admin/users.php" class="nav-item <?= str_contains($currentPath, '/admin/users.php') ? 'active' : '' ?>">
                    Staff Users
                </a>
            <?php endif; ?>
        </nav>

        <div class="nav-user-area">
            <?php if ($navIsLoggedIn): ?>
                <div class="user-chip">
                    <span class="user-avatar"><?= strtoupper(substr($navUser['name'], 0, 1)) ?></span>
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($navUser['name']) ?></span>
                        <span class="role-tag role-<?= strtolower(str_replace(' ', '-', $navUser['role'])) ?>">
                            <?= htmlspecialchars($navUser['role']) ?>
                        </span>
                    </div>
                </div>
                <a href="/logout.php" class="nav-btn-logout" title="Sign Out">Sign Out</a>
            <?php else: ?>
                <a href="/login.php" class="nav-btn-login">Sign In</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<style>
.app-navbar {
    background: #111827;
    border-bottom: 1px solid #1f2937;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}
.nav-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0.75rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
}
.nav-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.brand-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #fff;
    text-decoration: none;
    font-weight: 700;
    font-size: 1.15rem;
    letter-spacing: -0.01em;
}
.brand-icon {
    color: #3b82f6;
    font-size: 1.35rem;
}
.env-badge {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    text-transform: uppercase;
}
.nav-links {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.nav-item {
    color: #9ca3af;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    padding: 0.5rem 0.85rem;
    border-radius: 6px;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.nav-item:hover {
    color: #fff;
    background: #1f2937;
}
.nav-item.active {
    color: #fff;
    background: #1e3a8a;
}
.badge-alert-pill {
    background: #ef4444;
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.1rem 0.45rem;
    border-radius: 9999px;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
.nav-user-area {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.user-chip {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #3b82f6;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
}
.user-details {
    display: flex;
    flex-direction: column;
}
.user-name {
    font-size: 0.825rem;
    font-weight: 600;
    color: #f3f4f6;
    line-height: 1.2;
}
.role-tag {
    font-size: 0.7rem;
    font-weight: 600;
}
.role-admin { color: #f59e0b; }
.role-store-manager { color: #10b981; }
.role-sales-operator { color: #38bdf8; }
.nav-btn-logout {
    color: #9ca3af;
    background: #1f2937;
    border: 1px solid #374151;
    text-decoration: none;
    font-size: 0.8rem;
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.15s ease;
}
.nav-btn-logout:hover {
    color: #ef4444;
    border-color: #ef4444;
}
.nav-btn-login {
    background: #3b82f6;
    color: #fff;
    text-decoration: none;
    font-size: 0.85rem;
    padding: 0.45rem 1rem;
    border-radius: 6px;
    font-weight: 600;
}
</style>

