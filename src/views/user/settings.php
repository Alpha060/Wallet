<?php
// src/views/user/settings.php - User Settings Page
require_once dirname(dirname(dirname(__DIR__))) . '/src/helpers.php';
$user = requireAuth();

$title = "AeroPay - Settings";
$description = "Configure Profile details, KYC data, and password";
$activePage = "settings";

ob_start();
?>

<div style="display: flex; gap: 16px; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">
    <button class="nav-link active" onclick="switchSettingsSubTab('profile')" id="subtab-profile-btn" style="width: auto; padding: 8px 16px;">👤 Profile</button>
    <button class="nav-link" onclick="switchSettingsSubTab('payment')" id="subtab-payment-btn" style="width: auto; padding: 8px 16px;">💳 Payment Details</button>
</div>

<!-- Profile Subtab -->
<div id="settings-profile-panel">
    <form id="profile-form" class="glass-panel" style="padding: 36px; max-width: 600px; border: 1px solid var(--border);">
        <h3 style="font-weight: 700; margin-bottom: 20px; color: var(--foreground);">Profile Information</h3>
        
        <div class="form-group">
            <label class="form-label">Full Name</label>
            <input class="form-input" type="text" id="prof-name" name="name">
        </div>

        <div class="form-group">
            <label class="form-label">Mobile Number</label>
            <input class="form-input" type="text" id="prof-mobile" name="mobileNumber">
        </div>

        <div class="form-group">
            <label class="form-label">Date of Birth</label>
            <input class="form-input" type="date" id="prof-dob" name="dateOfBirth">
        </div>

        <div class="form-group">
            <label class="form-label">Aadhar Card Number (12 digits)</label>
            <input class="form-input" type="text" id="prof-aadhar" name="aadharNumber" maxlength="12">
        </div>

        <div class="form-group">
            <label class="form-label">PAN Card Number (10 characters)</label>
            <input class="form-input" type="text" id="prof-pan" name="panNumber" maxlength="10" style="text-transform: uppercase;">
        </div>

        <button type="submit" class="btn-primary">Save Profile</button>
    </form>
</div>

<!-- Payment Details Subtab -->
<div id="settings-payment-panel" style="display: none;">
    <form id="payment-details-form" class="glass-panel" style="padding: 36px; max-width: 600px; border: 1px solid var(--border);">
        <h3 style="font-weight: 700; margin-bottom: 20px; color: var(--foreground);">Withdrawal Details</h3>
        
        <div class="form-group">
            <label class="form-label">Preferred Cashout Method</label>
            <select class="form-input" id="set-pref-method" name="preferredPaymentMethod">
                <option value="upi">UPI ID</option>
                <option value="bank">Bank Transfer</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Saved UPI ID</label>
            <input class="form-input" type="text" id="set-upi" name="upiId">
        </div>

        <h4 style="font-weight: 700; margin: 24px 0 12px 0; color: var(--foreground);">Saved Bank Details</h4>

        <div class="form-group">
            <label class="form-label">Account Holder Name</label>
            <input class="form-input" type="text" id="set-acc-name">
        </div>

        <div class="form-group">
            <label class="form-label">Account Number</label>
            <input class="form-input" type="text" id="set-acc-num">
        </div>

        <div class="form-group">
            <label class="form-label">IFSC Code</label>
            <input class="form-input" type="text" id="set-ifsc" style="text-transform: uppercase;">
        </div>

        <button type="submit" class="btn-primary">Save Methods</button>
    </form>
</div>



<script>
    function switchSettingsSubTab(subId) {
        document.getElementById('settings-profile-panel').style.display = subId === 'profile' ? 'block' : 'none';
        document.getElementById('settings-payment-panel').style.display = subId === 'payment' ? 'block' : 'none';

        document.getElementById('subtab-profile-btn').className = `nav-link ${subId === 'profile' ? 'active' : ''}`;
        document.getElementById('subtab-payment-btn').className = `nav-link ${subId === 'payment' ? 'active' : ''}`;
    }

    async function fetchSettings() {
        try {
            const [profile, payment] = await Promise.all([
                apiRequest('/api/auth/me'),
                apiRequest('/api/auth/payment-details')
            ]);
            document.getElementById('prof-name').value = profile.name || '';
            document.getElementById('prof-mobile').value = profile.mobileNumber || '';
            document.getElementById('prof-dob').value = profile.dateOfBirth ? profile.dateOfBirth.split('T')[0] : '';
            document.getElementById('prof-aadhar').value = profile.aadharNumber || '';
            document.getElementById('prof-pan').value = profile.panNumber || '';

            // Payments Details
            document.getElementById('set-pref-method').value = payment.preferredPaymentMethod || 'upi';
            document.getElementById('set-upi').value = payment.savedUpiId || '';
            
            if (payment.savedBankDetails) {
                document.getElementById('set-acc-name').value = payment.savedBankDetails.accountName || '';
                document.getElementById('set-acc-num').value = payment.savedBankDetails.accountNumber || '';
                document.getElementById('set-ifsc').value = payment.savedBankDetails.ifscCode || '';
            }
        } catch (err) {}
    }

    document.getElementById('profile-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await apiRequest('/api/auth/profile', {
                method: 'PUT',
                body: {
                    name: document.getElementById('prof-name').value,
                    mobileNumber: document.getElementById('prof-mobile').value,
                    dateOfBirth: document.getElementById('prof-dob').value || null,
                    aadharNumber: document.getElementById('prof-aadhar').value,
                    panNumber: document.getElementById('prof-pan').value
                }
            });
            Toast.show('Profile updated successfully!');
            fetchSettings();
        } catch (err) {
            Toast.show(err.message, 'error');
        }
    });

    document.getElementById('payment-details-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await apiRequest('/api/auth/payment-details', {
                method: 'PUT',
                body: {
                    preferredPaymentMethod: document.getElementById('set-pref-method').value,
                    upiId: document.getElementById('set-upi').value,
                    bankDetails: {
                        accountName: document.getElementById('set-acc-name').value,
                        accountNumber: document.getElementById('set-acc-num').value,
                        ifscCode: document.getElementById('set-ifsc').value
                    }
                }
            });
            Toast.show('Payment configurations saved!');
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
