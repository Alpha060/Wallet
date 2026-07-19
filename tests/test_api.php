<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access Denied: This script can only be run from the command line.');
}
// Quick targeted API test for the 3 broken features
$base = 'http://localhost:8000/api';
$jar = tempnam(sys_get_temp_dir(), 'cookie');

function req($method, $url, $data = null, $multipart = false) {
    global $base, $jar;
    $ch = curl_init($base . $url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    
    $headers = [];
    
    // Get CSRF token first for mutating requests
    if (in_array($method, ['POST','PUT','PATCH','DELETE'])) {
        $csrf_ch = curl_init($base . '/auth/csrf-token');
        curl_setopt_array($csrf_ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $jar,
            CURLOPT_COOKIEFILE => $jar,
        ]);
        $csrf_res = json_decode(curl_exec($csrf_ch), true);
        curl_close($csrf_ch);
        $token = $csrf_res['csrfToken'] ?? '';
        $headers[] = 'X-CSRF-Token: ' . $token;
    }

    if ($data !== null) {
        if ($multipart) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($resp, true) ?: $resp];
}

echo "=== TARGETED BUG TEST ===\n\n";

// Step 1: Login as admin
echo "[LOGIN] Admin login...\n";
$r = req('POST', '/auth/login', ['email' => 'admin@example.com', 'password' => 'admin123']);
echo "  Status: {$r['code']} | ";
if ($r['code'] != 200) {
    // Try other common admin emails
    $r = req('POST', '/auth/login', ['email' => 'admin@aeropay.com', 'password' => 'admin123']);
    echo "Retry aeropay: {$r['code']} | ";
}
if ($r['code'] != 200) {
    echo "FAILED to login as admin. Checking what admin exists...\n";
    require_once dirname(__DIR__) . '/src/db.php';
    $stmt = $pdo->query("SELECT email, is_admin FROM users WHERE is_admin = TRUE LIMIT 5");
    $admins = $stmt->fetchAll();
    echo "  Admin accounts in DB: " . json_encode($admins) . "\n";
    if (!empty($admins)) {
        $adminEmail = $admins[0]['email'];
        echo "  Trying: $adminEmail\n";
        $r = req('POST', '/auth/login', ['email' => $adminEmail, 'password' => 'admin123']);
        echo "  Status: {$r['code']}\n";
        if ($r['code'] != 200) {
            $r = req('POST', '/auth/login', ['email' => $adminEmail, 'password' => 'password123']);
            echo "  Retry password123: {$r['code']}\n";
        }
        if ($r['code'] != 200) {
            $r = req('POST', '/auth/login', ['email' => $adminEmail, 'password' => 'Admin@123']);
            echo "  Retry Admin@123: {$r['code']}\n";
        }
    }
    if ($r['code'] != 200) {
        echo "  Cannot login as admin. Exiting.\n";
        exit(1);
    }
}
echo "Admin login OK\n";

// Step 2: Test Approve Deposit
echo "\n[TEST 1] Approve Deposit\n";
$deposits = req('GET', '/admin/deposits');
echo "  Fetch deposits: {$deposits['code']}\n";
if ($deposits['code'] == 200) {
    $pending = array_filter($deposits['body']['deposits'] ?? [], fn($d) => $d['status'] === 'pending');
    if (empty($pending)) {
        echo "  No pending deposits to test. Creating one...\n";
        // Create a test deposit via DB
        require_once dirname(__DIR__) . '/src/db.php';
        $userStmt = $pdo->query("SELECT id FROM users WHERE is_admin = FALSE LIMIT 1");
        $userId = $userStmt->fetchColumn();
        if ($userId) {
            $pdo->prepare("INSERT INTO deposit_requests (user_id, amount, status) VALUES (:uid, 50000, 'pending')")
                ->execute(['uid' => $userId]);
            $deposits = req('GET', '/admin/deposits');
            $pending = array_filter($deposits['body']['deposits'] ?? [], fn($d) => $d['status'] === 'pending');
        }
    }
    if (!empty($pending)) {
        $dep = array_values($pending)[0];
        echo "  Approving deposit ID: {$dep['id']}...\n";
        $approveRes = req('POST', "/admin/deposits/{$dep['id']}/approve");
        echo "  Approve result: {$approveRes['code']} | " . json_encode($approveRes['body']) . "\n";
    }
} else {
    echo "  ERROR: " . json_encode($deposits['body']) . "\n";
}

// Step 3: Test Create Product
echo "\n[TEST 2] Create Product\n";
// Create a tiny dummy image
$tmpImg = tempnam(sys_get_temp_dir(), 'img') . '.png';
// Create a minimal 1x1 PNG
$img = imagecreatetruecolor(50, 50);
imagepng($img, $tmpImg);
imagedestroy($img);

$formData = [
    'name' => 'TestPlan_' . time(),
    'price' => '50000',
    'durationDays' => '30',
    'dailyRewardPercent' => '5',
    'adWatchSeconds' => '30',
    'productImage' => new CURLFile($tmpImg, 'image/png', 'test.png'),
];
$createRes = req('POST', '/products/admin/create', $formData, true);
echo "  Create product: {$createRes['code']} | " . json_encode($createRes['body']) . "\n";
unlink($tmpImg);

// Step 4: Test Delete User
echo "\n[TEST 3] Delete User\n";
$users = req('GET', '/admin/users');
echo "  Fetch users: {$users['code']}\n";
if ($users['code'] == 200 && !empty($users['body']['users'])) {
    // Find a non-admin user named "Test User" or the first non-admin
    $target = null;
    foreach ($users['body']['users'] as $u) {
        if (stripos($u['name'] ?? '', 'test') !== false) {
            $target = $u;
            break;
        }
    }
    if (!$target) {
        // Create a dummy user to delete
        require_once dirname(__DIR__) . '/src/db.php';
        $hash = password_hash('test123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (email, password_hash, name) VALUES (:e, :p, 'DeleteMe') ON CONFLICT DO NOTHING")
            ->execute(['e' => 'deleteme@test.com', 'p' => $hash]);
        $users = req('GET', '/admin/users');
        foreach ($users['body']['users'] as $u) {
            if (stripos($u['name'] ?? '', 'deleteme') !== false) {
                $target = $u;
                break;
            }
        }
    }
    if ($target) {
        echo "  Deleting user: {$target['id']} ({$target['name']})...\n";
        $delRes = req('DELETE', "/admin/users/{$target['id']}");
        echo "  Delete result: {$delRes['code']} | " . json_encode($delRes['body']) . "\n";
    } else {
        echo "  No test user found to delete.\n";
    }
}

// Step 5: Test Logout
echo "\n[TEST 4] Logout\n";
$logoutRes = req('POST', '/auth/logout');
echo "  Logout result: {$logoutRes['code']} | " . json_encode($logoutRes['body']) . "\n";

// Verify session is destroyed
$meRes = req('GET', '/auth/me');
echo "  After logout /auth/me: {$meRes['code']} (expect 401)\n";

echo "\n=== DONE ===\n";
@unlink($jar);
