<?php
// forgot-password.php - Forgot Password Request View
require_once dirname(__DIR__) . '/helpers.php';
initSession();
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<script>document.documentElement.classList.toggle('dark', localStorage.getItem('theme') === 'dark');</script>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/app.css?v=2">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0a0a0c">
    <link rel="apple-touch-icon" href="/icons/aeropay-logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--background);
            color: var(--foreground);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            margin: 0;
            padding: 16px;
            box-sizing: border-box;
        }
        .forgot-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 440px;
        }
        .forgot-card {
            background-color: var(--card) !important;
            border: 1px solid var(--card-border) !important;
            border-radius: var(--radius) !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.02) !important;
            padding: 36px 30px;
            width: 100%;
            box-sizing: border-box;
            text-align: center;
        }
        .logo-container img {
            width: 64px;
            height: 64px;
            object-fit: contain;
            border-radius: 14px;
            background-color: #000;
            padding: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin: 0 auto 12px auto;
            display: block;
        }
        .message-box {
            background: rgba(217, 119, 6, 0.08);
            border: 1px solid rgba(217, 119, 6, 0.15);
            border-radius: var(--radius-sm);
            padding: 16px;
            margin: 20px 0;
            line-height: 1.5;
            color: var(--foreground);
            font-size: 0.85rem;
            text-align: left;
        }
        .form-group {
            margin-bottom: 16px;
            text-align: left;
        }
        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 8px;
            margin-left: 4px;
        }
        /* Override browser autofill input background colors */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px var(--card) inset !important;
            -webkit-text-fill-color: var(--foreground) !important;
            transition: background-color 5000s ease-in-out 0s;
        }
    </style>
</head>
<body>
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>

    <div class="forgot-wrapper">
        <div class="glass-panel forgot-card">
            <div class="logo-container" style="text-align: center; margin-bottom: 24px;">
                <img src="/icons/aeropay-logo.png" alt="AeroPay Logo" style="width: 54px; height: 54px; object-fit: contain; margin: 0 auto 10px auto; display: block;">
                <h2 style="font-size: 1.8rem; font-weight: 800; margin: 0;">Reset Password</h2>
            </div>
            
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
                    <div class="password-container">
                        <input class="form-input" type="password" id="newPassword" name="newPassword" required minlength="8" placeholder="Minimum 8 characters">
                        <button type="button" class="password-toggle">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirmPassword">Confirm New Password</label>
                    <div class="password-container">
                        <input class="form-input" type="password" id="confirmPassword" required minlength="8" placeholder="Repeat password">
                        <button type="button" class="password-toggle">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-primary" id="reset-submit" style="width: 100%;">Reset Password</button>
            </form>

            <a href="/login" class="btn-secondary" style="width: 100%; text-decoration: none; margin-top: 16px;">Back to Login</a>
        </div>
    </div>

    <script src="/app.js?v=2"></script>
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
