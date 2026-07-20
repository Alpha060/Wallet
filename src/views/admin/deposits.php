<?php
// src/views/admin/deposits.php - Manage Deposits
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAdmin();
$csrfToken = generateCsrfToken();

$title = __("Manage Deposits");
$description = __("Approve or reject pending user deposit screenshots");
$activePage = "deposits";

ob_start();
?>
<div class="split-layout">
    <!-- Left: Pending list -->
    <div class="glass-panel" style="padding: 24px; border: 1px solid var(--border);">
        <h3 style="font-weight: 700; margin-bottom: 16px; color: var(--foreground);"><?= __('Request Queue') ?></h3>
        <div class="pane-list-container" id="dep-queue-list">
            <!-- Pending deposits loaded dynamically -->
        </div>
    </div>

    <!-- Right: Pane details -->
    <div class="glass-panel pane-details" style="padding: 24px; border: 1px solid var(--border);" id="dep-details-pane">
        <div style="text-align: center; color: var(--muted); padding: 40px 0;"><?= __('Select a deposit request to inspect proof and process') ?></div>
    </div>
</div>

<script>
    function formatRupees(paise) {
        return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    let selectedDepositId = null;

    async function fetchDeposits() {
        try {
            const data = await apiRequest('/api/admin/pending-deposits?page=1&limit=20');
            const container = document.getElementById('dep-queue-list');
            container.innerHTML = '';
            document.getElementById('dep-details-pane').innerHTML = '<div style="text-align: center; color: var(--muted); padding: 40px 0;"><?= __('Select a deposit request to inspect proof and process') ?></div>';

            if (data.deposits.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: var(--muted); padding: 20px 0;"><?= __('No pending deposits found.') ?></div>';
                return;
            }

            let foundSelected = false;
            data.deposits.forEach(d => {
                const row = document.createElement('div');
                row.className = 'pane-item';
                if (selectedDepositId === d.id) {
                    row.classList.add('active');
                    foundSelected = true;
                }
                row.innerHTML = `
                    <div style="font-weight: 600; font-size: 1.1rem; margin-bottom: 8px; color: var(--foreground);">${formatRupees(d.amount)}</div>
                    <div style="font-size: 0.85rem; color: var(--muted); margin-bottom: 4px;"><?= __('User:') ?> ${d.userName}</div>
                    <div style="font-size: 0.75rem; color: var(--muted);">${new Date(d.createdAt).toLocaleString('en-IN')}</div>
                `;
                row.addEventListener('click', () => {
                    document.querySelectorAll('#dep-queue-list .pane-item').forEach(x => x.classList.remove('active'));
                    row.classList.add('active');
                    selectedDepositId = d.id;
                    renderDepositDetails(d);
                });
                container.appendChild(row);
            });
            if (!foundSelected) {
                selectedDepositId = null;
                document.getElementById('dep-details-pane').innerHTML = '<div style="color:var(--muted); text-align:center; padding: 40px;"><?= __('Select a request to review') ?></div>';
            }
        } catch (err) {
            console.error('fetchDeposits failed:', err);
            Toast.show(err.message || '<?= __('Failed to load deposits') ?>', 'error');
        }
    }

    function renderDepositDetails(d) {
        const pane = document.getElementById('dep-details-pane');
        pane.innerHTML = `
            <h3 style="font-weight: 700; margin-bottom: 20px; color: var(--foreground);"><?= __('Request Audit') ?></h3>
            <div style="font-size: 0.9rem; margin-bottom: 16px; line-height: 1.6; color: var(--foreground);">
                <div><?= __('User:') ?> <strong>${d.userName}</strong> (${d.userEmail})</div>
                <div><?= __('Amount:') ?> <strong>${formatRupees(d.amount)}</strong></div>
                <div><?= __('Tx ID:') ?> <strong>${d.transactionId || '<?= __('Not provided') ?>'}</strong></div>
                <div><?= __('Submitted:') ?> <strong>${new Date(d.createdAt).toLocaleString('en-IN')}</strong></div>
            </div>

            <div class="magnifier-container" style="margin-bottom: 24px; position: relative;">
                <img id="dep-proof-img" src="${d.paymentProofUrl}" alt="Proof" style="width: 100%; max-height: 280px; object-fit: contain; border-radius: var(--radius-sm);">
                <div id="magnifier-lens" class="magnifier-glass"></div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label"><?= __('Rejection Reason (if rejecting)') ?></label>
                <input class="form-input" type="text" id="dep-reject-reason" placeholder="<?= __('Incorrect Transaction ID, amount mismatched, etc.') ?>">
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="button" class="btn-destructive" onclick="window.rejectDeposit(this)" style="flex: 1; display: flex; align-items: center; justify-content: center;"><?= __('Reject Request') ?></button>
                <button type="button" class="btn-primary" onclick="window.approveDeposit(this)" style="flex: 1; background: var(--success); box-shadow: 0 4px 12px var(--success-glow); color: #000000; display: flex; align-items: center; justify-content: center;"><?= __('Approve Deposit') ?></button>
            </div>
        `;
        // Initialize Magnifier
        setTimeout(() => {
            if (window.ImageMagnifier) {
                ImageMagnifier.init('dep-proof-img', 'magnifier-lens', 2);
            }
        }, 100);
    }

    window.approveDeposit = async function(btn) {
        if (!selectedDepositId) return;

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span style="width: 16px; height: 16px; border: 2px solid rgba(0,0,0,0.2); border-top-color: #000; border-radius: 50%; display: inline-block; animation: spin 0.6s linear infinite; margin-right: 8px;"></span> <?= __('Approving...') ?>';

        try {
            await apiRequest(`/api/admin/deposits/${selectedDepositId}/approve`, { 
                method: 'POST',
                body: {}
            });
            Toast.show('<?= __('Deposit approved and credited.') ?>');
            fetchDeposits();
        } catch (err) {
            console.error('Deposit approval error:', err);
            Toast.show(err.message || '<?= __('Failed to approve deposit') ?>', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    window.rejectDeposit = async function(btn) {
        if (!selectedDepositId) return;
        const reason = document.getElementById('dep-reject-reason').value.trim();
        if (!reason) {
            Toast.show('<?= __('Please enter a rejection reason') ?>', 'error');
            return;
        }

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span style="width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.2); border-top-color: #fff; border-radius: 50%; display: inline-block; animation: spin 0.6s linear infinite; margin-right: 8px;"></span> <?= __('Rejecting...') ?>';

        try {
            await apiRequest(`/api/admin/deposits/${selectedDepositId}/reject`, {
                method: 'POST',
                body: { reason }
            });
            Toast.show('<?= __('Deposit request rejected.') ?>');
            fetchDeposits();
        } catch (err) {
            Toast.show(err.message, 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    document.addEventListener('DOMContentLoaded', fetchDeposits);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
