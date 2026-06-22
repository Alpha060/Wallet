<?php
// login.php - Login Screen
require_once __DIR__ . '/helpers.php';
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
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AeroPay - Login</title>
    <link rel="stylesheet" href="/app.css">
    <style>
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }
        .login-card {
            width: 100%;
            max-width: 440px;
            padding: 40px;
            z-index: 10;
        }
        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 32px;
            text-align: center;
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
            margin-bottom: 16px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 10px;
            margin-left: 4px;
        }
        .password-container {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            outline: none;
        }
        .password-toggle:hover {
            color: var(--foreground);
        }
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            margin-bottom: 24px;
        }
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: var(--muted);
        }
        .forgot-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        .forgot-link:hover {
            text-decoration: underline;
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
    </style>
</head>
<body>
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>

    <div class="login-wrapper">
        <div class="glass-panel login-card">
            <div class="logo-container">
                <div class="logo-symbol">A</div>
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
                        <button type="button" class="password-toggle" id="password-toggle">
                            <!-- Eye Icon SVG -->
                            <svg style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
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

    <script src="/app.js"></script>
    <script>
        // Password Visibility Toggle
        const passwordInput = document.getElementById('password');
        const toggleBtn = document.getElementById('password-toggle');
        
        toggleBtn.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            toggleBtn.querySelector('svg').style.opacity = isPassword ? '0.7' : '1';
        });

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
