<?php
// src/views/admin/dashboard.php - Admin Dashboard statistics
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAdmin();
$csrfToken = generateCsrfToken();

$title = "Dashboard";
$description = "Lifetime statistics and pending transaction queues";
$activePage = "dashboard";

ob_start();
?>
<div class="bento-grid">
    <div class="glass-card" id="card-pending-deposits">
        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Pending Deposits</div>
        <h2 style="font-size: 2.2rem; font-weight: 800; color: var(--foreground);" id="stats-pending-deposits">0</h2>
        <a href="/admin/deposits" class="btn-secondary" style="margin-top: 12px; padding: 6px 12px; font-size: 0.75rem; text-decoration: none;">Manage Queue</a>
    </div>
    <div class="glass-card" id="card-pending-withdrawals">
        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Pending Withdrawals</div>
        <h2 style="font-size: 2.2rem; font-weight: 800; color: var(--foreground);" id="stats-pending-withdrawals">0</h2>
        <a href="/admin/withdrawals" class="btn-secondary" style="margin-top: 12px; padding: 6px 12px; font-size: 0.75rem; text-decoration: none;">Manage Queue</a>
    </div>
    <div class="glass-card" id="card-pending-claims">
        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Pending Bonus Claims</div>
        <h2 style="font-size: 2.2rem; font-weight: 800; color: var(--foreground);" id="stats-pending-claims">0</h2>
        <a href="/admin/claims" class="btn-secondary" style="margin-top: 12px; padding: 6px 12px; font-size: 0.75rem; text-decoration: none;">Manage Queue</a>
    </div>
</div>

<div class="bento-grid" style="margin-top: 32px;">
    <div class="glass-card">
        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Total Approved Deposits</div>
        <h2 style="font-size: 2.2rem; font-weight: 800; color: var(--success);" id="stats-approved-deposits">₹0.00</h2>
    </div>
    <div class="glass-card">
        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Total Completed Withdrawals</div>
        <h2 style="font-size: 2.2rem; font-weight: 800; color: var(--destructive);" id="stats-completed-withdrawals">₹0.00</h2>
    </div>
</div>

<script>
    function formatRupees(paise) {
        return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    async function fetchStatistics() {
        try {
            const data = await apiRequest('/api/admin/statistics');
            
            document.getElementById('stats-pending-deposits').innerText = data.pendingDepositsCount;
            document.getElementById('stats-pending-withdrawals').innerText = data.pendingWithdrawalsCount;
            document.getElementById('stats-pending-claims').innerText = data.pendingClaimsCount;
            
            document.getElementById('stats-approved-deposits').innerText = formatRupees(data.totalApprovedDeposits);
            document.getElementById('stats-completed-withdrawals').innerText = formatRupees(data.totalCompletedWithdrawals);

            // Pulse effects
            document.getElementById('card-pending-deposits').classList.toggle('pulse', data.pendingDepositsCount > 0);
            document.getElementById('card-pending-withdrawals').classList.toggle('pulse', data.pendingWithdrawalsCount > 0);
            document.getElementById('card-pending-claims').classList.toggle('pulse', data.pendingClaimsCount > 0);
        } catch (err) {
            Toast.show('Failed to fetch statistics', 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', fetchStatistics);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
