<?php
// Diagnostic: reproduce the two failing operations against the DB to capture real errors.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access Denied: This script can only be run from the command line.');
}
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/helpers.php';

function dump($label, $e) {
    echo "[$label] ERROR: " . $e->getMessage() . "\n";
    echo "  Code: " . $e->getCode() . "\n";
    if (method_exists($e, 'errorInfo')) {
        echo "  errorInfo: " . json_encode($e->errorInfo) . "\n";
    }
    echo str_repeat('-', 60) . "\n";
}

echo "=== Connected. DB driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . " ===\n\n";

// Inspect products columns
echo "--- PRODUCTS columns ---\n";
foreach ($pdo->query("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_schema='public' AND table_name='products' ORDER BY ordinal_position") as $row) {
    echo json_encode($row) . "\n";
}

echo "\n--- WALLET_LEDGER columns ---\n";
foreach ($pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema='public' AND table_name='wallet_ledger' ORDER BY ordinal_position") as $row) {
    echo json_encode($row) . "\n";
}

echo "\n--- ADMIN_AUDIT_LOGS columns ---\n";
foreach ($pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema='public' AND table_name='admin_audit_logs' ORDER BY ordinal_position") as $row) {
    echo json_encode($row) . "\n";
}

echo "\n--- DEPOSIT_REQUESTS columns ---\n";
foreach ($pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema='public' AND table_name='deposit_requests' ORDER BY ordinal_position") as $row) {
    echo json_encode($row) . "\n";
}

echo "\n--- REFERRAL_BONUSES columns ---\n";
foreach ($pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema='public' AND table_name='referral_bonuses' ORDER BY ordinal_position") as $row) {
    echo json_encode($row) . "\n";
}

// Find a non-admin user and an admin for the test
$admin = $pdo->query("SELECT id FROM users WHERE is_admin = TRUE LIMIT 1")->fetch();
$user = $pdo->query("SELECT id, referred_by FROM users WHERE is_admin = FALSE AND deleted_at IS NULL LIMIT 1")->fetch();
echo "\n--- Sample users ---\n";
echo "Admin: " . json_encode($admin) . "\n";
echo "User:  " . json_encode($user) . "\n\n";

// ============ TEST 1: Product creation (the INSERT statement used by api.php) ============
echo "=== TEST 1: products INSERT (as in products/admin/create) ===\n";
try {
    $newId = generateUUID();
    $stmt = $pdo->prepare('
        INSERT INTO products (id, name, image_url, price, duration_days, daily_reward_percent, ad_watch_seconds, is_active)
        VALUES (:id, :name, :imageUrl, :price, :duration, :reward, :seconds, TRUE)
    ');
    $stmt->execute([
        'id' => $newId,
        'name' => '__DIAG_TEST_PRODUCT__',
        'imageUrl' => '/uploads/diag.png',
        'price' => 10000,
        'duration' => 30,
        'reward' => 2.5,
        'seconds' => 120,
    ]);
    echo "OK product inserted: $newId\n";
    // cleanup
    $pdo->prepare('DELETE FROM products WHERE id = :id')->execute(['id' => $newId]);
    echo "Cleaned up test product.\n";
} catch (Exception $e) {
    dump('products INSERT', $e);
}

// ============ TEST 2: Deposit approval flow (simulate the approve transaction) ============
echo "\n=== TEST 2: deposit approve flow ===\n";
if ($user && $admin) {
    try {
        $pdo->beginTransaction();

        // create a fake pending deposit for this user
        $depositId = generateUUID();
        $depStmt = $pdo->prepare('
            INSERT INTO deposit_requests (id, user_id, amount, payment_proof_url, transaction_id, status)
            VALUES (:id, :userId, :amount, :proof, :tx, \'pending\')
        ');
        $depStmt->execute([
            'id' => $depositId,
            'userId' => $user['id'],
            'amount' => 5000,
            'proof' => '/uploads/diag.png',
            'tx' => 'DIAGTX123',
        ]);
        echo "Created test deposit: $depositId\n";

        // lock + read
        $dStmt = $pdo->prepare('SELECT * FROM deposit_requests WHERE id = :id FOR UPDATE');
        $dStmt->execute(['id' => $depositId]);
        $deposit = $dStmt->fetch();

        $userStmt = $pdo->prepare('SELECT id, wallet_balance, referred_by FROM users WHERE id = :userId FOR UPDATE');
        $userStmt->execute(['userId' => $deposit['user_id']]);
        $u = $userStmt->fetch();

        $newBalance = $u['wallet_balance'] + $deposit['amount'];
        $pdo->prepare('UPDATE users SET wallet_balance = :bal WHERE id = :id')
            ->execute(['bal' => $newBalance, 'id' => $u['id']]);

        $pdo->prepare('UPDATE deposit_requests SET status = \'approved\', processed_at = NOW(), processed_by = :adminId WHERE id = :id')
            ->execute(['adminId' => $admin['id'], 'id' => $depositId]);

        if ($u['referred_by']) {
            $bonusAmount = (int)floor($deposit['amount'] * 0.05);
            if ($bonusAmount > 0) {
                echo "Trying referral_bonuses INSERT (referrer={$u['referred_by']})...\n";
                $insBonus = $pdo->prepare('
                    INSERT INTO referral_bonuses (id, referrer_id, referred_user_id, deposit_id, deposit_amount, bonus_amount, is_claimed)
                    VALUES (:id, :referrerId, :referredId, :depId, :depAmt, :bonusAmt, FALSE)
                ');
                $insBonus->execute([
                    'id' => generateUUID(),
                    'referrerId' => $u['referred_by'],
                    'referredId' => $u['id'],
                    'depId' => $depositId,
                    'depAmt' => $deposit['amount'],
                    'bonusAmt' => $bonusAmount,
                ]);
                echo "  referral_bonuses OK\n";
            }
        }

        echo "Trying wallet_ledger INSERT...\n";
        $pdo->prepare('
            INSERT INTO wallet_ledger (id, user_id, amount, direction, entry_type, reference_table, reference_id, balance_after, note, created_by)
            VALUES (:id, :userId, :amount, :direction, :entryType, :referenceTable, :referenceId, :balanceAfter, :note, :createdBy)
        ')->execute([
            'id' => generateUUID(),
            'userId' => $u['id'],
            'amount' => (int)$deposit['amount'],
            'direction' => 'credit',
            'entryType' => 'deposit_approved',
            'referenceTable' => 'deposit_requests',
            'referenceId' => $depositId,
            'balanceAfter' => $newBalance,
            'note' => 'Deposit approved by admin',
            'createdBy' => $admin['id'],
        ]);
        echo "  wallet_ledger OK\n";

        echo "Trying admin_audit_logs INSERT...\n";
        $pdo->prepare('
            INSERT INTO admin_audit_logs (id, admin_id, action, target_table, target_id, details)
            VALUES (:id, :adminId, :action, :targetTable, :targetId, :details)
        ')->execute([
            'id' => generateUUID(),
            'adminId' => $admin['id'],
            'action' => 'deposit_approve',
            'targetTable' => 'deposit_requests',
            'targetId' => $depositId,
            'details' => json_encode(['amount' => $deposit['amount'], 'userId' => $deposit['user_id']]),
        ]);
        echo "  admin_audit_logs OK\n";

        $pdo->rollBack(); // don't actually persist the test
        echo "Rolled back test (no data changed).\n";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        dump('deposit approve flow', $e);
    }
} else {
    echo "Skipping deposit test: no admin or non-admin user found.\n";
}

echo "\n=== DONE ===\n";
