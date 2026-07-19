<?php
// src/views/user/referrals.php - Referrals Page
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAuth();

$title = "AeroPay - Referral Program";
$description = "Invite your friends to earn 5% cash rewards on their deposits";
$activePage = "referrals";

ob_start();
?>

<div class="bento-grid">
    <div class="glass-card">
        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">My Referral Code</div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <h2 style="font-size: 1.8rem; font-weight: 800; font-family: monospace; color: var(--foreground);" id="my-referral-code">------</h2>
            <button class="btn-secondary" onclick="copyReferralCode()" style="padding: 8px 12px; font-size: 0.8rem;">Copy</button>
        </div>
    </div>
    <div class="glass-card">
        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Claimable Commissions</div>
        <h2 style="font-size: 1.8rem; font-weight: 800; color: var(--success); margin-bottom: 8px;" id="claimable-amount">₹0.00</h2>
        <button class="btn-primary" onclick="claimAllBonuses()" style="padding: 8px 16px; font-size: 0.8rem;" id="claim-all-btn">Claim All</button>
    </div>
    <div class="glass-card">
        <div style="color: var(--muted); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">Roadmap Progress</div>
        <h3 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 6px; color: var(--foreground);" id="referral-roadmap-text">0 / 5 Referrals</h3>
        <div class="progress-bar-container"><div class="progress-bar" id="referral-roadmap-bar" style="width: 0%;"></div></div>
        <p style="font-size: 0.75rem; color: var(--muted); margin-top: 6px;">Invited members with active deposits</p>
    </div>
</div>

<div class="glass-panel" style="padding: 24px; margin-top: 32px; border: 1px solid var(--border);">
    <h3 style="font-weight: 700; margin-bottom: 20px;">Referred Members</h3>
    <div id="referred-members" style="display: flex; flex-direction: column; gap: 12px;">
        <!-- Referred members dynamic loading -->
    </div>
</div>

<div class="glass-panel" style="padding: 24px; margin-top: 32px; border: 1px solid var(--border);">
    <h3 style="font-weight: 700; margin-bottom: 20px;">Commissions Ledger</h3>
    <div id="unclaimed-bonuses-list" style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
        <!-- Unclaimed bonuses lists -->
    </div>
    <h3 style="font-weight: 700; margin-bottom: 20px; border-top: 1px solid var(--border); padding-top: 20px; color: var(--foreground);">Commission Claims History</h3>
    <div id="claim-history-list" style="display: flex; flex-direction: column; gap: 12px;">
        <!-- Claims logs -->
    </div>
</div>

