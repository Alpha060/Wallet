<?php
// src/views/user/dashboard.php - User Overview Page
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAuth();

$title = __("AeroPay - Overview");
$description = __("Your AeroPay balance and transaction highlights");
$activePage = "dashboard";

ob_start();
?>

<!-- Apple Card Style Wallet -->
<div style="background: var(--card); border: 1px solid var(--border); border-radius: 24px; padding: 32px; margin-bottom: 32px; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1); position: relative; overflow: hidden; max-width: 500px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; position: relative; z-index: 1;">
        <div>
            <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px;"><?= __('AeroPay Card') ?></div>
            <div style="font-size: 2.8rem; font-weight: 800; letter-spacing: -0.05em; color: var(--foreground);" id="wallet-balance">₹0.00</div>
            <div style="color: var(--muted); font-size: 0.9rem; font-weight: 500; margin-top: 4px;"><?= htmlspecialchars($user['name'] ?: __('Cardholder')); ?></div>
        </div>
        <!-- Logo -->
        <img src="/images/aeropay-logo.png" alt="AeroPay Logo" style="width: 48px; height: 48px; object-fit: contain; opacity: 0.25;">
    </div>
    
    <div style="display: flex; gap: 12px; margin-top: 32px; position: relative; z-index: 1;">
        <a href="/deposit" class="btn-primary" style="flex: 1; text-align: center; text-decoration: none;">📥 <?= __('Deposit') ?></a>
        <a href="/withdraw" class="btn-secondary" style="flex: 1; text-align: center; text-decoration: none;">📤 <?= __('Withdraw') ?></a>
    </div>
</div>

<div class="bento-grid">
    <div class="glass-card">
        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;"><?= __('Total Deposits') ?></div>
        <h3 style="font-size: 1.6rem; font-weight: 800; color: var(--success);" id="total-deposits">₹0.00</h3>
    </div>
    <div class="glass-card">
        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 6px;"><?= __('Total Withdrawals') ?></div>
        <h3 style="font-size: 1.6rem; font-weight: 800; color: var(--destructive);" id="total-withdrawals">₹0.00</h3>
    </div>
</div>

<div class="glass-panel" style="padding: 24px; margin-top: 32px;">
    <h3 style="font-weight: 700; margin-bottom: 20px;"><?= __('Recent Activity') ?></h3>
    <div id="recent-transactions" style="display: flex; flex-direction: column; gap: 12px;">
        <!-- Recent transactions dynamically loaded -->
    </div>
</div>

<script>
    // Formatters
    function formatRupees(paise) {
        return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    async function fetchOverview() {
        try {
            const [balData, txData, summary] = await Promise.all([
                apiRequest('/api/wallet/balance'),
                apiRequest('/api/wallet/transactions?page=1&limit=5'),
                apiRequest('/api/wallet/summary')
            ]);

            document.getElementById('wallet-balance').innerText = formatRupees(balData.balance);
            
            const recentList = document.getElementById('recent-transactions');
            recentList.innerHTML = '';

            if (txData.transactions.length === 0) {
                recentList.innerHTML = '<div style="text-align: center; color: var(--muted); font-size: 0.85rem; padding: 12px 0;"><?= __('No transactions recorded.') ?></div>';
            }

            txData.transactions.forEach(t => {
                const row = document.createElement('div');
                row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--border);';
                const isCredit = t.type === 'deposit';
                const symbol = isCredit ? '↘️' : '↗️';
                const amtColor = isCredit ? 'var(--success)' : 'var(--destructive)';
                const prefix = isCredit ? '+' : '-';
                const date = new Date(t.createdAt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });

                row.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 1.1rem;">${symbol}</span>
                        <div>
                            <span style="font-weight: 700; font-size: 0.9rem; text-transform: capitalize;">${t.type}</span>
                            <span style="font-size: 0.75rem; color: var(--muted); display: block;">${date} | <?= __('Status:') ?> <span style="font-weight: 600;">${t.status}</span></span>
                        </div>
                    </div>
                    <span style="font-weight: 850; color: ${amtColor};">${prefix}${formatRupees(t.amount)}</span>
                `;
                recentList.appendChild(row);
            });

            document.getElementById('total-deposits').innerText = formatRupees(summary.totalApprovedDeposits);
            document.getElementById('total-withdrawals').innerText = formatRupees(summary.totalCompletedWithdrawals);
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', fetchOverview);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
