<?php
// login.php - Login Screen
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
    <title>Login - AeroPay</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
        .login-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 420px;
        }
        .login-card {
            background-color: var(--card) !important;
            border: 1px solid var(--card-border) !important;
            border-radius: var(--radius) !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.02) !important;
            padding: 40px 30px;
            width: 100%;
            box-sizing: border-box;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 28px;
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
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 8px;
            margin-left: 4px;
        }
        .remember-forgot {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            width: 100% !important;
            font-size: 0.85rem !important;
            margin-bottom: 24px !important;
            gap: 12px;
        }
        .remember-me {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            cursor: pointer !important;
            color: var(--muted) !important;
            font-weight: 500;
        }
        .forgot-link {
            color: var(--primary) !important;
            text-decoration: none !important;
            font-weight: 600 !important;
            white-space: nowrap;
        }
        .forgot-link:hover {
            text-decoration: underline !important;
        }
        .register-link {
            text-align: center;
            margin-top: 24px;
            font-size: 0.85rem;
            color: var(--muted);
        }
        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
        }
        .register-link a:hover {
            text-decoration: underline;
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

    <div class="login-wrapper">
        <div class="glass-panel login-card">
            <div class="logo-container">
                <img src="/images/aeropay-logo.png" alt="AeroPay Logo" style="width: 54px; height: 54px; object-fit: contain; margin: 0 auto 10px auto; display: block;">
                <h2>Aero<span class="text-gradient">Pay</span></h2>
                <p style="color: var(--muted); font-size: 0.85rem; margin-top: 4px;">Your money, at the speed of air</p>
            </div>

            <h3 style="text-align: center; margin-bottom: 24px; font-weight: 700;">Welcome Back</h3>

            <form id="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input class="form-input" type="email" id="email" name="email" required placeholder="name@example.com">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="password-container">
                        <input class="form-input" type="password" id="password" name="password" required placeholder="••••••••">
                        <button type="button" class="password-toggle">
                            <!-- SVG Eye icon (initially open) -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="remember-forgot">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" checked style="accent-color: var(--primary);">
                        Keep me logged in
                    </label>
                    <a href="/forgot-password" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%;">Login</button>
            </form>

            <div class="register-link">
                Don't have an account? <a href="/register">Register here</a>
            </div>
        </div>
    </div>

    <script src="/app.js?v=2"></script>
    <script>


        // AJAX Form Submission
        const form = document.getElementById('login-form');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerText = 'Logging in...';

            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());

            try {
                const data = await apiRequest('/api/auth/login', {
                    method: 'POST',
                    body: payload
                });

                Toast.show('Login successful! Redirecting...');
                setTimeout(() => {
                    if (data.user.isAdmin) {
                        window.location.href = '/admin-dashboard';
                    } else {
                        window.location.href = '/user-dashboard';
                    }
                }, 1000);
            } catch (err) {
                Toast.show(err.message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerText = 'Login';
            }
        });
    </script>
</body>
</html>
