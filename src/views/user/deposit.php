<?php
// src/views/user/deposit.php - Deposit Funds Page
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAuth();

$title = __("AeroPay – Deposit Funds");
$description = __("Add funds using UPI or direct Bank Transfer");
$activePage = "deposit";

ob_start();
?>

<div class="glass-panel" style="padding: 36px; max-width: 600px; border: 1px solid var(--border);">
    <!-- Step Wizard -->
    <div class="step-wizard">
        <div class="wizard-step active" id="step1-indicator">1</div>
        <div class="wizard-connector" id="connector-1-2"></div>
        <div class="wizard-step" id="step2-indicator">2</div>
        <div class="wizard-connector" id="connector-2-3"></div>
        <div class="wizard-step" id="step3-indicator">3</div>
    </div>

    <form id="deposit-form" enctype="multipart/form-data">
        <!-- Step 1: Amount -->
        <div id="deposit-step-1">
            <div style="text-align: center; margin-bottom: 24px;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 12px auto;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <h3 style="font-weight: 800; margin-bottom: 4px; color: var(--foreground); font-size: 1.2rem;"><?= __('Enter Deposit Amount') ?></h3>
                <p style="font-size: 0.85rem; color: var(--muted); margin: 0;"><?= __('How much would you like to deposit?') ?></p>
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('Amount (₹ Rupees)') ?></label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); font-size: 1.1rem; font-weight: 700; color: var(--primary);">₹</span>
                    <input class="form-input" type="text" id="dep-amount-input" name="amount" placeholder="2,000" required style="padding-left: 36px; font-size: 1.1rem; font-weight: 600;">
                </div>
                <p style="font-size: 0.75rem; color: var(--muted-light); margin-top: 6px;"><?= __('Minimum deposit: ₹1.00') ?></p>
            </div>
            <button type="button" class="btn-primary" onclick="nextDepositStep(2)" style="width: 100%; padding: 14px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <?= __('Continue') ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>

        <!-- Step 2: Pay -->
        <div id="deposit-step-2" style="display: none;">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 12px auto;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M7 15h0M2 9.5h20"/></svg>
                </div>
                <h3 style="font-weight: 800; margin-bottom: 4px; color: var(--foreground); font-size: 1.2rem;"><?= __('Send Payment') ?></h3>
                <p style="font-size: 0.85rem; color: var(--muted); margin: 0;"><?= __('Scan QR code or transfer to the UPI ID below') ?></p>
            </div>
            
            <!-- Amount summary -->
            <div class="deposit-summary-card">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary); flex-shrink: 0;"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 0 1 0 4H8"/><path d="M12 18V6"/></svg>
                <div>
                    <div style="font-size: 0.75rem; color: var(--muted); font-weight: 600;"><?= __('Deposit Amount') ?></div>
                    <div class="amount-display" id="step2-amount-display">₹0.00</div>
                </div>
            </div>

            <!-- Payment Method Tabs -->
            <div class="pm-tabs" id="deposit-pm-tabs"></div>

            <div style="display: flex; flex-direction: column; align-items: center; margin-bottom: 24px;">
                <div style="background: #fff; padding: 12px; border-radius: 16px; border: 1px solid var(--border); display: inline-block; margin-bottom: 16px;">
                    <img id="deposit-qr" src="" alt="QR Code" style="width: 200px; height: 200px; border-radius: 8px; display: none;">
                    <div id="deposit-no-qr" style="width: 200px; height: 120px; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 0.85rem;"><?= __('No QR code available') ?></div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; padding: 10px 20px; background: var(--secondary); border-radius: 100px; border: 1px solid var(--border); cursor: pointer;" onclick="copyUPI()" id="upi-copy-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary);"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                    <span style="font-size: 0.9rem; font-weight: 700; color: var(--foreground);" id="deposit-upi-text"><?= __('Loading...') ?></span>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px;">
                <button type="button" class="btn-secondary" onclick="nextDepositStep(1)" style="flex: 1; padding: 14px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    <?= __('Back') ?>
                </button>
                <button type="button" class="btn-primary" onclick="nextDepositStep(3)" style="flex: 2; padding: 14px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <?= __('I Have Paid') ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
            </div>
        </div>

        <!-- Step 3: Proof -->
        <div id="deposit-step-3" style="display: none;">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 12px auto;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                </div>
                <h3 style="font-weight: 800; margin-bottom: 4px; color: var(--foreground); font-size: 1.2rem;"><?= __('Upload Payment Proof') ?></h3>
                <p style="font-size: 0.85rem; color: var(--muted); margin: 0;"><?= __('Upload your payment receipt screenshot') ?></p>
            </div>

            <!-- Amount summary -->
            <div class="deposit-summary-card">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--primary); flex-shrink: 0;"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 0 1 0 4H8"/><path d="M12 18V6"/></svg>
                <div>
                    <div style="font-size: 0.75rem; color: var(--muted); font-weight: 600;"><?= __('Deposit Amount') ?></div>
                    <div class="amount-display" id="step3-amount-display">₹0.00</div>
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label"><?= __('Payment Screenshot') ?></label>
                <div class="upload-dropzone" id="upload-dropzone">
                    <input type="file" name="paymentProof" required accept="image/*" id="proof-file-input">
                    <div class="upload-dropzone-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                    </div>
                    <div class="upload-dropzone-text" id="dropzone-text"><?= __('Click to upload screenshot') ?></div>
                    <div class="upload-dropzone-hint"><?= __('JPEG, PNG, WEBP — Max 5MB') ?></div>
                </div>
                <div id="upload-preview-container" style="display: none; margin-top: 12px; position: relative;">
                    <img id="upload-preview" src="" alt="Preview" style="width: 100%; max-height: 200px; object-fit: contain; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                    <button type="button" id="remove-preview" style="position: absolute; top: 8px; right: 8px; background: var(--destructive); color: #fff; border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">✕</button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><?= __('Transaction / Reference ID') ?> <span style="color: var(--muted-light); font-weight: 500;">(<?= __('Optional') ?>)</span></label>
                <input class="form-input" type="text" name="transactionId" placeholder="12-digit UPI Transaction Ref ID">
            </div>

            <div style="display: flex; gap: 12px; margin-top: 8px;">
                <button type="button" class="btn-secondary" onclick="nextDepositStep(2)" style="flex: 1; padding: 14px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    <?= __('Back') ?>
                </button>
                <button type="submit" class="btn-primary" style="flex: 2; padding: 14px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px;" id="deposit-submit-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                    <?= __('Submit Request') ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    // Formatters
    function formatRupees(paise) {
        return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    let depositPaymentMethods = [];
    async function fetchDepositDetails() {
        try {
            const [data, methodsData] = await Promise.all([
                apiRequest('/api/deposits/payment-details'),
                apiRequest('/api/admin/payment-methods/public')
            ]);
            const tabsContainer = document.getElementById('deposit-pm-tabs');
            tabsContainer.innerHTML = '';

            if (methodsData.methods && methodsData.methods.length > 0) {
                depositPaymentMethods = methodsData.methods;
                methodsData.methods.forEach((pm, idx) => {
                    const tab = document.createElement('button');
                    tab.type = 'button';
                    tab.className = idx === 0 ? 'pm-tab active' : 'pm-tab';
                    tab.innerText = pm.label || `Option ${idx + 1}`;
                    tab.addEventListener('click', () => {
                        tabsContainer.querySelectorAll('.pm-tab').forEach(b => b.className = 'pm-tab');
                        tab.className = 'pm-tab active';
                        showDepositPaymentMethod(pm);
                    });
                    tabsContainer.appendChild(tab);
                });
                showDepositPaymentMethod(methodsData.methods[0]);
            } else {
                showDepositPaymentMethod({ qrCodeUrl: data.qrCodeUrl, upiId: data.upiId });
            }
        } catch (err) {
            document.getElementById('deposit-upi-text').innerText = '<?= __('Error loading details') ?>';
        }
    }

    function showDepositPaymentMethod(pm) {
        const qrImg = document.getElementById('deposit-qr');
        const noQr = document.getElementById('deposit-no-qr');
        if (pm.qrCodeUrl) {
            qrImg.src = pm.qrCodeUrl;
            qrImg.style.display = 'block';
            noQr.style.display = 'none';
        } else {
            qrImg.style.display = 'none';
            noQr.style.display = 'flex';
        }
        document.getElementById('deposit-upi-text').innerText = pm.upiId || '<?= __('Not configured') ?>';
    }

    function copyUPI() {
        const upiText = document.getElementById('deposit-upi-text').innerText;
        if (upiText && upiText !== 'Loading...' && upiText !== 'Not configured') {
            navigator.clipboard.writeText(upiText).then(() => {
                Toast.show('<?= __('UPI ID copied to clipboard!') ?>');
            }).catch(() => {
                Toast.show('<?= __('Failed to copy UPI ID') ?>', 'error');
            });
        }
    }

    function updateAmountDisplays() {
        const rupees = parseFloat(document.getElementById('dep-amount-input').value) || 0;
        const formatted = '₹' + rupees.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('step2-amount-display').innerText = formatted;
        document.getElementById('step3-amount-display').innerText = formatted;
    }

    function nextDepositStep(stepNum) {
        // Validate amount before advancing from step 1
        if (stepNum === 2) {
            const amountInput = document.getElementById('dep-amount-input');
            const amount = parseFloat(amountInput.value);
            if (isNaN(amount) || amount <= 0) {
                Toast.show('<?= __('Please enter a valid deposit amount') ?>', 'error');
                return;
            }
            if (amount < 1) {
                Toast.show('<?= __('Minimum deposit amount is ₹1') ?>', 'error');
                return;
            }
            updateAmountDisplays();
        }

        document.getElementById('deposit-step-1').style.display = stepNum === 1 ? 'block' : 'none';
        document.getElementById('deposit-step-2').style.display = stepNum === 2 ? 'block' : 'none';
        document.getElementById('deposit-step-3').style.display = stepNum === 3 ? 'block' : 'none';

        // Update step indicators
        document.getElementById('step1-indicator').className = `wizard-step ${stepNum === 1 ? 'active' : 'complete'}`;
        document.getElementById('step2-indicator').className = `wizard-step ${stepNum === 2 ? 'active' : (stepNum > 2 ? 'complete' : '')}`;
        document.getElementById('step3-indicator').className = `wizard-step ${stepNum === 3 ? 'active' : ''}`;

        // Update connectors
        document.getElementById('connector-1-2').className = `wizard-connector ${stepNum >= 2 ? 'active' : ''}`;
        document.getElementById('connector-2-3').className = `wizard-connector ${stepNum >= 3 ? 'active' : ''}`;
    }

    // Format currency amount input
    formatCurrencyInput(document.getElementById('dep-amount-input'));

    // File upload dropzone
    const proofInput = document.getElementById('proof-file-input');
    const dropzone = document.getElementById('upload-dropzone');
    const previewContainer = document.getElementById('upload-preview-container');
    const previewImg = document.getElementById('upload-preview');
    const dropzoneText = document.getElementById('dropzone-text');
    const removeBtn = document.getElementById('remove-preview');

    proofInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            dropzone.classList.add('has-file');
            dropzoneText.innerText = file.name;
            // Show preview
            const reader = new FileReader();
            reader.onload = (ev) => {
                previewImg.src = ev.target.result;
                previewContainer.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    removeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        proofInput.value = '';
        dropzone.classList.remove('has-file');
        dropzoneText.innerText = '<?= __('Click to upload screenshot') ?>';
        previewContainer.style.display = 'none';
        previewImg.src = '';
    });

    // Form submission
    document.getElementById('deposit-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('deposit-submit-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner" style="width: 18px; height: 18px; border: 2px solid rgba(0,0,0,0.2); border-top-color: #000; border-radius: 50%; display: inline-block; animation: spin 0.6s linear infinite;"></span> <?= __('Submitting...') ?>';

        const formElement = document.getElementById('deposit-form');
        const rupees = parseFloat(document.getElementById('dep-amount-input').value);
        const paise = Math.round(rupees * 100);

        const formData = new FormData(formElement);
        formData.set('amount', paise);

        try {
            await apiRequest('/api/deposits/create', {
                method: 'POST',
                body: formData,
                isMultipart: true
            });

            Toast.show('<?= __('Deposit request submitted! Awaiting admin approval.') ?>');
            formElement.reset();
            nextDepositStep(1);
            window.location.href = '/dashboard';
        } catch (err) {
            Toast.show(err.message || '<?= __('Failed to submit deposit request') ?>', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg> <?= __('Submit Request') ?>';
        }
    });

    document.addEventListener('DOMContentLoaded', fetchDepositDetails);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
