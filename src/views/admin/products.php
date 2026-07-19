<?php
// src/views/admin/products.php - Yield Assets Manager
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAdmin();
$csrfToken = generateCsrfToken();

$title = "Yield Assets";
$description = "Create and schedule video ad links for investment products";
$activePage = "products";

ob_start();
?>
<div style="display: none; justify-content: flex-end; margin-bottom: 24px;" id="products-top-bar">
    <button class="btn-primary" onclick="openProductModal()" style="display: flex; align-items: center; gap: 8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
        Create Product
    </button>
</div>

<!-- Empty State -->
<div id="products-empty-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: calc(100vh - 200px); text-align: center; gap: 24px; padding: 40px 20px;">
    <div style="width: 88px; height: 88px; border-radius: 50%; background: var(--primary-glow); display: flex; align-items: center; justify-content: center;">
        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><rect width="20" height="5" x="2" y="7" rx="1"/></svg>
    </div>
    <div>
        <h3 style="font-weight: 800; color: var(--foreground); margin: 0 0 8px 0; font-size: 1.4rem;">No Yield Assets Yet</h3>
        <p style="color: var(--muted); font-size: 0.9rem; margin: 0; max-width: 300px;">Create your first investment product to start earning.</p>
    </div>
    <button class="btn-primary" onclick="openProductModal()" style="padding: 14px 36px; font-size: 1rem; display: flex; align-items: center; gap: 10px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
        Create Product
    </button>
</div>

<div class="marketplace-grid" id="products-list" style="display: none; margin-bottom: 40px;">
    <!-- Admin products list -->
</div>

<!-- Create/Edit Product Modal -->
<div class="modal-overlay" id="product-modal">
    <div class="modal-content" style="max-width: 520px;">
        <h3 style="font-weight: 800; margin-bottom: 20px; color: var(--foreground);" id="product-modal-title">Create Yield Product</h3>
        <form id="product-form" enctype="multipart/form-data">
            <input type="hidden" id="edit-product-id">
            
            <div class="form-group">
                <label class="form-label">Asset Name</label>
                <input class="form-input" type="text" id="prod-name" name="name" required placeholder="Aero Yield Gold">
            </div>

            <div class="form-group">
                <label class="form-label">Purchase Price (in Rupees)</label>
                <input class="form-input" type="text" id="prod-price" name="price" required placeholder="e.g. 5000">
            </div>

            <div class="form-group">
                <label class="form-label">Contract Duration (Days)</label>
                <input class="form-input" type="number" id="prod-duration" name="durationDays" required min="1" placeholder="30">
            </div>

            <div class="form-group">
                <label class="form-label">Daily Reward Percentage ROI (%)</label>
                <input class="form-input" type="text" id="prod-reward" name="dailyRewardPercent" required placeholder="e.g. 2.50">
            </div>

            <div class="form-group">
                <label class="form-label">Daily Video Ad Watch Time (Seconds)</label>
                <input class="form-input" type="number" id="prod-seconds" name="adWatchSeconds" required min="10" max="3600" value="120">
            </div>

            <div class="form-group" id="prod-file-group">
                <label class="form-label">Asset Photo/Banner Image</label>
                <input class="form-input" type="file" id="prod-image" name="productImage" accept="image/*">
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button type="button" class="btn-secondary" onclick="closeProductModal()" style="flex: 1;">Cancel</button>
                <button type="submit" class="btn-primary" style="flex: 1; color: #000000;" id="prod-submit-btn">Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Ad Scheduler Modal -->
<div class="modal-overlay" id="ad-scheduler-modal">
    <div class="modal-content" style="max-width: 600px;">
        <h3 style="font-weight: 800; margin-bottom: 16px; color: var(--foreground);">Configure Day-by-Day Ad Schedules</h3>
        <p style="font-size: 0.8rem; color: var(--muted); margin-bottom: 20px;">Provide custom video links (.mp4 or Youtube) for each day of the contract. If empty, the backup global Daily Ad will be shown.</p>
        
        <div class="ad-links-grid" id="ad-scheduler-grid">
            <!-- Inputs generated dynamically based on duration -->
        </div>

        <div style="display: flex; gap: 12px; margin-top: 16px;">
            <button type="button" class="btn-secondary" onclick="closeAdScheduler()" style="flex: 1;">Cancel</button>
            <button type="button" class="btn-primary" onclick="saveAdSchedule()" style="flex: 1; color: #000000;">Save Schedules</button>
        </div>
    </div>
