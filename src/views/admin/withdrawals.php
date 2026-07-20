<?php
// src/views/admin/withdrawals.php - Manage Withdrawals
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAdmin();
$csrfToken = generateCsrfToken();

$title = __("Manage Withdrawals");
$description = __("Confirm payment transfers or reject cashout requests");
$activePage = "withdrawals";

ob_start();
?>
<div class="split-layout">
    <!-- Left: Pending List -->
    <div class="glass-panel" style="padding: 24px; border: 1px solid var(--border);">
        <h3 style="font-weight: 700; margin-bottom: 16px; color: var(--foreground);"><?= __('Request Queue') ?></h3>
        <div class="pane-list-container" id="with-queue-list">
            <!-- Pending withdrawals list -->
        </div>
    </div>

    <!-- Right: Pane details -->
    <div class="glass-panel pane-details" style="padding: 24px; border: 1px solid var(--border);" id="with-details-pane">
        <div style="text-align: center; color: var(--muted); padding: 40px 0;"><?= __('Select a withdrawal request to view bank details and process') ?></div>
    </div>
</div>

<script>
    function formatRupees(paise) {
        return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    let selectedWithdrawalId = null;

    async function fetchWithdrawals() {
        try {
            const data = await apiRequest('/api/admin/pending-withdrawals?page=1&limit=20');
            const container = document.getElementById('with-queue-list');
            container.innerHTML = '';
            document.getElementById('with-details-pane').innerHTML = '<div style="text-align: center; color: var(--muted); padding: 40px 0;"><?= __('Select a withdrawal request to view bank details and process') ?></div>';

            if (data.withdrawals.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: var(--muted); padding: 20px 0;"><?= __('No pending withdrawals found.') ?></div>';
                return;
            }

            let foundSelected = false;
            data.withdrawals.forEach(w => {
                const row = document.createElement('div');
                row.className = 'pane-item';
                if (selectedWithdrawalId === w.id) {
                    row.classList.add('active');
                    foundSelected = true;
                }
                row.innerHTML = `
                    <div style="font-weight: 600; font-size: 1.1rem; margin-bottom: 8px; color: var(--foreground);">${formatRupees(w.amount)}</div>
                    <div style="font-size: 0.85rem; color: var(--muted); margin-bottom: 4px;"><?= __('User:') ?> ${w.userName}</div>
                    <div style="font-size: 0.75rem; color: var(--muted);">${new Date(w.createdAt).toLocaleString('en-IN')}</div>
                `;
                row.addEventListener('click', () => {
                    document.querySelectorAll('#with-queue-list .pane-item').forEach(x => x.classList.remove('active'));
                    row.classList.add('active');
                    selectedWithdrawalId = w.id;
                    renderWithdrawalDetails(w);
                });
                container.appendChild(row);
            });
            if (!foundSelected) {
                selectedWithdrawalId = null;
                document.getElementById('with-details-pane').innerHTML = '<div style="color:var(--muted); text-align:center; padding: 40px;"><?= __('Select a request to review') ?></div>';
            }
        } catch (err) {
            console.error('fetchWithdrawals failed:', err);
            Toast.show(err.message || '<?= __('Failed to load withdrawals') ?>', 'error');
        }
    }

    function renderWithdrawalDetails(w) {
        const pane = document.getElementById('with-details-pane');
        let payDetailsHtml = '';
        
        if (w.bankDetails.upiId) {
            payDetailsHtml = `<div><?= __('Preferred Type:') ?> <strong><?= __('UPI ID') ?></strong></div><div style="font-size: 1.1rem; color: var(--primary); font-weight: 700; margin-top: 6px;"><?= __('UPI ID:') ?> ${w.bankDetails.upiId}</div>`;
        } else {
            payDetailsHtml = `
                <div><?= __('Preferred Type:') ?> <strong><?= __('Bank Transfer') ?></strong></div>
                <div style="margin-top: 6px; padding: 12px; background: rgba(0,0,0,0.02); border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 0.85rem; line-height: 1.6; color: var(--foreground);">
                    <div><?= __('Holder:') ?> <strong>${w.bankDetails.accountName}</strong></div>
                    <div><?= __('Account No:') ?> <strong>${w.bankDetails.accountNumber}</strong></div>
                    <div><?= __('IFSC:') ?> <strong>${w.bankDetails.ifscCode}</strong></div>
                </div>
            `;
        }

        pane.innerHTML = `
            <h3 style="font-weight: 700; margin-bottom: 20px; color: var(--foreground);"><?= __('Cashout Details') ?></h3>
            <div style="font-size: 0.9rem; margin-bottom: 20px; line-height: 1.6; color: var(--foreground);">
                <div><?= __('User:') ?> <strong>${w.userName}</strong> (${w.userEmail})</div>
                <div><?= __('Requested Amount:') ?> <strong>${formatRupees(w.amount)}</strong></div>
                <div><?= __('Requested At:') ?> <strong>${new Date(w.createdAt).toLocaleString('en-IN')}</strong></div>
            </div>

            <div class="glass-card" style="margin-bottom: 24px; border: 1px solid var(--border);">
                ${payDetailsHtml}
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label"><?= __('Rejection Reason (if rejecting)') ?></label>
                <input class="form-input" type="text" id="with-reject-reason" placeholder="<?= __('Incorrect account number, KYC mismatch, etc.') ?>">
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="button" class="btn-destructive" onclick="rejectWithdrawal()" style="flex: 1;"><?= __('Reject Request') ?></button>
                <button type="button" class="btn-primary" onclick="confirmWithdrawal()" style="flex: 1; background: var(--success); box-shadow: 0 4px 12px var(--success-glow); color: #000000;"><?= __('Confirm Completed') ?></button>
            </div>
        `;
    }

    async function confirmWithdrawal() {
        if (!selectedWithdrawalId) return;

        const btn = document.querySelector('button[onclick="confirmWithdrawal()"]');
        if(btn) {
            btn.innerHTML = '<span style="display:inline-block; animation: spin 1s linear infinite;">⏳</span> <?= __('Processing...') ?>';
            btn.disabled = true;
        }

        try {
            await apiRequest(`/api/admin/withdrawals/${selectedWithdrawalId}/confirm`, { method: 'POST' });
            Toast.show('<?= __('Withdrawal marked as completed.') ?>');
            fetchWithdrawals();
        } catch (err) {
            Toast.show(err.message, 'error');
            if(btn) {
                btn.innerHTML = '<?= __('Confirm Completed') ?>';
                btn.disabled = false;
            }
        }
    }

    async function rejectWithdrawal() {
        if (!selectedWithdrawalId) return;
        const reason = document.getElementById('with-reject-reason').value.trim();
        if (!reason) {
            Toast.show('<?= __('Please enter a rejection reason') ?>', 'error');
            return;
        }

        const btn = document.querySelector('button[onclick="rejectWithdrawal()"]');
        if(btn) {
            btn.innerHTML = '<span style="display:inline-block; animation: spin 1s linear infinite;">⏳</span> <?= __('Processing...') ?>';
            btn.disabled = true;
        }

        try {
            await apiRequest(`/api/admin/withdrawals/${selectedWithdrawalId}/reject`, {
                method: 'POST',
                body: { reason }
            });
            Toast.show('<?= __('Withdrawal request rejected. Funds returned to user.') ?>');
            fetchWithdrawals();
        } catch (err) {
            Toast.show(err.message, 'error');
            if(btn) {
                btn.innerHTML = '<?= __('Reject Request') ?>';
                btn.disabled = false;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', fetchWithdrawals);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
