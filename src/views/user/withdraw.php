<?php
// src/views/user/withdraw.php - Withdraw Funds Page
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAuth();

$title = "AeroPay - Withdraw Funds";
$description = "Cash out your balance to bank or UPI";
$activePage = "withdraw";

ob_start();
?>

<div class="glass-panel" style="padding: 36px; max-width: 600px; border: 1px solid var(--border);">
    <div style="margin-bottom: 24px; font-weight: 700; font-size: 1.1rem; color: var(--foreground);">
        Available Balance: <span style="color: var(--primary);" id="withdraw-available-balance">₹0.00</span>
    </div>

    <div id="withdrawal-referral-warning" style="display: none; background: var(--destructive-glow); border: 1px solid rgba(239, 68, 68, 0.15); color: var(--destructive); padding: 16px; border-radius: var(--radius-sm); margin-bottom: 24px; font-size: 0.85rem; font-weight: 600;">
        <!-- Display referral limits warnings -->
    </div>

    <form id="withdrawal-form">
        <div class="form-group">
            <label class="form-label">Withdrawal Amount (Rupees)</label>
            <input class="form-input" type="text" id="withdraw-amount-input" name="amount" placeholder="e.g. 500" required>
        </div>

        <div class="form-group">
            <label class="form-label">Payment Type</label>
            <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                <label style="flex: 1; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px; display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--foreground);">
                    <input type="radio" name="withdrawMethod" value="upi" checked onclick="toggleWithdrawMethod('upi')"> UPI
                </label>
                <label style="flex: 1; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px; display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--foreground);">
                    <input type="radio" name="withdrawMethod" value="bank" onclick="toggleWithdrawMethod('bank')"> Bank Account
                </label>
            </div>
        </div>

        <!-- UPI Field -->
        <div id="withdraw-upi-fields" class="form-group">
            <label class="form-label">UPI ID</label>
            <input class="form-input" type="text" id="withdraw-upi-id" placeholder="yourname@bank">
        </div>

        <!-- Bank Fields -->
        <div id="withdraw-bank-fields" style="display: none;">
            <div class="form-group">
                <label class="form-label">Account Holder Name</label>
                <input class="form-input" type="text" id="withdraw-acc-name" placeholder="John Doe">
            </div>
            <div class="form-group">
                <label class="form-label">Account Number</label>
                <input class="form-input" type="text" id="withdraw-acc-num" placeholder="1234567890">
            </div>
            <div class="form-group">
                <label class="form-label">IFSC Code</label>
                <input class="form-input" type="text" id="withdraw-ifsc" placeholder="ABCD0123456">
            </div>
        </div>

        <div style="margin-top: 32px;">
            <label class="form-label" style="text-align: center; margin-bottom: 12px; display: block;">Swipe to confirm withdrawal</label>
            <div class="swipe-container" id="withdraw-swipe">
                <div class="swipe-bg"></div>
                <div class="swipe-text">Swipe Right to Cashout</div>
                <div class="swipe-handle">➔</div>
            </div>
        </div>
    </form>
</div>

<script>
    // Formatters
    function formatRupees(paise) {
        return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Format currency amount input automatically
    formatCurrencyInput(document.getElementById('withdraw-amount-input'));

    async function fetchWithdrawalDetails() {
        try {
            const [balData, refStats, payment] = await Promise.all([
                apiRequest('/api/wallet/balance'),
                apiRequest('/api/referrals/stats'),
                apiRequest('/api/auth/payment-details')
            ]);
            document.getElementById('withdraw-available-balance').innerText = formatRupees(balData.balance);
            document.getElementById('withdraw-available-balance').setAttribute('data-raw', balData.balance);

            // Fetch limits/referral limits
            const warningDiv = document.getElementById('withdrawal-referral-warning');
            
            if (refStats.confirmedReferrals < refStats.requiredReferrals) {
                const needed = refStats.requiredReferrals - refStats.confirmedReferrals;
                warningDiv.innerText = `⚠️ Requirement: You must invite ${refStats.requiredReferrals} friends with active deposits to unlock withdrawals. You currently have ${refStats.confirmedReferrals} (${needed} remaining).`;
                warningDiv.style.display = 'block';
            } else {
                warningDiv.style.display = 'none';
            }

            // Autoload saved settings
            if (payment.savedUpiId) {
                document.getElementById('withdraw-upi-id').value = payment.savedUpiId;
            }
            if (payment.savedBankDetails) {
                document.getElementById('withdraw-acc-name').value = payment.savedBankDetails.accountName || '';
                document.getElementById('withdraw-acc-num').value = payment.savedBankDetails.accountNumber || '';
                document.getElementById('withdraw-ifsc').value = payment.savedBankDetails.ifscCode || '';
            }
            if (payment.preferredPaymentMethod) {
                const radio = document.querySelector(`input[name="withdrawMethod"][value="${payment.preferredPaymentMethod}"]`);
                if (radio) {
                    radio.checked = true;
                    toggleWithdrawMethod(payment.preferredPaymentMethod);
                }
            }
        } catch (err) {}
    }

    function toggleWithdrawMethod(method) {
        document.getElementById('withdraw-upi-fields').style.display = method === 'upi' ? 'block' : 'none';
        document.getElementById('withdraw-bank-fields').style.display = method === 'bank' ? 'block' : 'none';
    }

    // Initialize swipe to withdraw
    SwipeSlider.init('withdraw-swipe', async () => {
        const amount = parseFloat(document.getElementById('withdraw-amount-input').value);
        if (isNaN(amount) || amount <= 0) {
            Toast.show('Please enter a valid amount', 'error');
            SwipeSlider.reset();
            return;
        }

        const method = document.querySelector('input[name="withdrawMethod"]:checked').value;
        let bankDetails = {};

        if (method === 'upi') {
            const upiVal = document.getElementById('withdraw-upi-id').value.trim();
            if (!upiVal) {
                Toast.show('UPI ID is required', 'error');
                SwipeSlider.reset();
                return;
            }
            bankDetails = { upiId: upiVal };
        } else {
            const name = document.getElementById('withdraw-acc-name').value.trim();
            const num = document.getElementById('withdraw-acc-num').value.trim();
            const ifsc = document.getElementById('withdraw-ifsc').value.trim().toUpperCase();

            if (!name || !num || !ifsc) {
                Toast.show('All bank account details are required', 'error');
                SwipeSlider.reset();
                return;
            }
            bankDetails = { accountName: name, accountNumber: num, ifscCode: ifsc };
        }

        try {
            await apiRequest('/api/withdrawals/create', {
                method: 'POST',
                body: {
                    amount: Math.round(amount * 100),
                    bankDetails
                }
            });

            Toast.show('Withdrawal request submitted successfully!');
            document.getElementById('withdraw-amount-input').value = '';
            SwipeSlider.reset();
            window.location.href = '/dashboard';
        } catch (err) {
            Toast.show(err.message, 'error');
            SwipeSlider.reset();
        }
    });

    document.addEventListener('DOMContentLoaded', fetchWithdrawalDetails);
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
