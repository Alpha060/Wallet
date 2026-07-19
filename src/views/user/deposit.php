<?php
// src/views/user/deposit.php - Deposit Funds Page
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAuth();

$title = "AeroPay - Deposit Funds";
$description = "Add funds using UPI or direct Bank Transfer";
$activePage = "deposit";

ob_start();
?>

<div class="glass-panel" style="padding: 36px; max-width: 600px; border: 1px solid var(--border);">
    <div class="step-wizard">
        <div class="wizard-step active" id="step1-indicator">1</div>
        <div class="wizard-step" id="step2-indicator">2</div>
        <div class="wizard-step" id="step3-indicator">3</div>
    </div>

    <form id="deposit-form" enctype="multipart/form-data">
        <!-- Step 1: Amount -->
        <div id="deposit-step-1">
            <h3 style="font-weight: 700; margin-bottom: 16px; color: var(--foreground);">Step 1: Enter Amount</h3>
            <div class="form-group">
                <label class="form-label">Amount (in Rupees)</label>
                <input class="form-input" type="text" id="dep-amount-input" name="amount" placeholder="e.g. 2000" required>
            </div>
            <button type="button" class="btn-primary" onclick="nextDepositStep(2)" style="width: 100%;">Continue</button>
        </div>

        <!-- Step 2: Pay -->
        <div id="deposit-step-2" style="display: none;">
            <h3 style="font-weight: 700; margin-bottom: 16px; color: var(--foreground);">Step 2: Scan and Send</h3>
            <p style="font-size: 0.9rem; color: var(--muted); margin-bottom: 20px;">Scan the QR code below or transfer to the UPI ID, then send the payment.</p>
            
            <!-- Payment Method Tabs -->
            <div id="deposit-pm-tabs" style="display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;"></div>

            <div style="display: flex; flex-direction: column; align-items: center; margin-bottom: 24px;">
                <img id="deposit-qr" src="" alt="QR Code" style="width: 220px; height: 220px; border-radius: 12px; border: 1px solid var(--border); display: none; margin-bottom: 16px;">
                <div style="font-size: 1rem; font-weight: 700; color: var(--foreground);" id="deposit-upi-text">UPI ID: Loading...</div>
            </div>
            
            <div style="display: flex; gap: 12px;">
                <button type="button" class="btn-secondary" onclick="nextDepositStep(1)" style="flex: 1;">Back</button>
                <button type="button" class="btn-primary" onclick="nextDepositStep(3)" style="flex: 1;">I Have Paid</button>
            </div>
        </div>

        <!-- Step 3: Proof -->
        <div id="deposit-step-3" style="display: none;">
            <h3 style="font-weight: 700; margin-bottom: 16px; color: var(--foreground);">Step 3: Upload Proof</h3>
            <p style="font-size: 0.9rem; color: var(--muted); margin-bottom: 20px;">Upload a screenshot of the payment receipt and enter the Transaction ID / Ref ID.</p>
            
            <div class="form-group">
                <label class="form-label">Screenshot Receipt</label>
                <input class="form-input" type="file" name="paymentProof" required accept="image/*">
            </div>

            <div class="form-group">
                <label class="form-label">Transaction ID (Optional)</label>
                <input class="form-input" type="text" name="transactionId" placeholder="12-digit UPI Transaction Ref ID">
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="button" class="btn-secondary" onclick="nextDepositStep(2)" style="flex: 1;">Back</button>
                <button type="submit" class="btn-primary" style="flex: 1;" id="deposit-submit-btn">Submit Request</button>
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
                // Build tabs for each payment method
                methodsData.methods.forEach((pm, idx) => {
                    const tab = document.createElement('button');
                    tab.type = 'button';
                    tab.className = idx === 0 ? 'btn-primary' : 'btn-secondary';
                    tab.style.cssText = 'padding: 8px 16px; font-size: 0.8rem;';
                    tab.innerText = pm.label || `Option ${idx + 1}`;
                    tab.addEventListener('click', () => {
                        tabsContainer.querySelectorAll('button').forEach(b => b.className = 'btn-secondary');
                        tab.className = 'btn-primary';
                        showDepositPaymentMethod(pm);
                    });
                    tabsContainer.appendChild(tab);
                });
                // Show first method by default
                showDepositPaymentMethod(methodsData.methods[0]);
            } else {
                // Fallback to primary admin settings
                showDepositPaymentMethod({ qrCodeUrl: data.qrCodeUrl, upiId: data.upiId });
            }
        } catch (err) {
            document.getElementById('deposit-upi-text').innerText = 'UPI ID: Error loading details';
        }
    }

    function showDepositPaymentMethod(pm) {
        if (pm.qrCodeUrl) {
            document.getElementById('deposit-qr').src = pm.qrCodeUrl;
            document.getElementById('deposit-qr').style.display = 'block';
        } else {
            document.getElementById('deposit-qr').style.display = 'none';
        }
        document.getElementById('deposit-upi-text').innerText = `UPI ID: ${pm.upiId || 'Not Setup'}`;
    }

    function nextDepositStep(stepNum) {
        // Validate amount before advancing from step 1
        if (stepNum === 2) {
            const amountInput = document.getElementById('dep-amount-input');
            const amount = parseFloat(amountInput.value);
            if (isNaN(amount) || amount <= 0) {
                Toast.show('Please enter a valid deposit amount', 'error');
                return;
            }
            if (amount < 1) {
                Toast.show('Minimum deposit amount is ₹1', 'error');
                return;
            }
        }

        document.getElementById('deposit-step-1').style.display = stepNum === 1 ? 'block' : 'none';
        document.getElementById('deposit-step-2').style.display = stepNum === 2 ? 'block' : 'none';
        document.getElementById('deposit-step-3').style.display = stepNum === 3 ? 'block' : 'none';

        // Update step indicators
        document.getElementById('step1-indicator').className = `wizard-step ${stepNum === 1 ? 'active' : 'complete'}`;
        document.getElementById('step2-indicator').className = `wizard-step ${stepNum === 2 ? 'active' : (stepNum > 2 ? 'complete' : '')}`;
        document.getElementById('step3-indicator').className = `wizard-step ${stepNum === 3 ? 'active' : ''}`;
    }

    // Format currency amount input automatically
    formatCurrencyInput(document.getElementById('dep-amount-input'));

    document.getElementById('deposit-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('deposit-submit-btn');
        btn.disabled = true;
        btn.innerText = 'Submitting...';

        const formElement = document.getElementById('deposit-form');
        const rupees = parseFloat(document.getElementById('dep-amount-input').value);
        const paise = Math.round(rupees * 100);

        const formData = new FormData(formElement);
        formData.set('amount', paise); // Overwrite in paise

        try {
            await apiRequest('/api/deposits/create', {
                method: 'POST',
                body: formData,
                isMultipart: true
            });

            Toast.show('Deposit request submitted! Awaiting admin approval.');
            formElement.reset();
            nextDepositStep(1);
            window.location.href = '/dashboard';
        } catch (err) {
            Toast.show(err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerText = 'Submit Request';
        }
    });

    document.addEventListener('DOMContentLoaded', fetchDepositDetails);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
