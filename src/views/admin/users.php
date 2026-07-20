<?php
// src/views/admin/users.php - User Accounts Directory
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAdmin();
$csrfToken = generateCsrfToken();

$title = __("User Accounts");
$description = __("Search and audit active user ledgers or suspend/reactivate profiles");
$activePage = "users";

ob_start();
?>
<div class="glass-panel" style="padding: 24px; margin-bottom: 24px; display: flex; gap: 16px; align-items: center; border: 1px solid var(--border);">
    <input class="form-input" type="text" id="user-search" placeholder="<?= __('Search by name, email, or mobile...') ?>" style="max-width: 360px;">
    <button class="btn-primary" onclick="fetchUsers()"><?= __('Search') ?></button>
</div>

<div class="glass-panel" style="padding: 24px; overflow-x: auto; border: 1px solid var(--border);">
<div class="table-responsive">
    <table style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
            <tr style="border-bottom: 1px solid var(--border); color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase;">
                <th style="padding: 12px;"><?= __('User') ?></th>
                <th style="padding: 12px;"><?= __('Mobile') ?></th>
                <th style="padding: 12px;"><?= __('Wallet Balance') ?></th>
                <th style="padding: 12px;"><?= __('Status') ?></th>
                <th style="padding: 12px;"><?= __('Actions') ?></th>
            </tr>
        </thead>
        <tbody id="users-table-body">
            <!-- User accounts rows -->
        </tbody>
    </table>
</div>
</div>

<!-- User Transactions Audit Side Drawer -->
<div class="drawer-overlay" id="audit-drawer-overlay">
    <div class="side-drawer" id="audit-drawer">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="font-weight: 800; color: var(--foreground);"><?= __('Member Transactions') ?></h3>
            <button onclick="closeAuditDrawer()" style="background: none; border: none; font-size: 1.4rem; color: var(--foreground); cursor: pointer;">✕</button>
        </div>
        <div id="audit-user-details" style="margin-bottom: 24px; color: var(--foreground);">
            <!-- User account audit information -->
        </div>
        <div id="audit-transactions-list" style="display: flex; flex-direction: column; gap: 12px;">
            <!-- Audit transactions dynamic rows -->
        </div>
    </div>
</div>

