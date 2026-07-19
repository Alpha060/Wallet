<?php
// public/index.php - Front Controller and Application Router

// Resolve the request path (relative to the web root)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Bootstrap backend utilities (guarantees $pdo and helper functions are globally accessible)
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/helpers.php';

// Route REST API requests
if (strpos($uri, '/api/') === 0) {
    $_GET['route'] = substr($uri, 5); // strip '/api/' prefix
    require dirname(__DIR__) . '/src/api.php';
    exit;
}

// Handle root landing page redirect
if ($uri === '/' || $uri === '') {
    initSession();
    $user = getAuthenticatedUser();
    if ($user) {
        if ($user['isAdmin']) {
            header('Location: /admin');
        } else {
            header('Location: /dashboard');
        }
    } else {
        header('Location: /login');
    }
    exit;
}

// Route pages to private views
switch ($uri) {
    case '/login':
        require dirname(__DIR__) . '/src/views/login.php';
        break;
    case '/register':
        require dirname(__DIR__) . '/src/views/register.php';
        break;
    case '/forgot-password':
        require dirname(__DIR__) . '/src/views/forgot-password.php';
        break;
    case '/user-dashboard':
        header('Location: /dashboard');
        break;
    case '/dashboard':
        require dirname(__DIR__) . '/src/views/user/dashboard.php';
        break;
    case '/marketplace':
        require dirname(__DIR__) . '/src/views/user/marketplace.php';
        break;
    case '/deposit':
        require dirname(__DIR__) . '/src/views/user/deposit.php';
        break;
    case '/withdraw':
        require dirname(__DIR__) . '/src/views/user/withdraw.php';
        break;
    case '/investments':
        require dirname(__DIR__) . '/src/views/user/investments.php';
        break;
    case '/referrals':
        require dirname(__DIR__) . '/src/views/user/referrals.php';
        break;
    case '/history':
        require dirname(__DIR__) . '/src/views/user/history.php';
        break;
    case '/settings':
        require dirname(__DIR__) . '/src/views/user/settings.php';
        break;
    case '/admin-dashboard':
        header('Location: /admin');
        break;
    case '/admin':
        require dirname(__DIR__) . '/src/views/admin/dashboard.php';
        break;
    case '/admin/deposits':
        require dirname(__DIR__) . '/src/views/admin/deposits.php';
        break;
    case '/admin/withdrawals':
        require dirname(__DIR__) . '/src/views/admin/withdrawals.php';
        break;
    case '/admin/claims':
        require dirname(__DIR__) . '/src/views/admin/claims.php';
        break;
    case '/admin/users':
        require dirname(__DIR__) . '/src/views/admin/users.php';
        break;
    case '/admin/products':
        require dirname(__DIR__) . '/src/views/admin/products.php';
        break;
    case '/admin/settings':
        require dirname(__DIR__) . '/src/views/admin/settings.php';
        break;
    default:
        http_response_code(404);
        echo "404 Not Found";
        break;
}
exit;
