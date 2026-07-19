<?php
// register.php - User Registration View
require_once dirname(__DIR__) . '/helpers.php';
initSession();

// Redirect if already authenticated
$user = getAuthenticatedUser();
if ($user) {
    if ($user['isAdmin']) {
        header('Location: /admin-dashboard');
    } else {
        header('Location: /user-dashboard');
    }
    exit;
}

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
    <link rel="apple-touch-icon" href="/images/aeropay-logo.png">
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
        .register-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 460px;
        }
        .register-card {
            background-color: var(--card) !important;
            border: 1px solid var(--card-border) !important;
            border-radius: var(--radius) !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.02) !important;
            padding: 36px 30px;
            width: 100%;
            box-sizing: border-box;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 24px;
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
        .logo-container h2 {
            font-size: 1.6rem;
            font-weight: 800;
            margin: 0;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 8px;
            margin-left: 4px;
        }
        .login-link {
            text-align: center;
            margin-top: 24px;
            font-size: 0.85rem;
            color: var(--muted);
        }
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .ref-status {
            font-size: 0.75rem;
            margin-top: 4px;
            font-weight: 600;
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

    <div class="register-wrapper">
        <div class="glass-panel register-card">
            <div class="logo-container">
                <img src="/images/aeropay-logo.png" alt="AeroPay Logo" style="width: 54px; height: 54px; object-fit: contain; margin: 0 auto 10px auto; display: block;">
                <h2>Aero<span class="text-gradient">Pay</span></h2>
                <p style="color: var(--muted); font-size: 0.85rem; margin-top: 2px;">Your money, at the speed of air</p>
            </div>

            <h3 style="text-align: center; margin-bottom: 20px; font-weight: 700;">Create Account</h3>

            <form id="register-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="form-group">
                    <label class="form-label" for="name">Full Name (Optional)</label>
                    <input class="form-input" type="text" id="name" name="name" placeholder="John Doe">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input class="form-input" type="email" id="email" name="email" required placeholder="name@example.com">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="password-container">
                        <input class="form-input" type="password" id="password" name="password" required placeholder="••••••••">
                        <button type="button" class="password-toggle">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirmPassword">Confirm Password</label>
                    <div class="password-container">
                        <input class="form-input" type="password" id="confirmPassword" name="confirmPassword" required placeholder="••••••••">
                        <button type="button" class="password-toggle">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="referralCode">Referral Code (Optional)</label>
                    <input class="form-input" type="text" id="referralCode" name="referralCode" placeholder="6-digit code" maxlength="6" style="text-transform: uppercase;">
                    <div id="referral-status" class="ref-status"></div>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 8px;">Register</button>
            </form>

            <div class="login-link">
                Already have an account? <a href="/login">Login here</a>
            </div>
        </div>
    </div>

    <script src="/app.js?v=2"></script>
    <script>
        // Referral Code Validation
        const referralInput = document.getElementById('referralCode');
        const referralStatus = document.getElementById('referral-status');

        let debounceTimeout;
        referralInput.addEventListener('input', (e) => {
            const code = e.target.value.toUpperCase();
            e.target.value = code;
            
            clearTimeout(debounceTimeout);
            referralStatus.innerText = '';
            referralStatus.className = 'ref-status';

            if (code.length === 6) {
                debounceTimeout = setTimeout(async () => {
                    try {
                        const data = await apiRequest('/api/referrals/verify', {
                            method: 'POST',
                            body: { referralCode: code }
                        });
                        
                        if (data.valid) {
                            referralStatus.innerText = `Referred by: ${data.referrerName}`;
                            referralStatus.style.color = 'var(--success)';
                        } else {
                            referralStatus.innerText = 'Invalid referral code';
                            referralStatus.style.color = 'var(--destructive)';
                        }
                    } catch (err) {
                        referralStatus.innerText = 'Error verifying referral code';
                        referralStatus.style.color = 'var(--destructive)';
                    }
                }, 400);
            }
        });

        // Form Submit
        const form = document.getElementById('register-form');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirmPassword').value;

            if (password !== confirm) {
                Toast.show('Passwords do not match', 'error');
                return;
            }

            if (password.length < 8) {
                Toast.show('Password must be at least 8 characters long', 'error');
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerText = 'Registering...';

            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());

            try {
                const data = await apiRequest('/api/auth/register', {
                    method: 'POST',
                    body: payload
                });

                Toast.show('Registration successful! Redirecting...');
                setTimeout(() => {
                    window.location.href = '/user-dashboard';
                }, 1000);
            } catch (err) {
                Toast.show(err.message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerText = 'Register';
            }
        });
    </script>
</body>
</html>