</div>

<script>
    function formatRupees(paise) {
        return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    let scheduleTargetProduct = null;
    
    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, ch => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[ch]));
    }

    async function fetchProducts() {
        const list = document.getElementById('products-list');
        const emptyState = document.getElementById('products-empty-state');
        const topBar = document.getElementById('products-top-bar');

        try {
            const data = await apiRequest('/api/products/admin/all');
            list.innerHTML = '';

            if (!data.products || data.products.length === 0) {
                list.style.display = 'none';
                topBar.style.display = 'none';
                emptyState.style.display = 'flex';
                return;
            }

            emptyState.style.display = 'none';
            topBar.style.display = 'flex';
            list.style.display = '';

            data.products.forEach(p => {
                const card = document.createElement('div');
                card.className = 'glass-card';
                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <span class="badge-roi" style="background: var(--primary-glow); color: var(--primary);">${escapeHtml(p.dailyRewardPercent)}% ROI</span>
                        <span style="font-size: 0.75rem; color: ${p.isActive ? 'var(--success)' : 'var(--muted)'}; font-weight: 700;">${p.isActive ? 'Active' : 'Draft'}</span>
                    </div>
                    <img src="${escapeHtml(p.imageUrl)}" alt="${escapeHtml(p.name)}" style="width: 100%; height: 140px; object-fit: cover; border-radius: var(--radius-sm); margin-bottom: 16px; border: 1px solid var(--border);">
                    <h3 style="font-weight: 700; margin-bottom: 4px; color: var(--foreground);">${escapeHtml(p.name)}</h3>
                    <p style="color: var(--muted); font-size: 0.8rem; margin-bottom: 12px;">Price: <strong style="color: var(--foreground);">${formatRupees(p.price)}</strong> | Duration: ${escapeHtml(p.durationDays)} Days</p>

                    <div style="display: flex; flex-direction: column; gap: 8px; border-top: 1px solid var(--border); padding-top: 16px;">
                        <button class="btn-primary" data-action="schedule" style="font-size: 0.8rem; padding: 8px 12px; color: #000000;">📅 Schedule Ad Links</button>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn-secondary" data-action="edit" style="flex: 1; font-size: 0.8rem; padding: 8px;">Edit</button>
                            <button class="btn-destructive" data-action="delete" style="flex: 1; font-size: 0.8rem; padding: 8px;">Delete</button>
                        </div>
                    </div>
                `;
                card.querySelector('[data-action="schedule"]').addEventListener('click', () => openAdScheduler(p.id, p.durationDays));
                card.querySelector('[data-action="edit"]').addEventListener('click', () => editProduct(p.id, p.name, p.price, p.durationDays, p.dailyRewardPercent, p.adWatchSeconds));
                card.querySelector('[data-action="delete"]').addEventListener('click', () => deleteProduct(p.id, p.name));
                list.appendChild(card);
            });
        } catch (err) {
            console.error('fetchProducts failed:', err);
            // Show empty state on error too
            list.style.display = 'none';
            topBar.style.display = 'none';
            emptyState.style.display = 'flex';
        }
    }

    function openProductModal() {
        document.getElementById('product-form').reset();
        document.getElementById('edit-product-id').value = '';
        document.getElementById('product-modal-title').innerText = 'Create Yield Product';
        document.getElementById('prod-file-group').style.display = 'block';
        document.getElementById('product-modal').classList.add('active');
    }

    function closeProductModal() {
        document.getElementById('product-modal').classList.remove('active');
    }

    function editProduct(id, name, price, duration, reward, seconds) {
        document.getElementById('edit-product-id').value = id;
        document.getElementById('prod-name').value = name;
        document.getElementById('prod-price').value = price / 100; // paise to rupees
        document.getElementById('prod-duration').value = duration;
        document.getElementById('prod-reward').value = reward;
        document.getElementById('prod-seconds').value = seconds;

        document.getElementById('product-modal-title').innerText = 'Edit Yield Product';
        document.getElementById('prod-file-group').style.display = 'none'; // hide file input on update
        document.getElementById('product-modal').classList.add('active');
    }

    // Auto format currency
    formatCurrencyInput(document.getElementById('prod-price'));

    document.getElementById('product-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('prod-submit-btn');
        submitBtn.disabled = true;
        submitBtn.innerText = 'Saving...';

        const editId = document.getElementById('edit-product-id').value;
        const price = parseFloat(document.getElementById('prod-price').value);

        if (editId) {
            // Update Product API
            try {
                await apiRequest(`/api/products/admin/${editId}`, {
                    method: 'PUT',
                    body: {
                        name: document.getElementById('prod-name').value,
                        price: Math.round(price * 100),
                        durationDays: parseInt(document.getElementById('prod-duration').value),
                        dailyRewardPercent: parseFloat(document.getElementById('prod-reward').value),
                        adWatchSeconds: parseInt(document.getElementById('prod-seconds').value)
                    }
                });
                Toast.show('Product updated successfully!');
                closeProductModal();
                fetchProducts();
            } catch (err) {
                Toast.show(err.message, 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Save Product';
            }
        } else {
            // Create Product API
            const formData = new FormData(document.getElementById('product-form'));
            formData.set('price', Math.round(price * 100)); // Overwrite in paise

            try {
                await apiRequest('/api/products/admin/create', {
                    method: 'POST',
                    body: formData,
                    isMultipart: true
                });
                Toast.show('Product created successfully!');
                closeProductModal();
                fetchProducts();
            } catch (err) {
                Toast.show(err.message, 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Save Product';
            }
        }
    });

    async function deleteProduct(id, name) {
        const conf = confirm(`Are you sure you want to delete "${name}"?`);
        if (!conf) return;

        try {
            await apiRequest(`/api/products/admin/${id}`, { method: 'DELETE' });
            Toast.show('Product deleted successfully.');
            fetchProducts();
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    async function openAdScheduler(productId, duration) {
        scheduleTargetProduct = productId;
        try {
            const data = await apiRequest(`/api/products/admin/${productId}/ad-links`);
            const grid = document.getElementById('ad-scheduler-grid');
            grid.innerHTML = '';

            const linksMap = {};
            data.links.forEach(l => { linksMap[l.dayNumber] = l.videoUrl; });

            for (let i = 1; i <= duration; i++) {
                const rowLabel = document.createElement('label');
                rowLabel.innerText = `Day ${i}`;
                rowLabel.style.fontWeight = 'bold';
                rowLabel.style.color = 'var(--foreground)';
                
                const rowInput = document.createElement('input');
                rowInput.className = 'form-input';
                rowInput.type = 'url';
                rowInput.placeholder = 'https://example.com/video-url.mp4';
                rowInput.value = linksMap[i] || '';
                rowInput.setAttribute('data-day', i);

                grid.appendChild(rowLabel);
                grid.appendChild(rowInput);
            }

            document.getElementById('ad-scheduler-modal').classList.add('active');
        } catch (err) {
            Toast.show('Failed to load ad link schedules', 'error');
        }
    }

    function closeAdScheduler() {
        document.getElementById('ad-scheduler-modal').classList.remove('active');
        scheduleTargetProduct = null;
    }

    async function saveAdSchedule() {
        if (!scheduleTargetProduct) return;
        const inputs = document.querySelectorAll('#ad-scheduler-grid input');
        const links = [];

        inputs.forEach(inp => {
            const url = inp.value.trim();
            if (url) {
                links.push({
                    dayNumber: parseInt(inp.getAttribute('data-day')),
                    videoUrl: url
                });
            }
        });

        try {
            await apiRequest(`/api/products/admin/${scheduleTargetProduct}/ad-links`, {
                method: 'PUT',
                body: { links }
            });
            Toast.show('Product ad links schedule saved!');
            closeAdScheduler();
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', fetchProducts);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