<script>
    function formatRupees(paise) {
        return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    let auditUserTargetId = null;
    let auditPage = 1;

    async function fetchUsers() {
        const search = document.getElementById('user-search').value;
        try {
            const data = await apiRequest(`/api/admin/users?page=1&limit=20&search=${encodeURIComponent(search)}`);
            const body = document.getElementById('users-table-body');
            body.innerHTML = '';

            if (data.users.length === 0) {
                body.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--muted); padding: 32px 0;"><?= __('No user accounts found.') ?></td></tr>';
                return;
            }

            data.users.forEach(u => {
                const tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid var(--border)';
                
                const statusText = u.isActive ? '<?= __('Active') ?>' : '<?= __('Suspended') ?>';
                const statusColor = u.isActive ? 'var(--success)' : 'var(--destructive)';
                const safeName = (u.name || '<?= __('Anonymous') ?>').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                const displayName = u.name || '<?= __('Anonymous User') ?>';

                tr.innerHTML = `
                    <td style="padding: 12px; color: var(--foreground);">
                        <div style="font-weight: 700;">${displayName}</div>
                        <div style="font-size: 0.75rem; color: var(--muted);">${u.email}</div>
                    </td>
                    <td style="padding: 12px; font-size: 0.85rem; color: var(--foreground);">${u.mobileNumber || '-'}</td>
                    <td style="padding: 12px; font-weight: 800; color: var(--foreground);">${formatRupees(u.walletBalance)}</td>
                    <td style="padding: 12px; font-weight: 700; color: ${statusColor};">${statusText}</td>
                    <td style="padding: 12px; display: flex; gap: 8px;">
                        <button class="btn-secondary" onclick="openAuditDrawer('${u.id}', '${safeName}')" style="padding: 6px 12px; font-size: 0.75rem;"><?= __('Audit') ?></button>
                        <button class="btn-secondary" onclick="toggleUserStatus('${u.id}', ${u.isActive})" style="padding: 6px 12px; font-size: 0.75rem;">${u.isActive ? '<?= __('Suspend') ?>' : '<?= __('Activate') ?>'}</button>
                        <button class="btn-destructive" onclick="deleteUser('${u.id}', '${safeName}')" style="padding: 6px 12px; font-size: 0.75rem;"><?= __('Delete') ?></button>
                    </td>
                `;
                body.appendChild(tr);
            });
        } catch (err) {
            console.error('fetchUsers failed:', err);
            Toast.show(err.message || '<?= __('Failed to load users') ?>', 'error');
        }
    }

    async function toggleUserStatus(id, isActive) {
        try {
            await apiRequest(`/api/admin/users/${id}/status`, {
                method: 'PATCH',
                body: { isActive: !isActive }
            });
            Toast.show('<?= __('User status toggled successfully.') ?>');
            fetchUsers();
        } catch (err) {
            console.error('Toggle user status error:', err);
            Toast.show(err.message || '<?= __('Failed to toggle user status') ?>', 'error');
        }
    }

    async function deleteUser(id, name) {
        const conf = confirm(`<?= __('Permanently delete account') ?> "${name}" <?= __('and all historical requests? THIS ACTION IS IRREVERSIBLE.') ?>`);
        if (!conf) return;

        try {
            await apiRequest(`/api/admin/users/${id}`, { 
                method: 'DELETE',
                body: {}
            });
            Toast.show('<?= __('User deleted successfully.') ?>');
            fetchUsers();
        } catch (err) {
            console.error('Delete user error:', err);
            Toast.show(err.message || '<?= __('Failed to delete user') ?>', 'error');
        }
    }

    async function openAuditDrawer(id, name) {
        auditUserTargetId = id;
        auditPage = 1;
        document.getElementById('audit-user-details').innerHTML = `<h3>${name}'s <?= __('Audit Log') ?></h3>`;
        document.getElementById('audit-drawer-overlay').classList.add('active');
        fetchAuditTransactions();
    }

    function closeAuditDrawer() {
        document.getElementById('audit-drawer-overlay').classList.remove('active');
        auditUserTargetId = null;
    }

    async function fetchAuditTransactions() {
        if (!auditUserTargetId) return;

        try {
            const data = await apiRequest(`/api/admin/users/${auditUserTargetId}/transactions?page=${auditPage}&limit=10`);
            const container = document.getElementById('audit-transactions-list');
            container.innerHTML = '';

            if (data.transactions.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: var(--muted); padding: 20px 0;"><?= __('No transaction logs recorded.') ?></div>';
                return;
            }

            data.transactions.forEach(t => {
                const row = document.createElement('div');
                row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid var(--border);';
                const isCredit = t.type === 'deposit';
                const color = isCredit ? 'var(--success)' : 'var(--destructive)';
                const prefix = isCredit ? '+' : '-';
                const date = new Date(t.createdAt).toLocaleDateString('en-IN');

                row.innerHTML = `
                    <div style="color: var(--foreground);">
                        <span style="font-weight: 700; font-size: 0.85rem; text-transform: capitalize;">${t.type}</span>
                        <span style="font-size: 0.7rem; color: var(--muted); display: block;">${date} | <?= __('Status:') ?> ${t.status}</span>
                    </div>
                    <span style="font-weight: 800; color: ${color};">${prefix}${formatRupees(t.amount)}</span>
                `;
                container.appendChild(row);
            });
        } catch (err) {
            console.error('fetchAuditTransactions failed:', err);
            Toast.show(err.message || '<?= __('Failed to load transactions') ?>', 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', fetchUsers);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
