<?php
// src/views/user/marketplace.php - Marketplace View Page
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAuth();

$title = "AeroPay - Marketplace";
$description = "Invest in high-yield assets to secure daily rewards";
$activePage = "marketplace";

ob_start();
?>
<div class="marketplace-grid" id="products-list">
    <!-- Products dynamically loaded -->
</div>

<!-- Purchase Confirmation Modal -->
<div class="modal-overlay" id="purchase-modal">
    <div class="modal-content">
        <h3 style="font-weight: 800; margin-bottom: 16px;">Confirm Purchase</h3>
        <p style="font-size: 0.95rem; color: var(--muted); margin-bottom: 24px; line-height: 1.5;" id="purchase-modal-text"></p>
        <div style="display: flex; gap: 12px;">
            <button class="btn-secondary" onclick="closePurchaseModal()" style="flex: 1;">Cancel</button>
            <button class="btn-primary" id="purchase-confirm-btn" style="flex: 1;">Confirm Purchase</button>
        </div>
    </div>
</div>

<script>
    // Formatters
    function formatRupees(paise) {
        return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    let purchaseTargetProduct = null;
    async function fetchMarketplace() {
        try {
            const data = await apiRequest('/api/products');
            const list = document.getElementById('products-list');
            list.innerHTML = '';

            data.products.forEach(p => {
                const card = document.createElement('div');
                card.className = 'glass-card marketplace-card';
                card.innerHTML = `
                    <div class="roi-badge"><span class="badge-roi">${p.dailyRewardPercent}% Daily ROI</span></div>
                    <img src="${p.imageUrl}" alt="${p.name}" style="width: 100%; height: 160px; object-fit: cover; border-radius: var(--radius-sm); margin-bottom: 16px;">
                    <h3 style="font-weight: 700; margin-bottom: 4px;">${p.name}</h3>
                    <p style="color: var(--muted); font-size: 0.8rem; margin-bottom: 16px;">Duration: ${p.durationDays} Days | Watch Time: ${p.adWatchSeconds}s</p>
                    
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: auto; border-top: 1px solid var(--border); padding-top: 16px;">
                        <div>
                            <span style="font-size: 0.75rem; color: var(--muted); display: block;">Purchase Price</span>
                            <span style="font-size: 1.2rem; font-weight: 800; color: var(--foreground);">${formatRupees(p.price)}</span>
                        </div>
                        <button class="btn-primary" onclick="openPurchaseModal('${p.id}', '${p.name}', ${p.price})" style="padding: 10px 20px;">Buy</button>
                    </div>
                `;
                list.appendChild(card);
            });
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    function openPurchaseModal(id, name, price) {
        purchaseTargetProduct = id;
        document.getElementById('purchase-modal-text').innerHTML = `Are you sure you want to purchase the asset <strong>"${name}"</strong> for <strong>${formatRupees(price)}</strong>?<br>Confirming will automatically debit this amount from your available balance.`;
        document.getElementById('purchase-modal').classList.add('active');
    }

    function closePurchaseModal() {
        document.getElementById('purchase-modal').classList.remove('active');
        purchaseTargetProduct = null;
    }

    document.getElementById('purchase-confirm-btn').addEventListener('click', async () => {
        if (!purchaseTargetProduct) return;
        try {
            const res = await apiRequest(`/api/products/buy/${purchaseTargetProduct}`, { method: 'POST' });
            Toast.show('Asset purchased successfully!');
            closePurchaseModal();
            window.location.href = '/investments';
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    });

    document.addEventListener('DOMContentLoaded', fetchMarketplace);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
