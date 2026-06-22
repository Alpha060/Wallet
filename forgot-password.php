<?php
// forgot-password.php - Forgot Password Request View
require_once __DIR__ . '/helpers.php';
initSession();
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AeroPay - Forgot Password</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .forgot-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }
        .forgot-card {
            width: 100%;
            max-width: 440px;
            padding: 40px;
            text-align: center;
            z-index: 10;
        }
        .logo-symbol {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, rgba(14,165,233,0.8) 0%, rgba(99,102,241,0.8) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 2rem;
            font-weight: 800;
            box-shadow: 0 12px 32px rgba(14, 165, 233, 0.4);
            margin: 0 auto 20px auto;
        }
        .message-box {
            background: rgba(14, 165, 233, 0.08);
            border: 1px solid rgba(14, 165, 233, 0.15);
            border-radius: var(--radius-sm);
            padding: 24px;
            margin: 24px 0;
            line-height: 1.6;
            color: var(--foreground);
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>

    <div class="forgot-wrapper">
        <div class="glass-panel forgot-card">
            <div class="logo-symbol">A</div>
            <h2>Reset Password</h2>
            
            <div class="message-box">
                Verify your saved KYC details to reset your password. If your PAN or date of birth is not saved, contact administration for manual verification.
            </div>

            <form id="reset-form" style="text-align: left;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input class="form-input" type="email" id="email" name="email" required placeholder="name@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label" for="panNumber">PAN Number</label>
                    <input class="form-input" type="text" id="panNumber" name="panNumber" required placeholder="ABCDE1234F" style="text-transform: uppercase;">
                </div>
                <div class="form-group">
                    <label class="form-label" for="dateOfBirth">Date of Birth</label>
                    <input class="form-input" type="date" id="dateOfBirth" name="dateOfBirth" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="newPassword">New Password</label>
                    <input class="form-input" type="password" id="newPassword" name="newPassword" required minlength="8" placeholder="Minimum 8 characters">
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirmPassword">Confirm New Password</label>
                    <input class="form-input" type="password" id="confirmPassword" required minlength="8" placeholder="Repeat password">
                </div>
                <button type="submit" class="btn-primary" id="reset-submit" style="width: 100%;">Reset Password</button>
            </form>

            <a href="/login" class="btn-secondary" style="width: 100%; text-decoration: none; margin-top: 16px;">Back to Login</a>
        </div>
    </div>

    <script src="/app.js"></script>
    <script>
        const form = document.getElementById('reset-form');
        const panInput = document.getElementById('panNumber');
        panInput.addEventListener('input', () => {
            panInput.value = panInput.value.toUpperCase();
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            if (newPassword !== confirmPassword) {
                Toast.show('Passwords do not match', 'error');
                return;
            }

            const submitBtn = document.getElementById('reset-submit');
            submitBtn.disabled = true;
            submitBtn.innerText = 'Resetting...';

            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());

            try {
                await apiRequest('/api/auth/reset-password', {
                    method: 'POST',
                    body: payload
                });
                Toast.show('Password reset successfully. Redirecting...');
                setTimeout(() => {
                    window.location.href = '/login';
                }, 1200);
            } catch (err) {
                Toast.show(err.message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerText = 'Reset Password';
            }
        });
    </script>
</body>
</html>
