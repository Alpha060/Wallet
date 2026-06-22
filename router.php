<?php
// router.php - PHP built-in web server router script

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// If the file exists in the directory, serve it directly (e.g. app.css, app.js, images in uploads/)
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // let the built-in server handle the file request
}

// Route API requests to api.php
if (strpos($uri, '/api/') === 0) {
    // Set query parameter 'route' for api.php
    $_GET['route'] = substr($uri, 5); // strip '/api/' prefix
    require __DIR__ . '/api.php';
    exit;
}

// Redirect root to dashboard (which handles auth validation internally)
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    exit;
}

// Route pages
switch ($uri) {
    case '/login':
        require __DIR__ . '/login.php';
        break;
    case '/register':
        require __DIR__ . '/register.php';
        break;
    case '/forgot-password':
        require __DIR__ . '/forgot-password.php';
        break;
    case '/user-dashboard':
        require __DIR__ . '/dashboard.php';
        break;
    case '/admin-dashboard':
        require __DIR__ . '/admin.php';
        break;
    default:
        http_response_code(404);
        echo "404 Not Found";
        break;
}
exit;
