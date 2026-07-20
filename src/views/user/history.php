<?php
// src/views/user/history.php - Transaction History Log Page
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAuth();

$title = __("AeroPay - History");
$description = __("Track deposits, withdrawals, yield earnings, and sales");
$activePage = "history";

ob_start();
?>

<div class="glass-panel" style="padding: 24px; margin-bottom: 24px; display: flex; flex-wrap: wrap; gap: 16px; align-items: center; border: 1px solid var(--border);">
    <div>
        <label class="form-label" style="margin-bottom: 4px;"><?= __('Type Filter') ?></label>
        <select class="form-input" id="history-filter" style="width: 160px; padding: 8px 12px;">
            <option value="all"><?= __('All Transactions') ?></option>
            <option value="deposit"><?= __('Deposits') ?></option>
            <option value="withdrawal"><?= __('Withdrawals') ?></option>
            <option value="buy"><?= __('Asset Purchases') ?></option>
            <option value="sell"><?= __('Asset Sells') ?></option>
            <option value="reward"><?= __('Daily Rewards') ?></option>
        </select>
    </div>

    <div>
        <label class="form-label" style="margin-bottom: 4px;"><?= __('Start Date') ?></label>
        <input class="form-input" type="date" id="history-start" style="width: 160px; padding: 8px 12px;">
    </div>

    <div>
        <label class="form-label" style="margin-bottom: 4px;"><?= __('End Date') ?></label>
        <input class="form-input" type="date" id="history-end" style="width: 160px; padding: 8px 12px;">
    </div>

    <button class="btn-primary" onclick="fetchHistory()" style="margin-top: 20px; padding: 10px 20px;"><?= __('Apply Filters') ?></button>
</div>

<div class="glass-panel" style="padding: 24px; border: 1px solid var(--border);">
    <div id="history-ledger" style="display: flex; flex-direction: column; gap: 12px;">
        <!-- Transaction history dynamically loaded -->
    </div>
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px;">
        <button class="btn-secondary" id="history-prev-btn" onclick="prevHistoryPage()" style="padding: 8px 16px;"><?= __('Previous') ?></button>
        <span id="history-page-indicator" style="font-weight: 700; color: var(--foreground);"><?= __('Page 1 of 1') ?></span>
        <button class="btn-secondary" id="history-next-btn" onclick="nextHistoryPage()" style="padding: 8px 16px;"><?= __('Next') ?></button>
    </div>
</div>

<script>
    // Formatters
    function formatRupees(paise) {
        return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    let historyPage = 1;
    async function fetchHistory() {
        const filter = document.getElementById('history-filter').value;
        const start = document.getElementById('history-start').value;
        const end = document.getElementById('history-end').value;

        let path = `/api/wallet/transactions?page=${historyPage}&limit=10`;
        if (filter === 'deposit' || filter === 'withdrawal') {
            path += `&type=${filter}`;
        }

        try {
            const [data, inv] = await Promise.all([
                apiRequest(path),
                ['all', 'buy', 'sell', 'reward'].includes(filter) ? apiRequest('/api/products/investment-history') : Promise.resolve({ buys: [], sells: [], rewards: [] })
            ]);
            let list = data.transactions || [];

            // Load product buys/sells/rewards on client side filters
            if (['all', 'buy', 'sell', 'reward'].includes(filter)) {
                const buys = inv.buys.map(x => ({ ...x, type: 'buy', status: 'approved' }));
                const sells = inv.sells.map(x => ({ ...x, type: 'sell', status: 'completed' }));
                const rewards = inv.rewards.map(x => ({ ...x, type: 'reward', status: 'completed' }));

                let merged = [...list];
                if (filter === 'all') {
                    merged = [...merged, ...buys, ...sells, ...rewards];
                } else if (filter === 'buy') {
                    merged = buys;
                } else if (filter === 'sell') {
                    merged = sells;
                } else if (filter === 'reward') {
                    merged = rewards;
                }

                // Filter by dates
                if (start) {
                    const sDate = new Date(start);
                    sDate.setHours(0,0,0,0);
                    merged = merged.filter(x => new Date(x.createdAt) >= sDate);
                }
                if (end) {
                    const eDate = new Date(end);
                    eDate.setHours(23,59,59,999);
                    merged = merged.filter(x => new Date(x.createdAt) <= eDate);
                }

                // Sort
                merged.sort((a,b) => new Date(b.createdAt) - new Date(a.createdAt));

                const limit = 10;
                const totalPages = Math.ceil(merged.length / limit) || 1;
                document.getElementById('history-page-indicator').innerText = `<?= __('Page') ?> ${historyPage} <?= __('of') ?> ${totalPages}`;
                document.getElementById('history-prev-btn').disabled = (historyPage === 1);
                document.getElementById('history-next-btn').disabled = (historyPage === totalPages);

                renderHistoryList(merged.slice((historyPage - 1) * limit, historyPage * limit));
            } else {
                // Date filters on deposits/withdrawals
                if (start) {
                    const sDate = new Date(start);
                    sDate.setHours(0,0,0,0);
                    list = list.filter(x => new Date(x.createdAt) >= sDate);
                }
                if (end) {
                    const eDate = new Date(end);
                    eDate.setHours(23,59,59,999);
                    list = list.filter(x => new Date(x.createdAt) <= eDate);
                }

                document.getElementById('history-page-indicator').innerText = `<?= __('Page') ?> ${historyPage} <?= __('of') ?> ${data.totalPages || 1}`;
                document.getElementById('history-prev-btn').disabled = (historyPage === 1);
                document.getElementById('history-next-btn').disabled = (historyPage === (data.totalPages || 1));
                
                renderHistoryList(list);
            }
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    function renderHistoryList(arr) {
        const container = document.getElementById('history-ledger');
        container.innerHTML = '';

        if (arr.length === 0) {
            container.innerHTML = '<div style="text-align: center; color: var(--muted); padding: 32px 0;"><?= __('No matching transactions found.') ?></div>';
            return;
        }

        arr.forEach(t => {
            const row = document.createElement('div');
            row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 14px; border-bottom: 1px solid var(--border);';
            
            const isCredit = ['deposit', 'reward', 'sell'].includes(t.type);
            const color = isCredit ? 'var(--success)' : 'var(--destructive)';
            const prefix = isCredit ? '+' : '-';
            const date = new Date(t.createdAt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            
            let label = t.type;
            if (t.name) {
                label = t.type === 'buy' ? `<?= __('Purchase') ?> ${t.name}` : (t.type === 'sell' ? `<?= __('Early Refund') ?> ${t.name}` : `<?= __('Reward:') ?> ${t.name}`);
            }

            row.innerHTML = `
                <div>
                    <span style="font-weight: 700; font-size: 0.95rem; text-transform: capitalize; color: var(--foreground);">${label}</span>
                    <span style="font-size: 0.75rem; color: var(--muted); display: block;">${date} | <?= __('Status:') ?> <strong style="text-transform: capitalize;">${t.status}</strong></span>
                    ${t.rejectionReason ? `<span style="font-size: 0.7rem; color: var(--destructive); display: block;"><?= __('Reason:') ?> ${t.rejectionReason}</span>` : ''}
                </div>
                <span style="font-weight: 850; font-size: 1.1rem; color: ${color};">${prefix}${formatRupees(t.amount)}</span>
            `;
            container.appendChild(row);
        });
    }

    function prevHistoryPage() {
        if (historyPage > 1) {
            historyPage--;
            fetchHistory();
        }
    }

    function nextHistoryPage() {
        historyPage++;
        fetchHistory();
    }

    document.addEventListener('DOMContentLoaded', fetchHistory);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
