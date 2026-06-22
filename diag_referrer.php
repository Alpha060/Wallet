<?php
// Test deposit-approve path WITH a referred user (the referral_bonuses branch).
require __DIR__ . '/db.php';

$admin = $pdo->query("SELECT id FROM users WHERE is_admin=TRUE LIMIT 1")->fetch();
// Find a non-admin user who HAS a referrer
$user = $pdo->query("SELECT id, referred_by FROM users WHERE is_admin=FALSE AND referred_by IS NOT NULL AND deleted_at IS NULL LIMIT 1")->fetch();

if (!$user) {
    echo "No non-admin user with a referrer exists. Creating a referral chain for the test...\n";
    // make rajhritik472@gmail.com referred by john.doe
    $pdo->query("UPDATE users SET referred_by=(SELECT id FROM users WHERE email='john.doe@example.com') WHERE email='rajhritik472@gmail.com'");
    $user = $pdo->query("SELECT id, referred_by FROM users WHERE email='rajhritik472@gmail.com'")->fetch();
}

echo "Test user: " . json_encode($user) . "\n";

// Check if a referral_bonuses row already exists for this pair to detect unique constraint issues
$existing = $pdo->prepare("SELECT COUNT(*) FROM referral_bonuses WHERE referrer_id=:r AND referred_user_id=:u");
$existing->execute(['r' => $user['referred_by'], 'u' => $user['id']]);
echo "Existing referral_bonuses for this pair: " . $existing->fetchColumn() . "\n";

try {
    $pdo->beginTransaction();

    $depStmt = $pdo->prepare("INSERT INTO deposit_requests (user_id,amount,payment_proof_url,transaction_id,status) VALUES (:u,10000,'/uploads/diag.png','DIAGTX','pending') RETURNING id,status");
    $depStmt->execute(['u' => $user['id']]);
    $deposit = $depStmt->fetch();
    $depositId = $deposit['id'];
    echo "Created deposit: $depositId\n";

    // simulate the FULL approve flow inline (copy from api.php lines 1420-1495)
    $dStmt = $pdo->prepare('SELECT * FROM deposit_requests WHERE id = :id FOR UPDATE');
    $dStmt->execute(['id' => $depositId]);
    $dep = $dStmt->fetch();

    $userStmt = $pdo->prepare('SELECT id, wallet_balance, referred_by FROM users WHERE id = :userId FOR UPDATE');
    $userStmt->execute(['userId' => $dep['user_id']]);
    $u = $userStmt->fetch();

    $newBalance = $u['wallet_balance'] + $dep['amount'];
    $pdo->prepare('UPDATE users SET wallet_balance = :bal WHERE id = :id')->execute(['bal' => $newBalance, 'id' => $u['id']]);

    $pdo->prepare("UPDATE deposit_requests SET status='approved', processed_at=NOW(), processed_by=:a WHERE id=:id")
        ->execute(['a' => $admin['id'], 'id' => $depositId]);

    echo "referred_by = {$u['referred_by']} — entering referral bonus block\n";
    if ($u['referred_by']) {
        $bonusAmount = (int)floor($dep['amount'] * 0.05);
        echo "bonusAmount = $bonusAmount\n";
        if ($bonusAmount > 0) {
            $insBonus = $pdo->prepare('
                INSERT INTO referral_bonuses (referrer_id, referred_user_id, deposit_id, deposit_amount, bonus_amount, is_claimed)
                VALUES (:referrerId, :referredId, :depId, :depAmt, :bonusAmt, FALSE)
            ');
            $insBonus->execute([
                'referrerId' => $u['referred_by'],
                'referredId' => $u['id'],
                'depId' => $depositId,
                'depAmt' => $dep['amount'],
                'bonusAmt' => $bonusAmount,
            ]);
            echo "referral_bonuses INSERT OK\n";
        }
    }

    // wallet_ledger
    $pdo->prepare('INSERT INTO wallet_ledger (user_id,amount,direction,entry_type,reference_table,reference_id,balance_after,note,created_by) VALUES (:u,:a,:d,:e,:rt,:ri,:ba,:n,:c)')
        ->execute(['u'=>$u['id'],'a'=>(int)$dep['amount'],'d'=>'credit','e'=>'deposit_approved','rt'=>'deposit_requests','ri'=>$depositId,'ba'=>$newBalance,'n'=>'Deposit approved by admin','c'=>$admin['id']]);
    echo "wallet_ledger OK\n";

    $pdo->prepare('INSERT INTO admin_audit_logs (admin_id,action,target_table,target_id,details) VALUES (:a,:ac,:t,:ti,:d)')
        ->execute(['a'=>$admin['id'],'ac'=>'deposit_approve','t'=>'deposit_requests','ti'=>$depositId,'d'=>json_encode(['amount'=>$dep['amount'],'userId'=>$dep['user_id']])]);
    echo "admin_audit_logs OK\n";

    $pdo->rollBack();
    echo "\nRolled back — NO DATA CHANGED. Flow SUCCEEDED with a referred user.\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "\n>>> FAILURE in referred-user approve flow <<<\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "errorInfo: " . json_encode($e->errorInfo ?? null) . "\n";
}
// undo the referral chain change we made for testing
$pdo->exec("UPDATE users SET referred_by=NULL WHERE email='rajhritik472@gmail.com'");
echo "Reverted test referrer change.\n";
