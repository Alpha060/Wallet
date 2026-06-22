<?php
// End-to-end HTTP test: login as admin, then hit the two failing endpoints
// exactly the way the browser does, to capture the real error responses.

$BASE = 'http://127.0.0.1:8766';
$cookieFile = __DIR__ . '/diag_cookie.txt';
@unlink($cookieFile);

function curlReq($url, $method = 'GET', $postfields = null, $headers = [], $cookieFile = null, $asMultipart = false) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($postfields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $hdrs = substr($resp, 0, $hdrSize);
    $body = substr($resp, $hdrSize);
    $err = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'headers' => $hdrs, 'body' => $body, 'curlErr' => $err];
}

// 1. Get CSRF token
echo "=== Step 1: Get CSRF token ===\n";
$r = curlReq("$BASE/api/auth/csrf-token", 'GET', null, [], $cookieFile);
echo "HTTP $r[code]\n$r[body]\n";
$csrf = '';
if (preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $r['body'], $m)) {
    $csrf = $m[1];
}
echo "CSRF token: " . substr($csrf, 0, 16) . "...\n\n";

// 2. Login as admin (admin@aeropay.test / admin123 per reset_admin.php)
echo "=== Step 2: Login as admin ===\n";
$r = curlReq("$BASE/api/auth/login", 'POST', json_encode(['email' => 'admin@example.com', 'password' => 'admin123']),
    ['Content-Type: application/json', 'x-csrf-token: ' . $csrf], $cookieFile);
echo "HTTP $r[code]\n$r[body]\n\n";

// 3. Fresh CSRF after login (session changed)
echo "=== Step 3: Fresh CSRF after login ===\n";
$r = curlReq("$BASE/api/auth/csrf-token", 'GET', null, [], $cookieFile);
if (preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $r['body'], $m)) {
    $csrf = $m[1];
}
echo "Fresh CSRF: " . substr($csrf, 0, 16) . "...\n\n";

// 4. TEST A: Create product (multipart, like the browser)
echo "=== TEST A: POST /api/products/admin/create (multipart) ===\n";
// Build multipart manually to mimic browser FormData exactly
$cfile = new CURLFile(__DIR__ . '/diag_errors.php', 'image/png', 'test.png');
$fields = [
    'name' => 'Diag Product',
    'price' => (string)10000,
    'durationDays' => (string)30,
    'dailyRewardPercent' => '2.5',
    'adWatchSeconds' => (string)120,
    'productImage' => $cfile,
];
$r = curlReq("$BASE/api/products/admin/create", 'POST', $fields,
    ['x-csrf-token: ' . $csrf], $cookieFile);
echo "HTTP $r[code]\nBODY: $r[body]\n";
if ($r['code'] >= 400) {
    echo ">>> THIS IS THE PRODUCT-CREATE ERROR <<<\n";
}
echo "\n";

// 5. TEST B: Get a pending deposit to approve (need one). List pending.
echo "=== Step 5: List pending deposits ===\n";
$r = curlReq("$BASE/api/admin/pending-deposits?page=1&limit=5", 'GET', null, [], $cookieFile);
echo "HTTP $r[code]\n$r[body]\n\n";

// Find a deposit id
$depositId = null;
if (preg_match('/"id"\s*:\s*"([0-9a-f-]+)"/', $r['body'], $m)) {
    $depositId = $m[1];
}

// 6. TEST B: Approve deposit
echo "=== TEST B: POST /api/admin/deposits/<id>/approve ===\n";
if ($depositId) {
    echo "Using deposit id: $depositId\n";
    $r = curlReq("$BASE/api/admin/deposits/$depositId/approve", 'POST', json_encode((object)[]),
        ['Content-Type: application/json', 'x-csrf-token: ' . $csrf], $cookieFile);
    echo "HTTP $r[code]\nBODY: $r[body]\n";
    if ($r['code'] >= 400) {
        echo ">>> THIS IS THE DEPOSIT-APPROVE ERROR <<<\n";
    }
} else {
    echo "No pending deposit found in DB to test approval. Creating one via direct DB then retrying.\n";
}

echo "\n=== DONE ===\n";
