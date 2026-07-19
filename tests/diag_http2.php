<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access Denied: This script can only be run from the command line.');
}
// More accurate end-to-end test using a REAL image file.
$BASE = 'http://127.0.0.1:8766';
$cookieFile = __DIR__ . '/diag_cookie.txt';
@unlink($cookieFile);

// Create a real PNG image (1x1 red pixel) so MIME validation passes.
$imgPath = __DIR__ . '/diag_test.png';
$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII='
);
file_put_contents($imgPath, $png);

function curlReq($url, $method = 'GET', $postfields = null, $headers = [], $cookieFile = null) {
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
    $body = substr($resp, $hdrSize);
    curl_close($ch);
    return ['code' => $code, 'body' => $body];
}

// CSRF
$r = curlReq("$BASE/api/auth/csrf-token", 'GET', null, [], $cookieFile);
preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $r['body'], $m);
$csrf = $m[1];

// Login
$r = curlReq("$BASE/api/auth/login", 'POST', json_encode(['email' => 'admin@example.com', 'password' => 'admin123']),
    ['Content-Type: application/json', 'x-csrf-token: ' . $csrf], $cookieFile);
echo "Login HTTP $r[code]: " . substr($r['body'], 0, 60) . "\n";

// Fresh CSRF
$r = curlReq("$BASE/api/auth/csrf-token", 'GET', null, [], $cookieFile);
preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $r['body'], $m);
$csrf = $m[1];

// ===== TEST A: product create with REAL png =====
echo "\n=== TEST A: Create product (real PNG) ===\n";
$cfile = new CURLFile($imgPath, 'image/png', 'test.png');
$fields = [
    'name' => 'Diag Yield',
    'price' => (string)10000,
    'durationDays' => (string)30,
    'dailyRewardPercent' => '2.5',
    'adWatchSeconds' => (string)120,
    'productImage' => $cfile,
];
$r = curlReq("$BASE/api/products/admin/create", 'POST', $fields, ['x-csrf-token: ' . $csrf], $cookieFile);
echo "HTTP $r[code]\nBODY: $r[body]\n";
if ($r['code'] >= 400) echo ">>> PRODUCT-CREATE FAILURE <<<\n";
else {
    // cleanup the created product
    if (preg_match('/"productId"\s*:\s*"([0-9a-f-]+)"/', $r['body'], $pm)) {
        if (!isset($pdo)) {
            require dirname(__DIR__) . '/src/db.php';
            require dirname(__DIR__) . '/src/helpers.php';
        }
        $pdo->prepare("DELETE FROM products WHERE id=:id")->execute(['id' => $pm[1]]);
        echo "Cleaned up created product {$pm[1]}\n";
    }
}

// ===== TEST B: deposit approve =====
echo "\n=== TEST B: Approve deposit ===\n";
if (!isset($pdo)) {
    require dirname(__DIR__) . '/src/db.php';
    require dirname(__DIR__) . '/src/helpers.php';
}
$user = $pdo->query("SELECT id FROM users WHERE is_admin=FALSE AND deleted_at IS NULL LIMIT 1")->fetch();
$depId = null;
if ($user) {
    $depId = generateUUID();
    $ins = $pdo->prepare("INSERT INTO deposit_requests (id,user_id,amount,payment_proof_url,transaction_id,status) VALUES (:id,:u,5000,'/uploads/diag.png','DIAGTX','pending')");
    $ins->execute(['id' => $depId, 'u' => $user['id']]);
    echo "Created pending deposit: $depId\n";
}
if ($depId) {
    // fresh CSRF
    $r = curlReq("$BASE/api/auth/csrf-token", 'GET', null, [], $cookieFile);
    preg_match('/"csrfToken"\s*:\s*"([^"]+)"/', $r['body'], $m);
    $csrf = $m[1];
    $r = curlReq("$BASE/api/admin/deposits/$depId/approve", 'POST', '{}',
        ['Content-Type: application/json', 'x-csrf-token: ' . $csrf], $cookieFile);
    echo "HTTP $r[code]\nBODY: $r[body]\n";
    if ($r['code'] >= 400) echo ">>> DEPOSIT-APPROVE FAILURE <<<\n";
    // check resulting state
    $chk = $pdo->prepare("SELECT status FROM deposit_requests WHERE id=:id");
    $chk->execute(['id' => $depId]);
    echo "Deposit status after: " . $chk->fetchColumn() . "\n";
    // cleanup: delete test deposit + ledger + audit
    $pdo->prepare("DELETE FROM wallet_ledger WHERE reference_id=:id")->execute(['id' => $depId]);
    $pdo->prepare("DELETE FROM admin_audit_logs WHERE target_id=:id")->execute(['id' => $depId]);
    $pdo->prepare("DELETE FROM deposit_requests WHERE id=:id")->execute(['id' => $depId]);
    echo "Cleaned up test deposit.\n";
}

@unlink($imgPath);
echo "\n=== DONE ===\n";