<script>
    // Formatters
    function formatRupees(paise) {
        return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function copyReferralCode() {
        const code = document.getElementById('my-referral-code').innerText;
        navigator.clipboard.writeText(code).then(() => {
            Toast.show('Referral code copied to clipboard!', 'success');
        }).catch(() => {
            Toast.show('Failed to copy code', 'error');
        });
    }

    async function fetchReferrals() {
        try {
            const [codeData, stats, bonusStats, listData, unclaimedData, claimHistory] = await Promise.all([
                apiRequest('/api/referrals/my-code'),
                apiRequest('/api/referrals/stats'),
                apiRequest('/api/referral-bonus/stats'),
                apiRequest('/api/referrals/my-referrals'),
                apiRequest('/api/referral-bonus/unclaimed'),
                apiRequest('/api/referral-bonus/claim-history')
            ]);

            document.getElementById('my-referral-code').innerText = codeData.referralCode;
            document.getElementById('referral-roadmap-text').innerText = `${stats.confirmedReferrals} / ${stats.requiredReferrals} Referrals`;
            
            const percent = stats.requiredReferrals > 0 ? Math.min(100, (stats.confirmedReferrals / stats.requiredReferrals) * 100) : 100;
            document.getElementById('referral-roadmap-bar').style.width = `${percent}%`;

            // Unclaimed commissions
            document.getElementById('claimable-amount').innerText = formatRupees(bonusStats.unclaimedAmount);
            document.getElementById('claim-all-btn').disabled = (bonusStats.unclaimedAmount === 0);

            // Load list of referred members
            const membersDiv = document.getElementById('referred-members');
            membersDiv.innerHTML = '';

            if (listData.referrals.length === 0) {
                membersDiv.innerHTML = '<div style="text-align: center; color: var(--muted); font-size: 0.85rem; padding: 12px 0;">No members referred.</div>';
            }

            listData.referrals.forEach(m => {
                const row = document.createElement('div');
                row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid var(--border);';
                const statusColor = m.isConfirmed ? 'var(--success)' : 'var(--muted)';
                const statusText = m.isConfirmed ? '✓ Active Investor' : '⌛ Pending Deposit';

                row.innerHTML = `
                    <div>
                        <span style="font-weight: 700; font-size: 0.85rem; color: var(--foreground);">${m.name || 'Anonymous User'}</span>
                        <span style="font-size: 0.7rem; color: var(--muted); display: block;">${m.email}</span>
                    </div>
                    <span style="font-size: 0.8rem; font-weight: 700; color: ${statusColor};">${statusText}</span>
                `;
                membersDiv.appendChild(row);
            });

            // Load commissions list
            const unclaimedList = document.getElementById('unclaimed-bonuses-list');
            unclaimedList.innerHTML = '';

            if (unclaimedData.length === 0) {
                unclaimedList.innerHTML = '<div style="text-align: center; color: var(--muted); font-size: 0.85rem; padding: 12px 0;">No commissions pending.</div>';
            }

            unclaimedData.forEach(bonus => {
                const row = document.createElement('div');
                row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--border);';
                row.innerHTML = `
                    <div>
                        <span style="font-weight: 700; font-size: 0.85rem; color: var(--foreground);">Bonus from ${bonus.referredUserName}</span>
                        <span style="font-size: 0.7rem; color: var(--muted); display: block;">Deposit amount: ${formatRupees(bonus.depositAmount)}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-weight: 850; color: var(--success);">${formatRupees(bonus.bonusAmount)}</span>
                        <button class="btn-primary" onclick="claimBonus('${bonus.id}')" style="padding: 6px 12px; font-size: 0.75rem;">Claim</button>
                    </div>
                `;
                unclaimedList.appendChild(row);
            });

            // Load claims requests history
            const claimsContainer = document.getElementById('claim-history-list');
            claimsContainer.innerHTML = '';

            if (claimHistory.claims.length === 0) {
                claimsContainer.innerHTML = '<div style="text-align: center; color: var(--muted); font-size: 0.85rem; padding: 12px 0;">No claims logged.</div>';
            }

            claimHistory.claims.forEach(c => {
                const row = document.createElement('div');
                row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid var(--border);';
                const statusColor = c.status === 'approved' ? 'var(--success)' : (c.status === 'rejected' ? 'var(--destructive)' : 'var(--muted)');
                const date = new Date(c.createdAt).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });

                row.innerHTML = `
                    <div>
                        <span style="font-weight: 700; font-size: 0.85rem; color: var(--foreground);">Claim ID: ${c.id.substring(0, 8)}...</span>
                        <span style="font-size: 0.7rem; color: var(--muted); display: block;">Submitted: ${date} | Status: <strong style="color: ${statusColor}; text-transform: capitalize;">${c.status}</strong></span>
                        ${c.rejectionReason ? `<span style="font-size: 0.7rem; color: var(--destructive); display: block;">Reason: ${c.rejectionReason}</span>` : ''}
                    </div>
                    <span style="font-weight: 850; color: var(--success);">${formatRupees(c.amount)}</span>
                `;
                claimsContainer.appendChild(row);
            });
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    async function claimBonus(bonusId) {
        try {
            await apiRequest(`/api/referral-bonus/claim/${bonusId}`, { method: 'POST' });
            Toast.show('Claim submitted! Awaiting admin approval.');
            fetchReferrals();
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    async function claimAllBonuses() {
        try {
            const data = await apiRequest('/api/referral-bonus/unclaimed');
            let count = 0;
            for (const b of data) {
                await apiRequest(`/api/referral-bonus/claim/${b.id}`, { method: 'POST' });
                count++;
            }
            if (count > 0) {
                Toast.show(`Submitted ${count} claim requests!`);
                fetchReferrals();
            }
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', fetchReferrals);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
