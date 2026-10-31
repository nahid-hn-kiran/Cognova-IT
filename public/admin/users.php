<?php
/**
 * User & Role Management Console
 *
 * Restricted strictly to: Admin
 */

require_once __DIR__ . '/../../includes/auth.php';

use App\Services\UserService;

requireRole(['Admin']);

$userService = new UserService();
$currentUser = currentUser();
$csrfToken = csrfToken();

$users = $userService->getAll();
$roles = $userService->getRoles();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User & Role Management - Admin Console</title>
    <style>
        :root {
            --bg: #0b1120;
            --surface: #1e293b;
            --surface-alt: #334155;
            --border: #334155;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
            min-height: 100vh;
        }
        .page-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .page-title h1 {
            font-size: 1.65rem;
            font-weight: 700;
            color: #fff;
        }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 0.2rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--primary);
            color: #fff;
            padding: 0.6rem 1.15rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn-success { background: var(--success); }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-outline:hover { background: var(--surface-alt); }
        .btn-sm {
            padding: 0.35rem 0.65rem;
            font-size: 0.8rem;
        }

        .table-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            text-align: left;
        }
        thead th {
            background: #111827;
            padding: 0.85rem 1rem;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid var(--border);
        }
        tbody td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        tbody tr:hover { background: rgba(255, 255, 255, 0.02); }
        .mono { font-family: ui-monospace, monospace; }

        .role-pill {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            border-radius: 9999px;
            display: inline-block;
        }
        .role-admin { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); }
        .role-store-manager { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .role-sales-operator { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }

        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
        }
        .status-active { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .status-suspended { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .status-inactive { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }
        .modal-overlay.active { display: flex; }
        .modal-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }
        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { font-size: 1.15rem; color: #fff; }
        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
        }
        .modal-body { padding: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.35rem;
        }
        .form-control {
            width: 100%;
            background: #0f172a;
            border: 1px solid var(--border);
            color: #fff;
            padding: 0.65rem 0.85rem;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        .form-control:focus { outline: none; border-color: var(--primary); }
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            background: rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../includes/nav.php'; ?>

<div class="page-container">
    <div class="page-header">
        <div class="page-title">
            <h1>Staff Accounts & Role-Based Access Control</h1>
            <p>Admin console to provision team credentials, assign operational permissions, and toggle access states.</p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="/admin/audit-ledger.php" class="btn btn-outline">
                <span>&#128220;</span> Audit Ledger
            </a>
            <button type="button" class="btn btn-success" onclick="openCreateModal()">
                <span>+</span> Add New Staff Member
            </button>
        </div>
    </div>

    <!-- Users Table -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>User Details</th>
                    <th>Email Address</th>
                    <th>Assigned Role</th>
                    <th>Account Status</th>
                    <th>Created</th>
                    <th style="text-align: right;">Administrative Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php 
                        $isSelf = ((int)$u['id'] === (int)$currentUser['id']);
                        $roleClass = 'role-' . strtolower(str_replace(' ', '-', $u['role_name']));
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight: 700; color: #fff;">
                                <?= htmlspecialchars($u['name']) ?>
                                <?php if ($isSelf): ?>
                                    <span style="font-size: 0.7rem; color: #60a5fa; margin-left: 0.3rem;">(You)</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($u['role_description'] ?? '') ?></div>
                        </td>
                        <td class="mono"><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="role-pill <?= $roleClass ?>">
                                <?= htmlspecialchars($u['role_name']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?= htmlspecialchars($u['status']) ?>">
                                <?= htmlspecialchars($u['status']) ?>
                            </span>
                        </td>
                        <td class="mono" style="font-size: 0.8rem; color: var(--text-muted);">
                            <?= date('M d, Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 0.4rem;">
                                <button 
                                    type="button" 
                                    class="btn btn-outline btn-sm"
                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)"
                                >
                                    Edit Role / Password
                                </button>

                                <?php if (!$isSelf): ?>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <button 
                                            type="button" 
                                            class="btn btn-outline btn-sm"
                                            style="border-color: rgba(239,68,68,0.4); color: #f87171;"
                                            onclick="toggleUserStatus(<?= $u['id'] ?>, 'suspended', '<?= htmlspecialchars($u['name']) ?>')"
                                        >
                                            Suspend
                                        </button>
                                    <?php else: ?>
                                        <button 
                                            type="button" 
                                            class="btn btn-outline btn-sm"
                                            style="border-color: rgba(16,185,129,0.4); color: #34d399;"
                                            onclick="toggleUserStatus(<?= $u['id'] ?>, 'active', '<?= htmlspecialchars($u['name']) ?>')"
                                        >
                                            Activate
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: var(--text-muted); padding: 0.3rem;">Protected</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Create / Edit User -->
<div class="modal-overlay" id="userModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Provision Staff Account</h3>
            <button type="button" class="modal-close" onclick="closeUserModal()">&times;</button>
        </div>
        <form id="userForm" onsubmit="handleUserSubmit(event)">
            <input type="hidden" name="csrf_token" id="csrfToken" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" id="userId" value="">
            <input type="hidden" name="action" id="userAction" value="create_user">

            <div class="modal-body">
                <div class="form-group">
                    <label for="userName">Full Name *</label>
                    <input type="text" name="name" id="userName" class="form-control" required placeholder="e.g. Rachel Adams">
                </div>

                <div class="form-group">
                    <label for="userEmail">Work Email Address *</label>
                    <input type="email" name="email" id="userEmail" class="form-control" required placeholder="rachel@cognova.local">
                </div>

                <div class="form-group">
                    <label for="userRole">Assign Role *</label>
                    <select name="role_id" id="userRole" class="form-control" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> — <?= htmlspecialchars($r['description']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="userStatus">Account Status *</label>
                    <select name="status" id="userStatus" class="form-control">
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="userPassword" id="passwordLabel">Password *</label>
                    <input type="password" name="password" id="userPassword" class="form-control" placeholder="Min 6 characters">
                    <small id="passwordHelp" style="display: none; color: var(--text-muted); font-size: 0.75rem;">Leave blank to preserve current password.</small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeUserModal()">Cancel</button>
                <button type="submit" class="btn btn-success" id="saveUserBtn">Save Staff Account</button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('userModal');
const userForm = document.getElementById('userForm');
const modalTitle = document.getElementById('modalTitle');
const userId = document.getElementById('userId');
const userAction = document.getElementById('userAction');
const passwordLabel = document.getElementById('passwordLabel');
const passwordHelp = document.getElementById('passwordHelp');
const userPassword = document.getElementById('userPassword');
const saveUserBtn = document.getElementById('saveUserBtn');

function openCreateModal() {
    userForm.reset();
    userId.value = '';
    userAction.value = 'create_user';
    modalTitle.textContent = 'Provision Staff Account';
    passwordLabel.textContent = 'Password *';
    userPassword.required = true;
    passwordHelp.style.display = 'none';
    modal.classList.add('active');
}

function openEditModal(user) {
    userForm.reset();
    userId.value = user.id;
    userAction.value = 'update_user';
    modalTitle.textContent = 'Edit Account: ' + user.name;
    document.getElementById('userName').value = user.name;
    document.getElementById('userEmail').value = user.email;
    document.getElementById('userRole').value = user.role_id;
    document.getElementById('userStatus').value = user.status;
    passwordLabel.textContent = 'Reset Password (Optional)';
    userPassword.required = false;
    passwordHelp.style.display = 'block';
    modal.classList.add('active');
}

function closeUserModal() {
    modal.classList.remove('active');
}

window.addEventListener('click', (e) => {
    if (e.target === modal) closeUserModal();
});

async function handleUserSubmit(e) {
    e.preventDefault();
    saveUserBtn.disabled = true;
    saveUserBtn.textContent = 'Saving...';

    const payload = {
        action: userAction.value,
        csrf_token: document.getElementById('csrfToken').value,
        name: document.getElementById('userName').value,
        email: document.getElementById('userEmail').value,
        role_id: parseInt(document.getElementById('userRole').value, 10),
        status: document.getElementById('userStatus').value,
    };

    if (userId.value) {
        payload.id = parseInt(userId.value, 10);
    }
    if (userPassword.value) {
        payload.password = userPassword.value;
    }

    try {
        const response = await fetch('/admin/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert(data.message || 'User saved successfully.');
            window.location.reload();
        } else {
            alert('Failed: ' + (data.message || 'Unknown error'));
            saveUserBtn.disabled = false;
            saveUserBtn.textContent = 'Save Staff Account';
        }
    } catch (err) {
        alert('Network error: ' + err.message);
        saveUserBtn.disabled = false;
        saveUserBtn.textContent = 'Save Staff Account';
    }
}

async function toggleUserStatus(id, newStatus, name) {
    const confirmMsg = `Are you sure you want to set status of '${name}' to '${newStatus}'?`;
    if (!confirm(confirmMsg)) return;

    try {
        const response = await fetch('/admin/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'toggle_user_status',
                csrf_token: document.getElementById('csrfToken').value,
                id: id,
                status: newStatus
            })
        });

        const data = await response.json();
        if (response.ok && data.success) {
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Could not update status'));
        }
    } catch (err) {
        alert('Network error: ' + err.message);
    }
}
</script>

</body>
</html>

