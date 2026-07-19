<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access Denied: This script can only be run from the command line.');
}
// Test the USER deposit-creation flow (the prerequisite for approve-deposit)
$BASE = 'http://127.0.0.1:8766';
$cookieFile = __DIR__ . '/diag_cookie_user.txt';
@unlink($cookieFile);

// Real PNG
$imgPath = __DIR__ . '/diag_test.png';
file_put_contents($imgPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII='));

function curlReq($url, $method='GET', $pf=null, $headers=[], $cf=null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cf);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cf);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($pf !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $pf); }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['code'=>$code, 'body'=>substr($r,$hs)];
}

// login as a normal user (john.doe). Need password - reset not available for users.
// Instead, register a fresh user.
$r = curlReq("$BASE/api/auth/csrf-token"); preg_match('/"csrfToken":"([^"]+)"/',$r['body'],$m); $csrf=$m[1];
$email = 'diaguser_'.time().'@example.com';
$r = curlReq("$BASE/api/auth/register", 'POST', json_encode(['email'=>$email,'password'=>'password123','name'=>'Diag User']),
    ['Content-Type: application/json','x-csrf-token: '.$csrf], $cookieFile);
echo "Register HTTP $r[code]: " . substr($r['body'],0,80) . "\n";

// fresh csrf
$r = curlReq("$BASE/api/auth/csrf-token",'GET',null,[],$cookieFile); preg_match('/"csrfToken":"([^"]+)"/',$r['body'],$m); $csrf=$m[1];

// create deposit
echo "\n=== Create deposit (multipart) ===\n";
$cf = new CURLFile($imgPath,'image/png','proof.png');
$fields = ['amount'=>(string)50000, 'transactionId'=>'DIAGPAY123', 'paymentProof'=>$cf];
$r = curlReq("$BASE/api/deposits/create",'POST',$fields,['x-csrf-token: '.$csrf],$cookieFile);
echo "HTTP $r[code]\nBODY: $r[body]\n";
if ($r['code'] >= 400) echo ">>> DEPOSIT-CREATE FAILURE <<<\n";

@unlink($imgPath);
require dirname(__DIR__) . '/src/db.php';
// check the deposit
$d = $pdo->query("SELECT id,user_id,amount,status,payment_proof_url FROM deposit_requests ORDER BY created_at DESC LIMIT 1")->fetch();
echo "\nLatest deposit in DB: " . json_encode($d) . "\n";
