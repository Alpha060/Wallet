<?php
// index.php - Root landing page and redirect router
require_once __DIR__ . '/helpers.php';

initSession();
$user = getAuthenticatedUser();

if ($user) {
    if ($user['isAdmin']) {
        header('Location: /admin-dashboard');
    } else {
        header('Location: /user-dashboard');
    }
} else {
    header('Location: /login');
}
exit;
