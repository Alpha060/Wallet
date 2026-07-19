<?php
// src/views/admin/claims.php - Manage Referral Claims
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAdmin();
$csrfToken = generateCsrfToken();

$title = "Referral Claims";
$description = "Review and payout referral commissions milestone requests";
$activePage = "claims";

ob_start();
?>
<div class="split-layout">
    <!-- Left: List -->
    <div class="glass-panel" style="padding: 24px; border: 1px solid var(--border);">
        <h3 style="font-weight: 700; margin-bottom: 16px; color: var(--foreground);">Pending Claims</h3>
        <div class="pane-list-container" id="claims-queue-list">
            <!-- Claims queue dynamically loaded -->
        </div>
    </div>

    <!-- Right: Details -->
    <div class="glass-panel pane-details" style="padding: 24px; border: 1px solid var(--border);" id="claims-details-pane">
        <div style="text-align: center; color: var(--muted); padding: 40px 0;">Select a claim to review user roadmap milestone metrics and process</div>
    </div>
</div>

<script>
    function formatRupees(paise) {
        return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    let selectedClaimId = null;

    async function fetchClaims() {
        try {
            const data = await apiRequest('/api/referral-bonus/admin/pending?page=1&limit=20');
            const container = document.getElementById('claims-queue-list');
            container.innerHTML = '';
            document.getElementById('claims-details-pane').innerHTML = '<div style="text-align: center; color: var(--muted); padding: 40px 0;">Select a claim to review user roadmap milestone metrics and process</div>';

            if (data.claims.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: var(--muted); padding: 20px 0;">No pending claims found.</div>';
                return;
            }

            let foundSelected = false;
            data.claims.forEach(c => {
                const row = document.createElement('div');
                row.className = 'pane-item';
                if (selectedClaimId === c.id) {
                    row.classList.add('active');
                    foundSelected = true;
                }
                row.innerHTML = `
                    <div style="font-weight: 700; font-size: 0.9rem; color: var(--foreground);">${c.userName}</div>
                    <div style="font-size: 0.75rem; color: var(--muted); margin-top: 4px;">Claim: <strong style="color: var(--primary);">${formatRupees(c.amount)}</strong></div>
                `;
                row.addEventListener('click', () => {
                    document.querySelectorAll('#claims-queue-list .pane-item').forEach(x => x.classList.remove('active'));
                    row.classList.add('active');
                    selectedClaimId = c.id;
                    renderClaimDetails(c);
                });
                container.appendChild(row);
            });
            if (!foundSelected) {
                selectedClaimId = null;
                document.getElementById('claims-details-pane').innerHTML = '<div style="text-align: center; color: var(--muted); padding: 40px 0;">Select a claim to review user roadmap milestone metrics and process</div>';
            }
        } catch (err) {
            console.error('fetchClaims failed:', err);
            Toast.show(err.message || 'Failed to load claims', 'error');
        }
    }

    function renderClaimDetails(c) {
        const pane = document.getElementById('claims-details-pane');
        pane.innerHTML = `
            <h3 style="font-weight: 700; margin-bottom: 20px; color: var(--foreground);">Claim Verification</h3>
            <div style="font-size: 0.9rem; margin-bottom: 24px; line-height: 1.6; color: var(--foreground);">
                <div>Claimant Account: <strong>${c.userName}</strong> (${c.userEmail})</div>
                <div>Milestone Bonus: <strong>${formatRupees(c.amount)}</strong></div>
                <div>Earned from Friend: <strong>${c.referredUserName}</strong> (${c.referredUserEmail})</div>
                <div>Requested: <strong>${new Date(c.createdAt).toLocaleString('en-IN')}</strong></div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">Rejection Reason (if rejecting)</label>
                <input class="form-input" type="text" id="claim-reject-reason" placeholder="KYC mismatch, inactive referred user, etc.">
            </div>

            <div style="display: flex; gap: 12px;">
                <button class="btn-destructive" onclick="rejectClaim()" style="flex: 1;">Reject Claim</button>
                <button class="btn-primary" onclick="approveClaim()" style="flex: 1; background: var(--success); box-shadow: 0 4px 12px var(--success-glow); color: #000000;">Approve Payout</button>
            </div>
        `;
    }

    async function approveClaim() {
        if (!selectedClaimId) return;
        try {
            await apiRequest(`/api/referral-bonus/admin/claims/${selectedClaimId}/approve`, { method: 'POST' });
            Toast.show('Commission claim approved and credited.');
            fetchClaims();
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    async function rejectClaim() {
        if (!selectedClaimId) return;
        const reason = document.getElementById('claim-reject-reason').value.trim();
        if (!reason) {
            Toast.show('Please enter a rejection reason', 'error');
            return;
        }

        try {
            await apiRequest(`/api/referral-bonus/admin/claims/${selectedClaimId}/reject`, {
                method: 'POST',
                body: { reason }
            });
            Toast.show('Claim rejected.');
            fetchClaims();
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', fetchClaims);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
