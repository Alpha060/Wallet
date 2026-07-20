<?php
// src/views/admin/settings.php - System Configuration Manager
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAdmin();
$csrfToken = generateCsrfToken();

$title = __("System Configuration");
$description = __("Configure receiving gateway codes, primary numbers and payout limits");
$activePage = "settings";

ob_start();
?>
<div class="bento-grid">
    <!-- Primary QR Code -->
    <form id="settings-qr-form" class="glass-card" style="display: flex; flex-direction: column; gap: 16px; border: 1px solid var(--border);">
        <h3 style="font-weight: 700; color: var(--foreground);"><?= __('Receiving QR Code') ?></h3>
        <img id="settings-qr-preview" src="" alt="System QR" style="width: 150px; height: 150px; object-fit: contain; align-self: center; border-radius: var(--radius-sm); border: 1px solid var(--border); display: none;">
        <div class="form-group">
            <label class="form-label"><?= __('Upload New QR Code Image') ?></label>
            <input class="form-input" type="file" name="qrCode" required accept="image/*">
        </div>
        <button type="submit" class="btn-primary" style="margin-top: auto; color: #000000;"><?= __('Upload QR Code') ?></button>
    </form>

    <!-- Core Config Form -->
    <form id="settings-config-form" class="glass-card" style="display: flex; flex-direction: column; gap: 16px; border: 1px solid var(--border);">
        <h3 style="font-weight: 700; color: var(--foreground);"><?= __('Global Settings') ?></h3>
        <div class="form-group">
            <label class="form-label"><?= __('Primary UPI ID') ?></label>
            <input class="form-input" type="text" id="settings-upi-id" name="upiId" placeholder="merchant@bank">
        </div>
        <div class="form-group">
            <label class="form-label"><?= __('Backup Global Video Ad URL') ?></label>
            <input class="form-input" type="text" id="settings-global-ad" name="videoUrl" placeholder="YouTube, Instagram, Facebook or any video URL">
        </div>
        <div class="form-group">
            <label class="form-label"><?= __('Required Referrals Count (Withdrawals)') ?></label>
            <input class="form-input" type="number" id="settings-req-referrals" name="requiredReferrals" min="0" placeholder="5">
        </div>
        <button type="submit" class="btn-primary" style="margin-top: auto; color: #000000;"><?= __('Save Configuration') ?></button>
    </form>

    <!-- Payment Tiers Settings -->
    <div class="glass-card" style="grid-column: 1/-1; border: 1px solid var(--border);">
        <h3 style="font-weight: 700; margin-bottom: 16px; color: var(--foreground);"><?= __('Active Deposit Tiers (Gold, Diamond, Platinum)') ?></h3>
        <div style="display: flex; flex-direction: column; gap: 16px;" id="payment-methods-list">
            <!-- Payment Tiers editor dynamically loaded -->
        </div>
    </div>
</div>

<script>
    async function fetchSettings() {
        try {
            // QR & UPI Details
            const qrData = await apiRequest('/api/deposits/payment-details');
            if (qrData.qrCodeUrl) {
                document.getElementById('settings-qr-preview').src = qrData.qrCodeUrl;
                document.getElementById('settings-qr-preview').style.display = 'block';
            }
            document.getElementById('settings-upi-id').value = qrData.upiId || '';

            // Backup global ad
            const adData = await apiRequest('/api/products/daily-ad');
            document.getElementById('settings-global-ad').value = adData.ad?.videoUrl || '';

            // Required referrals count
            const stats = await apiRequest('/api/referrals/stats');
            document.getElementById('settings-req-referrals').value = stats.requiredReferrals;

            // Load Payment Tiers list
            const pmData = await apiRequest('/api/admin/payment-methods');
            const tiersList = document.getElementById('payment-methods-list');
            tiersList.innerHTML = '';

            pmData.methods.forEach(m => {
                const row = document.createElement('form');
                row.className = 'glass-card tier-form-grid';
                row.innerHTML = `
                    <div style="font-weight: 800; color: var(--foreground);">${m.label} <?= __('Tier') ?></div>
                    <input class="form-input" type="text" name="upiId" placeholder="<?= __('UPI ID') ?>" value="${m.upiId || ''}">
                    <input class="form-input" type="file" name="qrImage" accept="image/*">
                    <button type="submit" class="btn-primary" style="padding: 8px; color: #000000;"><?= __('Save Tier') ?></button>
                `;
                row.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(row);
                    try {
                        await apiRequest(`/api/admin/payment-methods/${m.id}`, {
                            method: 'POST',
                            body: formData,
                            isMultipart: true
                        });
                        Toast.show(`${m.label} <?= __('tier details saved successfully!') ?>`);
                        fetchSettings();
                    } catch (err) {
                        Toast.show(err.message, 'error');
                    }
                });
                tiersList.appendChild(row);
            });
        } catch (err) {
            console.error('fetchSettings failed:', err);
            Toast.show(err.message || '<?= __('Failed to load settings') ?>', 'error');
        }
    }

    // Settings Forms submits
    document.getElementById('settings-qr-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        try {
            await apiRequest('/api/admin/qr-code', {
                method: 'POST',
                body: formData,
                isMultipart: true
            });
            Toast.show('<?= __('QR code image uploaded.') ?>');
            fetchSettings();
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    });

    document.getElementById('settings-config-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            // Save Primary UPI ID
            await apiRequest('/api/admin/upi-id', {
                method: 'PUT',
                body: { upiId: document.getElementById('settings-upi-id').value }
            });

            // Save Global daily ad
            await apiRequest('/api/products/admin/daily-ad', {
                method: 'POST',
                body: { videoUrl: document.getElementById('settings-global-ad').value }
            });

            // Save Required referrals count
            await apiRequest('/api/admin/settings', {
                method: 'PUT',
                body: { requiredReferrals: parseInt(document.getElementById('settings-req-referrals').value) }
            });

            Toast.show('<?= __('System settings saved successfully!') ?>');
            fetchSettings();
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    });

    document.addEventListener('DOMContentLoaded', fetchSettings);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
