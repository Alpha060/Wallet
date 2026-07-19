<?php
// src/views/admin/deposits.php - Manage Deposits
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAdmin();
$csrfToken = generateCsrfToken();

$title = "Manage Deposits";
$description = "Approve or reject pending user deposit screenshots";
$activePage = "deposits";

ob_start();
?>
<div class="split-layout">
    <!-- Left: Pending list -->
    <div class="glass-panel" style="padding: 24px; border: 1px solid var(--border);">
        <h3 style="font-weight: 700; margin-bottom: 16px; color: var(--foreground);">Request Queue</h3>
        <div class="pane-list-container" id="dep-queue-list">
            <!-- Pending deposits loaded dynamically -->
        </div>
    </div>

    <!-- Right: Pane details -->
    <div class="glass-panel pane-details" style="padding: 24px; border: 1px solid var(--border);" id="dep-details-pane">
        <div style="text-align: center; color: var(--muted); padding: 40px 0;">Select a deposit request to inspect proof and process</div>
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
            document.getElementById('dep-details-pane').innerHTML = '<div style="text-align: center; color: var(--muted); padding: 40px 0;">Select a deposit request to inspect proof and process</div>';

            if (data.deposits.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: var(--muted); padding: 20px 0;">No pending deposits found.</div>';
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
                    <div style="font-size: 0.85rem; color: var(--muted); margin-bottom: 4px;">User: ${d.userName}</div>
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
                document.getElementById('dep-details-pane').innerHTML = '<div style="color:var(--muted); text-align:center; padding: 40px;">Select a request to review</div>';
            }
        } catch (err) {
            console.error('fetchDeposits failed:', err);
            Toast.show(err.message || 'Failed to load deposits', 'error');
        }
    }

    function renderDepositDetails(d) {
        const pane = document.getElementById('dep-details-pane');
        pane.innerHTML = `
            <h3 style="font-weight: 700; margin-bottom: 20px; color: var(--foreground);">Request Audit</h3>
            <div style="font-size: 0.9rem; margin-bottom: 16px; line-height: 1.6; color: var(--foreground);">
                <div>User: <strong>${d.userName}</strong> (${d.userEmail})</div>
                <div>Amount: <strong>${formatRupees(d.amount)}</strong></div>
                <div>Tx ID: <strong>${d.transactionId || 'Not provided'}</strong></div>
                <div>Submitted: <strong>${new Date(d.createdAt).toLocaleString('en-IN')}</strong></div>
            </div>

            <div class="magnifier-container" style="margin-bottom: 24px; position: relative;">
                <img id="dep-proof-img" src="${d.paymentProofUrl}" alt="Proof" style="width: 100%; max-height: 280px; object-fit: contain; border-radius: var(--radius-sm);">
                <div id="magnifier-lens" class="magnifier-glass"></div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">Rejection Reason (if rejecting)</label>
                <input class="form-input" type="text" id="dep-reject-reason" placeholder="Incorrect Transaction ID, amount mismatched, etc.">
            </div>

            <div style="display: flex; gap: 12px;">
                <button class="btn-destructive" onclick="rejectDeposit()" style="flex: 1;">Reject Request</button>
                <button class="btn-primary" onclick="approveDeposit()" style="flex: 1; background: var(--success); box-shadow: 0 4px 12px var(--success-glow); color: #000000;">Approve Deposit</button>
            </div>
        `;
        // Initialize Magnifier
        setTimeout(() => {
            if (window.ImageMagnifier) {
                ImageMagnifier.init('dep-proof-img', 'magnifier-lens', 2);
            }
        }, 100);
    }

    async function approveDeposit() {
        if (!selectedDepositId) return;
        const conf = confirm('Confirm approval of this deposit? User wallet balance will be updated.');
        if (!conf) return;

        try {
            await apiRequest(`/api/admin/deposits/${selectedDepositId}/approve`, { 
                method: 'POST',
                body: {}
            });
            Toast.show('Deposit approved and credited.');
            fetchDeposits();
        } catch (err) {
            console.error('Deposit approval error:', err);
            Toast.show(err.message || 'Failed to approve deposit', 'error');
        }
    }

    async function rejectDeposit() {
        if (!selectedDepositId) return;
        const reason = document.getElementById('dep-reject-reason').value.trim();
        if (!reason) {
            Toast.show('Please enter a rejection reason', 'error');
            return;
        }

        try {
            await apiRequest(`/api/admin/deposits/${selectedDepositId}/reject`, {
                method: 'POST',
                body: { reason }
            });
            Toast.show('Deposit request rejected.');
            fetchDeposits();
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', fetchDeposits);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
