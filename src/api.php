<?php
// api.php - Rest API front controller and endpoint handlers
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// Enable error logging but disable raw output displaying in JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Global CSRF exemption list (e.g. GET methods, public assets, or login/registration if sessions not yet started)
$csrfExempt = [
    'auth/csrf-token' => ['GET'],
    'auth/login' => ['POST'],
    'auth/register' => ['POST'],
    'auth/logout' => ['POST'],
    'referrals/verify' => ['POST']
];

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Parse URL variables from routes like: products/buy/uuid or admin/users/uuid/status
$routeParts = explode('/', trim($route, '/'));

// Check CSRF
$isExempt = isset($csrfExempt[$route]) && in_array($method, $csrfExempt[$route]);
if ($method !== 'GET' && !$isExempt) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    error_log("CSRF DEBUG: route=$route, method=$method, tokenPresent=" . (!empty($token) ? 'YES' : 'NO') . ", sessionId=" . session_id());
    if (!validateCsrfToken($token)) {
        initSession();
        error_log("CSRF FAIL: sent=$token, expected=" . ($_SESSION['csrf_token'] ?? 'NONE') . ", sessionId=" . session_id());
        jsonError('Invalid or expired CSRF token', 403, 'CSRF_TOKEN_INVALID');
    }
}

// Read JSON input payload
$input = [];
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $input = $_POST;
}

try {
    // -------------------------------------------------------------
    // CSRF TOKEN ENDPOINT
    // -------------------------------------------------------------
    if ($route === 'auth/csrf-token' && $method === 'GET') {
        jsonResponse(['csrfToken' => generateCsrfToken()]);
    }

    // -------------------------------------------------------------
    // AUTHENTICATION ENDPOINTS
    // -------------------------------------------------------------
    else if ($route === 'auth/login' && $method === 'POST') {
        enforceSessionRateLimit('auth_login', 8, 300);

        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (!$email || !$password) {
            jsonError('Email and password are required', 400, 'VALIDATION_ERROR');
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email AND deleted_at IS NULL');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !comparePassword($password, $user['password_hash'])) {
            jsonError('Invalid credentials', 401, 'INVALID_CREDENTIALS');
        }

        if ($user['is_active'] === false) {
            jsonError('Account has been deactivated. Please contact admin to reactivate.', 403, 'ACCOUNT_DEACTIVATED');
        }

        initSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['is_admin'] = (bool)$user['is_admin'];

        jsonResponse([
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'isAdmin' => (bool)$user['is_admin']
            ],
            'token' => 'session_active' // Placeholder for client API compat
        ]);
    }

    else if ($route === 'auth/register' && $method === 'POST') {
        enforceSessionRateLimit('auth_register', 5, 600);

        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $name = trim($input['name'] ?? '');
        $referralCode = trim($input['referralCode'] ?? '');

        if (!$email || !$password) {
            jsonError('Email and password are required', 400, 'VALIDATION_ERROR');
        }

        if (strlen($password) < 8) {
            jsonError('Password must be at least 8 characters long', 400, 'VALIDATION_ERROR');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonError('Invalid email format', 400, 'VALIDATION_ERROR');
        }

        // Check unique email
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            jsonError('Email already registered', 422, 'DUPLICATE_EMAIL');
        }

        // Handle referral code verification
        $referrerId = null;
        if (!empty($referralCode)) {
            $refStmt = $pdo->prepare('SELECT id FROM users WHERE UPPER(referral_code) = UPPER(:code) AND is_admin = FALSE');
            $refStmt->execute(['code' => $referralCode]);
            $referrer = $refStmt->fetch();
            if (!$referrer) {
                jsonError('Invalid referral code', 400, 'INVALID_REFERRAL_CODE');
            }
            $referrerId = $referrer['id'];
        }

        $passwordHash = hashPassword($password);
        $userId = generateUUID();
        $referralCodeGenerated = generateReferralCode($pdo);

        $pdo->beginTransaction();
        try {
            $insStmt = $pdo->prepare('
                INSERT INTO users (id, email, password_hash, name, referred_by, referral_code, is_admin, is_active)
                VALUES (:id, :email, :password_hash, :name, :referred_by, :referral_code, FALSE, TRUE)
            ');
            $insStmt->execute([
                'id' => $userId,
                'email' => $email,
                'password_hash' => $passwordHash,
                'name' => $name ?: null,
                'referred_by' => $referrerId,
                'referral_code' => $referralCodeGenerated
            ]);
            
            $newUser = [
                'id' => $userId,
                'email' => $email,
                'name' => $name ?: null,
                'is_admin' => false
            ];
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        initSession();
        $_SESSION['user_id'] = $newUser['id'];
        $_SESSION['email'] = $newUser['email'];
        $_SESSION['name'] = $newUser['name'];
        $_SESSION['is_admin'] = (bool)$newUser['is_admin'];

        jsonResponse([
            'user' => [
                'id' => $newUser['id'],
                'email' => $newUser['email'],
                'name' => $newUser['name'],
                'isAdmin' => (bool)$newUser['is_admin']
            ],
            'token' => 'session_active'
        ], 201);
    }

    else if ($route === 'auth/logout' && $method === 'POST') {
        initSession();
        session_destroy();
        jsonResponse(['success' => true]);
    }

    else if ($route === 'auth/reset-password' && $method === 'POST') {
        enforceSessionRateLimit('auth_reset_password', 5, 900);

        $email = trim($input['email'] ?? '');
        $pan = strtoupper(trim($input['panNumber'] ?? ''));
        $dob = trim($input['dateOfBirth'] ?? '');
        $newPassword = $input['newPassword'] ?? '';

        if (!$email || !$pan || !$dob || !$newPassword) {
            jsonError('Email, PAN, date of birth, and new password are required.', 400, 'VALIDATION_ERROR');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !validatePAN($pan)) {
            jsonError('Invalid reset details.', 400, 'VALIDATION_ERROR');
        }

        if (strlen($newPassword) < 8) {
            jsonError('New password must be at least 8 characters long.', 400, 'VALIDATION_ERROR');
        }

        $stmt = $pdo->prepare('
            SELECT id, pan_number, date_of_birth, is_active
            FROM users
            WHERE email = :email AND is_admin = FALSE AND deleted_at IS NULL
        ');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !$user['is_active'] || empty($user['pan_number']) || empty($user['date_of_birth'])) {
            jsonError('Password reset could not be completed. Please contact administration for manual verification.', 400, 'RESET_NOT_AVAILABLE');
        }

        if (strtoupper($user['pan_number']) !== $pan || $user['date_of_birth'] !== $dob) {
            jsonError('Password reset could not be completed. Please verify your details.', 400, 'RESET_DETAILS_MISMATCH');
        }

        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['hash' => hashPassword($newPassword), 'id' => $user['id']]);

        jsonResponse(['success' => true, 'message' => 'Password reset successfully. You can now log in.']);
    }

    // -------------------------------------------------------------
    // PROTECTED USER ENDPOINTS
    // -------------------------------------------------------------
    else {
        $currentUser = requireAuth();

        if ($route === 'auth/me' && $method === 'GET') {
            $stmt = $pdo->prepare('SELECT id, email, name, mobile_number as "mobileNumber", aadhar_number as "aadharNumber", date_of_birth as "dateOfBirth", pan_number as "panNumber", is_admin as "isAdmin" FROM users WHERE id = :id');
            $stmt->execute(['id' => $currentUser['id']]);
            $profile = $stmt->fetch();
            jsonResponse($profile);
        }

        else if ($route === 'auth/profile' && $method === 'PUT') {
            if (isset($input['currentPassword']) && isset($input['newPassword'])) {
                // Change Password flow
                $currentPassword = $input['currentPassword'];
                $newPassword = $input['newPassword'];

                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
                $stmt->execute(['id' => $currentUser['id']]);
                $userDb = $stmt->fetch();

                if (!comparePassword($currentPassword, $userDb['password_hash'])) {
                    jsonError('Current password is incorrect', 400, 'PASSWORD_ERROR');
                }

                if (strlen($newPassword) < 8) {
                    jsonError('New password must be at least 8 characters long', 400, 'PASSWORD_ERROR');
                }

                $newHash = hashPassword($newPassword);
                $updateStmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $updateStmt->execute(['hash' => $newHash, 'id' => $currentUser['id']]);

                jsonResponse(['success' => true, 'message' => 'Password updated successfully']);
            } else {
                // Update profile data
                $name = trim($input['name'] ?? '');
                $mobile = trim($input['mobileNumber'] ?? '');
                $dob = trim($input['dateOfBirth'] ?? '');
                $aadhar = trim($input['aadharNumber'] ?? '');
                $pan = strtoupper(trim($input['panNumber'] ?? ''));

                if ($mobile && !validateMobileNumber($mobile)) {
                    jsonError('Invalid mobile number. Enter a 10 digit Indian mobile number.', 400, 'VALIDATION_ERROR');
                }

                if ($aadhar && !validateAadharNumber($aadhar)) {
                    jsonError('Invalid Aadhar number. Enter exactly 12 digits.', 400, 'VALIDATION_ERROR');
                }

                if ($pan && !validatePAN($pan)) {
                    jsonError('Invalid PAN number format.', 400, 'VALIDATION_ERROR');
                }

                if ($dob) {
                    $dobObj = DateTime::createFromFormat('Y-m-d', $dob);
                    if (!$dobObj || $dobObj->format('Y-m-d') !== $dob || $dobObj > new DateTime('-18 years')) {
                        jsonError('Invalid date of birth. User must be at least 18 years old.', 400, 'VALIDATION_ERROR');
                    }
                }

                $updateStmt = $pdo->prepare('
                    UPDATE users 
                    SET name = :name, mobile_number = :mobile, date_of_birth = :dob, aadhar_number = :aadhar, pan_number = :pan
                    WHERE id = :id
                ');
                $updateStmt->execute([
                    'name' => $name ?: null,
                    'mobile' => $mobile ?: null,
                    'dob' => $dob ?: null,
                    'aadhar' => $aadhar ?: null,
                    'pan' => $pan ?: null,
                    'id' => $currentUser['id']
                ]);

                jsonResponse(['success' => true, 'message' => 'Profile updated successfully']);
            }
        }

        else if ($route === 'auth/payment-details' && $method === 'GET') {
            $stmt = $pdo->prepare('SELECT saved_upi_id as "savedUpiId", saved_bank_details as "savedBankDetails", preferred_payment_method as "preferredPaymentMethod" FROM users WHERE id = :id');
            $stmt->execute(['id' => $currentUser['id']]);
            $data = $stmt->fetch();
            if ($data && $data['savedBankDetails']) {
                $data['savedBankDetails'] = is_string($data['savedBankDetails']) ? json_decode($data['savedBankDetails'], true) : $data['savedBankDetails'];
            }
            jsonResponse($data);
        }

        else if ($route === 'auth/payment-details' && $method === 'PUT') {
            $preferred = $input['preferredPaymentMethod'] ?? 'upi';
            $upiId = trim($input['upiId'] ?? '');
            $bankDetails = $input['bankDetails'] ?? null;

            if (!in_array($preferred, ['upi', 'bank'], true)) {
                jsonError('Invalid preferred payment method', 400);
            }

            if ($upiId && !validateUPI($upiId)) {
                jsonError('Invalid UPI ID format. Expected user@bank', 400);
            }

            if ($bankDetails) {
                if (!empty($bankDetails['accountName']) && strlen(trim($bankDetails['accountName'])) < 2) {
                    jsonError('Account holder name is too short', 400);
                }
                if (!empty($bankDetails['accountNumber']) && !preg_match('/^[0-9]{6,20}$/', $bankDetails['accountNumber'])) {
                    jsonError('Invalid account number format', 400);
                }
                if (!empty($bankDetails['ifscCode']) && !validateIFSC($bankDetails['ifscCode'])) {
                    jsonError('Invalid IFSC code format', 400);
                }
            }

            $stmt = $pdo->prepare('
                UPDATE users 
                SET preferred_payment_method = :pref, saved_upi_id = :upi, saved_bank_details = :bank
                WHERE id = :id
            ');
            $stmt->execute([
                'pref' => $preferred,
                'upi' => $upiId ?: null,
                'bank' => $bankDetails ? json_encode($bankDetails) : null,
                'id' => $currentUser['id']
            ]);

            jsonResponse(['success' => true, 'message' => 'Payment settings updated successfully']);
        }

        else if ($route === 'wallet/balance' && $method === 'GET') {
            $stmt = $pdo->prepare('SELECT wallet_balance as "balance" FROM users WHERE id = :id');
            $stmt->execute(['id' => $currentUser['id']]);
            $res = $stmt->fetch();
            jsonResponse(['balance' => (int)$res['balance']]);
        }

        else if ($route === 'wallet/summary' && $method === 'GET') {
            $depStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM deposit_requests WHERE user_id = :userId AND status = \'approved\'');
            $depStmt->execute(['userId' => $currentUser['id']]);

            $withStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests WHERE user_id = :userId AND status = \'completed\'');
            $withStmt->execute(['userId' => $currentUser['id']]);

            $rewardStmt = $pdo->prepare('SELECT COALESCE(SUM(reward_amount), 0) FROM ad_watch_log WHERE user_id = :userId');
            $rewardStmt->execute(['userId' => $currentUser['id']]);

            $bonusStmt = $pdo->prepare('
                SELECT COALESCE(SUM(amount), 0)
                FROM bonus_claim_requests
                WHERE user_id = :userId AND status = \'approved\'
            ');
            $bonusStmt->execute(['userId' => $currentUser['id']]);

            jsonResponse([
                'totalApprovedDeposits' => (int)$depStmt->fetchColumn(),
                'totalCompletedWithdrawals' => (int)$withStmt->fetchColumn(),
                'totalAdRewards' => (int)$rewardStmt->fetchColumn(),
                'totalReferralBonuses' => (int)$bonusStmt->fetchColumn()
            ]);
        }

        else if ($route === 'wallet/transactions' && $method === 'GET') {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? 10));
            $offset = ($page - 1) * $limit;
            $type = $_GET['type'] ?? 'all';

            // We combine Deposits and Withdrawals and Referral Claims into a single transaction history
            $transactions = [];
            $total = 0;

            if ($type === 'all' || $type === 'deposit') {
                $depStmt = $pdo->prepare('SELECT id, amount, status, created_at as "createdAt", \'deposit\' as type, rejection_reason as "rejectionReason" FROM deposit_requests WHERE user_id = :userId');
                $depStmt->execute(['userId' => $currentUser['id']]);
                $transactions = array_merge($transactions, $depStmt->fetchAll());
            }

            if ($type === 'all' || $type === 'withdrawal') {
                $withStmt = $pdo->prepare('SELECT id, amount, status, created_at as "createdAt", \'withdrawal\' as type, rejection_reason as "rejectionReason" FROM withdrawal_requests WHERE user_id = :userId');
                $withStmt->execute(['userId' => $currentUser['id']]);
                $transactions = array_merge($transactions, $withStmt->fetchAll());
            }

            // Sort merged array by date descending
            usort($transactions, function($a, $b) {
                return strcmp($b['createdAt'], $a['createdAt']);
            });

            $total = count($transactions);
            $sliced = array_slice($transactions, $offset, $limit);

            jsonResponse([
                'transactions' => $sliced,
                'total' => $total,
                'page' => $page,
                'totalPages' => ceil($total / $limit)
            ]);
        }

        else if ($route === 'deposits/create' && $method === 'POST') {
            $amountVal = $_POST['amount'] ?? '';
            $txId = trim($_POST['transactionId'] ?? '');

            if (!$amountVal) {
                jsonError('Amount is required', 400);
            }

            $amount = (int)$amountVal;
            if ($amount <= 0) {
                jsonError('Amount must be greater than zero', 400);
            }

            if ($amount < 100) {
                jsonError('Minimum deposit amount is ₹1 (100 paise)', 400);
            }

            $uploadRes = handleUploadedFile('paymentProof');
            if (isset($uploadRes['error'])) {
                jsonError($uploadRes['error'], 400);
            }

            $depositId = generateUUID();
            $stmt = $pdo->prepare('
                INSERT INTO deposit_requests (id, user_id, amount, payment_proof_url, transaction_id, status)
                VALUES (:id, :userId, :amount, :proofUrl, :txId, \'pending\')
            ');
            $stmt->execute([
                'id' => $depositId,
                'userId' => $currentUser['id'],
                'amount' => $amount,
                'proofUrl' => $uploadRes['path'],
                'txId' => $txId ?: null
            ]);

            jsonResponse([
                'depositId' => $depositId,
                'status' => 'pending'
            ], 201);
        }

        else if ($route === 'deposits/payment-details' && $method === 'GET') {
            // Returns system settings for UPI & QR code
            $stmt = $pdo->query('SELECT qr_code_url as "qrCodeUrl", upi_id as "upiId" FROM admin_settings LIMIT 1');
            $settings = $stmt->fetch();
            jsonResponse($settings ?: ['qrCodeUrl' => null, 'upiId' => null]);
        }

        else if ($route === 'admin/payment-methods/public' && $method === 'GET') {
            // Returns public payment methods
            $stmt = $pdo->query('SELECT id, label, qr_code_url as "qrCodeUrl", upi_id as "upiId" FROM payment_methods WHERE is_active = TRUE ORDER BY sort_order ASC');
            jsonResponse(['methods' => $stmt->fetchAll()]);
        }

        else if ($route === 'products' && $method === 'GET') {
            $stmt = $pdo->query('SELECT id, name, image_url as "imageUrl", price, duration_days as "durationDays", daily_reward_percent as "dailyRewardPercent", ad_watch_seconds as "adWatchSeconds" FROM products WHERE is_active = TRUE AND deleted_at IS NULL ORDER BY price ASC');
            jsonResponse(['products' => $stmt->fetchAll()]);
        }

        else if ($route === 'products/my-investments' && $method === 'GET') {
            // First process expired ones automatically
            // Find expired active investments
            $expStmt = $pdo->query('SELECT id, user_id, purchase_price FROM user_investments WHERE is_sold = FALSE AND expires_at <= NOW()');
            $expired = $expStmt->fetchAll();
            foreach ($expired as $inv) {
                $pdo->beginTransaction();
                try {
                    // Credit user balance
                    $balStmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + :price WHERE id = :userId');
                    $balStmt->execute(['price' => $inv['purchase_price'], 'userId' => $inv['user_id']]);
                    
                    $selStmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE id = :userId');
                    $selStmt->execute(['userId' => $inv['user_id']]);
                    $balanceAfter = (int)$selStmt->fetchColumn();
                    // Mark as sold/expired
                    $pdo->prepare('UPDATE user_investments SET is_sold = TRUE, sold_at = NOW() WHERE id = :id')
                        ->execute(['id' => $inv['id']]);
                    recordWalletLedger($inv['user_id'], $inv['purchase_price'], 'credit', 'investment_expiry_refund', 'user_investments', $inv['id'], $balanceAfter, 'Principal returned after contract expiry');
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                }
            }

            $stmt = $pdo->prepare('
                SELECT ui.id, ui.product_id as "productId", ui.purchase_price as "purchasePrice", ui.purchased_at as "purchasedAt", ui.expires_at as "expiresAt",
                       p.name, p.image_url as "imageUrl", p.daily_reward_percent as "dailyRewardPercent", p.ad_watch_seconds as "adWatchSeconds",
                       EXISTS(SELECT 1 FROM ad_watch_log awl WHERE awl.user_investment_id = ui.id AND awl.watched_at::date = CURRENT_DATE) as "watchedToday"
                FROM user_investments ui
                JOIN products p ON ui.product_id = p.id
                WHERE ui.user_id = :userId AND ui.is_sold = FALSE
                ORDER BY ui.purchased_at DESC
            ');
            $stmt->execute(['userId' => $currentUser['id']]);
            jsonResponse(['investments' => $stmt->fetchAll()]);
        }

        else if ($route === 'products/investment-history' && $method === 'GET') {
            // Fetch buys
            $buyStmt = $pdo->prepare('
                SELECT ui.id, ui.purchase_price as amount, ui.purchased_at as "createdAt", p.name
                FROM user_investments ui
                JOIN products p ON ui.product_id = p.id
                WHERE ui.user_id = :userId
                ORDER BY ui.purchased_at DESC
            ');
            $buyStmt->execute(['userId' => $currentUser['id']]);
            $buys = $buyStmt->fetchAll();

            // Fetch sells (refunds)
            $sellStmt = $pdo->prepare('
                SELECT ui.id, ui.purchase_price as amount, ui.sold_at as "createdAt", p.name
                FROM user_investments ui
                JOIN products p ON ui.product_id = p.id
                WHERE ui.user_id = :userId AND ui.is_sold = TRUE AND ui.sold_at IS NOT NULL
                ORDER BY ui.sold_at DESC
            ');
            $sellStmt->execute(['userId' => $currentUser['id']]);
            $sells = $sellStmt->fetchAll();

            // Fetch daily rewards
            $rewardStmt = $pdo->prepare('
                SELECT awl.id, awl.reward_amount as amount, awl.watched_at as "createdAt", p.name
                FROM ad_watch_log awl
                JOIN user_investments ui ON awl.user_investment_id = ui.id
                JOIN products p ON ui.product_id = p.id
                WHERE awl.user_id = :userId
                ORDER BY awl.watched_at DESC
            ');
            $rewardStmt->execute(['userId' => $currentUser['id']]);
            $rewards = $rewardStmt->fetchAll();

            jsonResponse([
                'buys' => $buys,
                'sells' => $sells,
                'rewards' => $rewards
            ]);
        }

        else if (isset($routeParts[0]) && $routeParts[0] === 'products' && isset($routeParts[1]) && $routeParts[1] === 'buy' && isset($routeParts[2])) {
            // POST products/buy/<id>
            if ($method !== 'POST') jsonError('Method not allowed', 405);
            $productId = $routeParts[2];

            $pdo->beginTransaction();
            try {
                // Fetch product
                $prodStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id AND is_active = TRUE AND deleted_at IS NULL');
                $prodStmt->execute(['id' => $productId]);
                $product = $prodStmt->fetch();

                if (!$product) throw new Exception('Product not found or inactive');

                // Lock and check user balance
                $userStmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE id = :id FOR UPDATE');
                $userStmt->execute(['id' => $currentUser['id']]);
                $user = $userStmt->fetch();

                if ($user['wallet_balance'] < $product['price']) {
                    throw new Exception('Insufficient wallet balance');
                }

                // Check daily limit (3 buys of same asset per day)
                $limitStmt = $pdo->prepare('
                    SELECT COUNT(id) FROM user_investments 
                    WHERE user_id = :userId AND product_id = :productId AND DATE(purchased_at) = CURRENT_DATE()
                ');
                $limitStmt->execute(['userId' => $currentUser['id'], 'productId' => $productId]);
                $todayBuys = (int)$limitStmt->fetchColumn();
                if ($todayBuys >= 3) {
                    throw new Exception('Daily limit reached: You can only buy the same asset 3 times per day');
                }

                // Deduct balance
                $newBalance = $user['wallet_balance'] - $product['price'];
                $pdo->prepare('UPDATE users SET wallet_balance = :bal WHERE id = :id')
                    ->execute(['bal' => $newBalance, 'id' => $currentUser['id']]);

                // Create investment
                $investmentId = generateUUID();
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$product['duration_days']} days"));
                $insStmt = $pdo->prepare('
                    INSERT INTO user_investments (id, user_id, product_id, purchase_price, expires_at)
                    VALUES (:id, :userId, :productId, :price, :expiresAt)
                ');
                $insStmt->execute([
                    'id' => $investmentId,
                    'userId' => $currentUser['id'],
                    'productId' => $productId,
                    'price' => $product['price'],
                    'expiresAt' => $expiresAt
                ]);

                recordWalletLedger($currentUser['id'], $product['price'], 'debit', 'investment_purchase', 'user_investments', $investmentId, $newBalance, 'Yield asset purchase');

                $pdo->commit();
                jsonResponse([
                    'success' => true,
                    'newBalance' => $newBalance,
                    'investmentId' => $investmentId
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                jsonError($e->getMessage(), 400);
            }
        }

        else if (isset($routeParts[0]) && $routeParts[0] === 'products' && isset($routeParts[1]) && $routeParts[1] === 'sell' && isset($routeParts[2])) {
            // POST products/sell/<id> (early exit refund)
            if ($method !== 'POST') jsonError('Method not allowed', 405);
            $investmentId = $routeParts[2];

            $pdo->beginTransaction();
            try {
                $invStmt = $pdo->prepare('SELECT * FROM user_investments WHERE id = :id FOR UPDATE');
                $invStmt->execute(['id' => $investmentId]);
                $inv = $invStmt->fetch();

                if (!$inv || $inv['user_id'] !== $currentUser['id']) throw new Exception('Investment not found');
                if ($inv['is_sold']) throw new Exception('Investment already sold or expired');

                // Limit early exit (max 3 sells of same asset per day)
                $limitStmt = $pdo->prepare('
                    SELECT COUNT(id) FROM user_investments 
                    WHERE user_id = :userId AND product_id = :productId AND is_sold = TRUE AND DATE(sold_at) = CURRENT_DATE()
                ');
                $limitStmt->execute(['userId' => $currentUser['id'], 'productId' => $inv['product_id']]);
                $todaySells = (int)$limitStmt->fetchColumn();
                if ($todaySells >= 3) {
                    throw new Exception('Daily limit reached: You can only exit/sell the same asset 3 times per day');
                }

                // Refund purchase price
                $userStmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE id = :id FOR UPDATE');
                $userStmt->execute(['id' => $currentUser['id']]);
                $user = $userStmt->fetch();
                $newBalance = $user['wallet_balance'] + $inv['purchase_price'];

                $pdo->prepare('UPDATE users SET wallet_balance = :bal WHERE id = :id')
                    ->execute(['bal' => $newBalance, 'id' => $currentUser['id']]);

                // Mark as sold
                $pdo->prepare('UPDATE user_investments SET is_sold = TRUE, sold_at = NOW() WHERE id = :id')
                    ->execute(['id' => $investmentId]);

                recordWalletLedger($currentUser['id'], $inv['purchase_price'], 'credit', 'investment_early_refund', 'user_investments', $investmentId, $newBalance, 'Principal returned after early exit');

                $pdo->commit();
                jsonResponse([
                    'success' => true,
                    'newBalance' => $newBalance,
                    'refundAmount' => $inv['purchase_price']
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                jsonError($e->getMessage(), 400);
            }
        }

        else if (isset($routeParts[0]) && $routeParts[0] === 'products' && isset($routeParts[1]) && $routeParts[1] === 'ad-url' && isset($routeParts[2])) {
            // GET products/ad-url/<id>
            $investmentId = $routeParts[2];
            $stmt = $pdo->prepare('SELECT ui.*, p.duration_days, p.ad_watch_seconds FROM user_investments ui JOIN products p ON ui.product_id = p.id WHERE ui.id = :id');
            $stmt->execute(['id' => $investmentId]);
            $inv = $stmt->fetch();

            if (!$inv || $inv['user_id'] !== $currentUser['id']) jsonError('Investment not found', 404);
            if ($inv['is_sold']) jsonError('Investment already completed', 400);

            // Compute current day number
            $pDate = new DateTime($inv['purchased_at']);
            $today = new DateTime();
            $pDate->setTime(0, 0, 0);
            $today->setTime(0, 0, 0);
            $dayNumber = $today->diff($pDate)->days + 1;

            // Fetch product-specific ad link
            $adStmt = $pdo->prepare('SELECT video_url FROM product_ad_links WHERE product_id = :prodId AND day_number = :day');
            $adStmt->execute(['prodId' => $inv['product_id'], 'day' => $dayNumber]);
            $adUrl = $adStmt->fetchColumn();

            if ($adUrl) {
                initSession();
                $claimToken = bin2hex(random_bytes(24));
                $_SESSION['ad_claims'][$investmentId] = [
                    'token' => $claimToken,
                    'available_after' => time() + max(1, (int)$inv['ad_watch_seconds'])
                ];
                jsonResponse(['videoUrl' => $adUrl, 'dayNumber' => $dayNumber, 'source' => 'product', 'claimToken' => $claimToken]);
            }

            // Fallback to global ad url
            $glStmt = $pdo->query('SELECT video_url FROM daily_ad ORDER BY created_at DESC LIMIT 1');
            $glUrl = $glStmt->fetchColumn();

            if ($glUrl) {
                initSession();
                $claimToken = bin2hex(random_bytes(24));
                $_SESSION['ad_claims'][$investmentId] = [
                    'token' => $claimToken,
                    'available_after' => time() + max(1, (int)$inv['ad_watch_seconds'])
                ];
                jsonResponse(['videoUrl' => $glUrl, 'dayNumber' => $dayNumber, 'source' => 'global', 'claimToken' => $claimToken]);
            }

            jsonError('No ad links have been scheduled for today by admin.', 400);
        }

        else if (isset($routeParts[0]) && $routeParts[0] === 'products' && isset($routeParts[1]) && $routeParts[1] === 'watch' && isset($routeParts[2])) {
            // POST products/watch/<id> (claim daily reward)
            if ($method !== 'POST') jsonError('Method not allowed', 405);
            $investmentId = $routeParts[2];
            $claimToken = $input['claimToken'] ?? '';

            initSession();
            $claim = $_SESSION['ad_claims'][$investmentId] ?? null;
            if (!$claim || !hash_equals($claim['token'], $claimToken)) {
                jsonError('Ad watch session is invalid or expired. Please replay the ad.', 400, 'AD_CLAIM_INVALID');
            }
            if (time() < (int)$claim['available_after']) {
                jsonError('Ad watch time has not completed yet.', 400, 'AD_WATCH_INCOMPLETE');
            }

            $pdo->beginTransaction();
            try {
                $invStmt = $pdo->prepare('SELECT ui.*, p.daily_reward_percent FROM user_investments ui JOIN products p ON ui.product_id = p.id WHERE ui.id = :id FOR UPDATE');
                $invStmt->execute(['id' => $investmentId]);
                $inv = $invStmt->fetch();

                if (!$inv || $inv['user_id'] !== $currentUser['id']) throw new Exception('Investment not found');
                if ($inv['is_sold']) throw new Exception('Investment already completed');
                if (strtotime($inv['expires_at']) <= time()) throw new Exception('Investment contract has expired');

                // Check double watch today
                $chkStmt = $pdo->prepare('SELECT id FROM ad_watch_log WHERE user_investment_id = :id AND DATE(watched_at) = CURRENT_DATE()');
                $chkStmt->execute(['id' => $investmentId]);
                if ($chkStmt->fetch()) {
                    throw new Exception('Already claimed daily reward for this asset today.');
                }

                // Reward amount = Price * dailyRewardPercent
                $reward = (int)floor(($inv['purchase_price'] * (float)$inv['daily_reward_percent']) / 100);

                // Log ad watch
                $adWatchId = generateUUID();
                $insStmt = $pdo->prepare('INSERT INTO ad_watch_log (id, user_investment_id, user_id, reward_amount) VALUES (:id, :invId, :userId, :reward)');
                $insStmt->execute(['id' => $adWatchId, 'invId' => $investmentId, 'userId' => $currentUser['id'], 'reward' => $reward]);

                // Credit balance
                $balStmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + :reward WHERE id = :userId');
                $balStmt->execute(['reward' => $reward, 'userId' => $currentUser['id']]);
                
                $selStmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE id = :userId');
                $selStmt->execute(['userId' => $currentUser['id']]);
                $balanceAfter = (int)$selStmt->fetchColumn();

                recordWalletLedger($currentUser['id'], $reward, 'credit', 'ad_reward', 'ad_watch_log', $adWatchId, $balanceAfter, 'Daily ad reward');
                unset($_SESSION['ad_claims'][$investmentId]);

                $pdo->commit();
                jsonResponse(['success' => true, 'message' => "Successfully claimed reward of " . formatRupees($reward) . "!"]);
            } catch (Exception $e) {
                $pdo->rollBack();
                jsonError($e->getMessage(), 400);
            }
        }

        else if ($route === 'products/daily-ad' && $method === 'GET') {
            $stmt = $pdo->query('SELECT video_url as "videoUrl" FROM daily_ad ORDER BY created_at DESC LIMIT 1');
            $ad = $stmt->fetch();
            jsonResponse(['ad' => $ad ?: ['videoUrl' => '']]);
        }

        else if ($route === 'withdrawals/create' && $method === 'POST') {
            $amount = (int)($input['amount'] ?? 0);
            $bankDetails = $input['bankDetails'] ?? null;
            $paymentMethod = 'bank';

            if ($amount <= 0) jsonError('Amount must be greater than zero', 400);
            if ($amount < 100) jsonError('Minimum withdrawal amount is ₹1 (100 paise)', 400);

            // Verify referral requirements
            $stmt = $pdo->query('SELECT COALESCE(required_referrals, 5) FROM admin_settings LIMIT 1');
            $requiredRefs = (int)$stmt->fetchColumn();

            $refStmt = $pdo->prepare('
                SELECT COUNT(DISTINCT u.id) 
                FROM users u 
                JOIN deposit_requests d ON u.id = d.user_id 
                WHERE u.referred_by = :userId AND d.status = \'approved\'
            ');
            $refStmt->execute(['userId' => $currentUser['id']]);
            $confirmedRefs = (int)$refStmt->fetchColumn();

            if ($confirmedRefs < $requiredRefs) {
                $needed = $requiredRefs - $confirmedRefs;
                jsonError("Requirement not met: You need {$needed} more confirmed referral" . ($needed > 1 ? 's' : '') . " to withdraw.", 400);
            }

            if (!$bankDetails || !is_array($bankDetails)) jsonError('Payment details are required', 400);

            if (!empty($bankDetails['upiId'])) {
                $upiId = trim($bankDetails['upiId']);
                if (!validateUPI($upiId)) {
                    jsonError('Invalid UPI ID format. Expected user@bank', 400);
                }
                $bankDetails = ['upiId' => $upiId];
                $paymentMethod = 'upi';
            } else {
                $accountName = trim($bankDetails['accountName'] ?? '');
                $accountNumber = trim($bankDetails['accountNumber'] ?? '');
                $ifscCode = strtoupper(trim($bankDetails['ifscCode'] ?? ''));

                if (strlen($accountName) < 2) {
                    jsonError('Account holder name is required', 400);
                }
                if (!preg_match('/^[0-9]{6,20}$/', $accountNumber)) {
                    jsonError('Invalid account number format', 400);
                }
                if (!validateIFSC($ifscCode)) {
                    jsonError('Invalid IFSC code format', 400);
                }

                $bankDetails = [
                    'accountName' => $accountName,
                    'accountNumber' => $accountNumber,
                    'ifscCode' => $ifscCode
                ];
            }

            $pdo->beginTransaction();
            try {
                // Check user balance
                $userStmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE id = :id FOR UPDATE');
                $userStmt->execute(['id' => $currentUser['id']]);
                $user = $userStmt->fetch();

                if ($user['wallet_balance'] < $amount) {
                    throw new Exception('Insufficient wallet balance');
                }

                // Deduct balance
                $balStmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - :amount WHERE id = :id');
                $balStmt->execute(['amount' => $amount, 'id' => $currentUser['id']]);
                
                $selStmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE id = :id');
                $selStmt->execute(['id' => $currentUser['id']]);
                $balanceAfter = (int)$selStmt->fetchColumn();

                // Create request
                $withdrawalId = generateUUID();
                $insStmt = $pdo->prepare('
                    INSERT INTO withdrawal_requests (id, user_id, amount, bank_details, status, payment_method, verified_details)
                    VALUES (:id, :userId, :amount, :bank, \'pending\', :method, TRUE)
                ');
                $insStmt->execute([
                    'id' => $withdrawalId,
                    'userId' => $currentUser['id'],
                    'amount' => $amount,
                    'bank' => json_encode($bankDetails),
                    'method' => $paymentMethod
                ]);

                recordWalletLedger($currentUser['id'], $amount, 'debit', 'withdrawal_hold', 'withdrawal_requests', $withdrawalId, $balanceAfter, 'Withdrawal request hold');

                $pdo->commit();
                jsonResponse(['success' => true, 'message' => 'Withdrawal request submitted successfully!']);
            } catch (Exception $e) {
                $pdo->rollBack();
                jsonError($e->getMessage(), 400);
            }
        }

        else if ($route === 'referrals/my-code' && $method === 'GET') {
            $stmt = $pdo->prepare('SELECT referral_code as "referralCode" FROM users WHERE id = :id');
            $stmt->execute(['id' => $currentUser['id']]);
            $code = $stmt->fetchColumn();
            jsonResponse(['referralCode' => $code]);
        }

        else if ($route === 'referrals/stats' && $method === 'GET') {
            // Confirmed referrals (have at least one approved deposit)
            $confStmt = $pdo->prepare('
                SELECT COUNT(DISTINCT u.id) 
                FROM users u 
                JOIN deposit_requests d ON u.id = d.user_id 
                WHERE u.referred_by = :userId AND d.status = \'approved\'
            ');
            $confStmt->execute(['userId' => $currentUser['id']]);
            $confirmed = (int)$confStmt->fetchColumn();

            // Total referrals
            $totStmt = $pdo->prepare('SELECT COUNT(id) FROM users WHERE referred_by = :userId');
            $totStmt->execute(['userId' => $currentUser['id']]);
            $total = (int)$totStmt->fetchColumn();

            // Required referrals
            $reqStmt = $pdo->query('SELECT COALESCE(required_referrals, 5) FROM admin_settings LIMIT 1');
            $required = (int)$reqStmt->fetchColumn();

            jsonResponse([
                'totalReferrals' => $total,
                'confirmedReferrals' => $confirmed,
                'requiredReferrals' => $required
            ]);
        }

        else if ($route === 'referrals/my-referrals' && $method === 'GET') {
            // Returns referrals list
            $stmt = $pdo->prepare('
                SELECT u.id, u.email, u.name, u.created_at as "createdAt",
                       EXISTS(SELECT 1 FROM deposit_requests d WHERE d.user_id = u.id AND d.status = \'approved\') as "isConfirmed"
                FROM users u
                WHERE u.referred_by = :userId
                ORDER BY u.created_at DESC
            ');
            $stmt->execute(['userId' => $currentUser['id']]);
            jsonResponse(['referrals' => $stmt->fetchAll()]);
        }

        else if ($route === 'referrals/verify' && $method === 'POST') {
            $code = trim($input['referralCode'] ?? '');
            $stmt = $pdo->prepare('SELECT name FROM users WHERE UPPER(referral_code) = UPPER(:code) AND is_admin = FALSE');
            $stmt->execute(['code' => $code]);
            $referrerName = $stmt->fetchColumn();

            if ($referrerName !== false) {
                jsonResponse(['valid' => true, 'referrerName' => $referrerName ?: 'User']);
            } else {
                jsonResponse(['valid' => false]);
            }
        }

        else if ($route === 'referral-bonus/unclaimed' && $method === 'GET') {
            $stmt = $pdo->prepare('
                SELECT rb.id, rb.bonus_amount as "bonusAmount", rb.deposit_amount as "depositAmount", rb.created_at as "createdAt",
                       u.name as "referredUserName", u.email as "referredUserEmail"
                FROM referral_bonuses rb
                JOIN users u ON rb.referred_user_id = u.id
                WHERE rb.referrer_id = :userId AND rb.is_claimed = FALSE
                ORDER BY rb.created_at DESC
            ');
            $stmt->execute(['userId' => $currentUser['id']]);
            jsonResponse($stmt->fetchAll());
        }

        else if ($route === 'referral-bonus/stats' && $method === 'GET') {
            // Unclaimed Amount
            $unclaimedStmt = $pdo->prepare('SELECT COALESCE(SUM(bonus_amount), 0) FROM referral_bonuses WHERE referrer_id = :userId AND is_claimed = FALSE');
            $unclaimedStmt->execute(['userId' => $currentUser['id']]);
            $unclaimed = (int)$unclaimedStmt->fetchColumn();

            // Total bonus earned (unclaimed + claimed/pending claims)
            $totalStmt = $pdo->prepare('SELECT COALESCE(SUM(bonus_amount), 0) FROM referral_bonuses WHERE referrer_id = :userId');
            $totalStmt->execute(['userId' => $currentUser['id']]);
            $totalEarned = (int)$totalStmt->fetchColumn();

            jsonResponse([
                'unclaimedAmount' => $unclaimed,
                'totalAmount' => $totalEarned
            ]);
        }

        else if ($route === 'referral-bonus/claim-history' && $method === 'GET') {
            $stmt = $pdo->prepare('
                SELECT bcr.id, bcr.amount, bcr.status, bcr.created_at as "createdAt", bcr.processed_at as "processedAt", bcr.rejection_reason as "rejectionReason",
                       u.name as "referredUserName", u.email as "referredUserEmail"
                FROM bonus_claim_requests bcr
                JOIN referral_bonuses rb ON bcr.bonus_id = rb.id
                JOIN users u ON rb.referred_user_id = u.id
                WHERE bcr.user_id = :userId
                ORDER BY bcr.created_at DESC
            ');
            $stmt->execute(['userId' => $currentUser['id']]);
            jsonResponse(['claims' => $stmt->fetchAll()]);
        }

        else if (isset($routeParts[0]) && $routeParts[0] === 'referral-bonus' && isset($routeParts[1]) && $routeParts[1] === 'claim' && isset($routeParts[2])) {
            // POST referral-bonus/claim/<id>
            if ($method !== 'POST') jsonError('Method not allowed', 405);
            $bonusId = $routeParts[2];

            $pdo->beginTransaction();
            try {
                $bonusStmt = $pdo->prepare('SELECT * FROM referral_bonuses WHERE id = :id FOR UPDATE');
                $bonusStmt->execute(['id' => $bonusId]);
                $bonus = $bonusStmt->fetch();

                if (!$bonus || $bonus['referrer_id'] !== $currentUser['id']) throw new Exception('Referral bonus not found');
                if ($bonus['is_claimed']) throw new Exception('Bonus already claimed');

                // Check if already has a pending claim request
                $chkStmt = $pdo->prepare('SELECT id FROM bonus_claim_requests WHERE bonus_id = :id AND status = \'pending\'');
                $chkStmt->execute(['id' => $bonusId]);
                if ($chkStmt->fetch()) {
                    throw new Exception('A claim request for this bonus is already pending');
                }

                // Insert claim request
                $insStmt = $pdo->prepare('
                    INSERT INTO bonus_claim_requests (user_id, bonus_id, amount, status)
                    VALUES (:userId, :bonusId, :amount, \'pending\')
                ');
                $insStmt->execute([
                    'userId' => $currentUser['id'],
                    'bonusId' => $bonusId,
                    'amount' => $bonus['bonus_amount']
                ]);

                $pdo->commit();
                jsonResponse(['success' => true, 'message' => 'Claim request submitted! Pending approval.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                jsonError($e->getMessage(), 400);
            }
        }

        // -------------------------------------------------------------
        // PROTECTED ADMIN ENDPOINTS
        // -------------------------------------------------------------
        else {
            $currentAdmin = requireAdmin();

            if ($route === 'admin/statistics' && $method === 'GET') {
                $depCount = (int)$pdo->query('SELECT COUNT(id) FROM deposit_requests WHERE status = \'pending\'')->fetchColumn();
                $withCount = (int)$pdo->query('SELECT COUNT(id) FROM withdrawal_requests WHERE status = \'pending\'')->fetchColumn();
                $claimCount = (int)$pdo->query('SELECT COUNT(id) FROM bonus_claim_requests WHERE status = \'pending\'')->fetchColumn();

                $totalDeps = (int)$pdo->query('SELECT COALESCE(SUM(amount), 0) FROM deposit_requests WHERE status = \'approved\'')->fetchColumn();
                $totalWiths = (int)$pdo->query('SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests WHERE status = \'completed\'')->fetchColumn();

                jsonResponse([
                    'pendingDepositsCount' => $depCount,
                    'pendingWithdrawalsCount' => $withCount,
                    'pendingClaimsCount' => $claimCount,
                    'totalApprovedDeposits' => $totalDeps,
                    'totalCompletedWithdrawals' => $totalWiths
                ]);
            }

            else if ($route === 'admin/pending-deposits' && $method === 'GET') {
                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = max(1, (int)($_GET['limit'] ?? 10));
                $offset = ($page - 1) * $limit;

                $total = (int)$pdo->query('SELECT COUNT(id) FROM deposit_requests WHERE status = \'pending\'')->fetchColumn();
                $stmt = $pdo->prepare('
                    SELECT dr.id, dr.amount, dr.transaction_id as "transactionId", dr.payment_proof_url as "paymentProofUrl", dr.created_at as "createdAt",
                           u.name as "userName", u.email as "userEmail"
                    FROM deposit_requests dr
                    JOIN users u ON dr.user_id = u.id
                    WHERE dr.status = \'pending\'
                    ORDER BY dr.created_at ASC
                    LIMIT :limit OFFSET :offset
                ');
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();

                jsonResponse([
                    'deposits' => $stmt->fetchAll(),
                    'totalPages' => ceil($total / $limit)
                ]);
            }

            else if ($route === 'admin/pending-withdrawals' && $method === 'GET') {
                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = max(1, (int)($_GET['limit'] ?? 10));
                $offset = ($page - 1) * $limit;

                $total = (int)$pdo->query('SELECT COUNT(id) FROM withdrawal_requests WHERE status = \'pending\'')->fetchColumn();
                $stmt = $pdo->prepare('
                    SELECT wr.id, wr.amount, wr.bank_details as "bankDetails", wr.created_at as "createdAt",
                           u.name as "userName", u.email as "userEmail"
                    FROM withdrawal_requests wr
                    JOIN users u ON wr.user_id = u.id
                    WHERE wr.status = \'pending\'
                    ORDER BY wr.created_at ASC
                    LIMIT :limit OFFSET :offset
                ');
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $withdrawals = $stmt->fetchAll();

                foreach ($withdrawals as &$w) {
                    $w['bankDetails'] = is_string($w['bankDetails']) ? json_decode($w['bankDetails'], true) : $w['bankDetails'];
                }

                jsonResponse([
                    'withdrawals' => $withdrawals,
                    'totalPages' => ceil($total / $limit)
                ]);
            }

            else if ($route === 'referral-bonus/admin/pending' && $method === 'GET') {
                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = max(1, (int)($_GET['limit'] ?? 10));
                $offset = ($page - 1) * $limit;

                $total = (int)$pdo->query('SELECT COUNT(id) FROM bonus_claim_requests WHERE status = \'pending\'')->fetchColumn();
                $stmt = $pdo->prepare('
                    SELECT bcr.id, bcr.amount, bcr.created_at as "createdAt",
                           u.name as "userName", u.email as "userEmail",
                           ref.name as "referredUserName", ref.email as "referredUserEmail"
                    FROM bonus_claim_requests bcr
                    JOIN users u ON bcr.user_id = u.id
                    JOIN referral_bonuses rb ON bcr.bonus_id = rb.id
                    JOIN users ref ON rb.referred_user_id = ref.id
                    WHERE bcr.status = \'pending\'
                    ORDER BY bcr.created_at ASC
                    LIMIT :limit OFFSET :offset
                ');
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();

                jsonResponse([
                    'claims' => $stmt->fetchAll(),
                    'totalPages' => ceil($total / $limit)
                ]);
            }

            else if ($route === 'admin/users' && $method === 'GET') {
                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = max(1, (int)($_GET['limit'] ?? 10));
                $offset = ($page - 1) * $limit;
                $search = $_GET['search'] ?? '';

                $where = 'WHERE is_admin = FALSE AND deleted_at IS NULL';
                $params = [];
                if (!empty($search)) {
                    $where .= ' AND (email LIKE :search OR name LIKE :search OR mobile_number LIKE :search)';
                    $params['search'] = '%' . $search . '%';
                }

                $cntStmt = $pdo->prepare("SELECT COUNT(id) FROM users $where");
                $cntStmt->execute($params);
                $total = (int)$cntStmt->fetchColumn();

                $stmt = $pdo->prepare("
                    SELECT id, email, name, is_active as \"isActive\", wallet_balance as \"walletBalance\", mobile_number as \"mobileNumber\", created_at as \"createdAt\"
                    FROM users
                    $where
                    ORDER BY created_at DESC
                    LIMIT :limit OFFSET :offset
                ");
                foreach ($params as $k => $v) {
                    $stmt->bindValue($k, $v);
                }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();

                jsonResponse([
                    'users' => $stmt->fetchAll(),
                    'totalPages' => ceil($total / $limit)
                ]);
            }

            else if (isset($routeParts[0]) && $routeParts[0] === 'admin' && isset($routeParts[1]) && $routeParts[1] === 'users' && isset($routeParts[2])) {
                $userId = $routeParts[2];

                if (isset($routeParts[3]) && $routeParts[3] === 'transactions' && $method === 'GET') {
                    // User transactions audit
                    $page = max(1, (int)($_GET['page'] ?? 1));
                    $limit = max(1, (int)($_GET['limit'] ?? 10));
                    $offset = ($page - 1) * $limit;

                    $transactions = [];
                    // Deposits
                    $depStmt = $pdo->prepare('SELECT id, amount, status, created_at as "createdAt", \'deposit\' as type FROM deposit_requests WHERE user_id = :userId');
                    $depStmt->execute(['userId' => $userId]);
                    $transactions = array_merge($transactions, $depStmt->fetchAll());
                    
                    // Withdrawals
                    $withStmt = $pdo->prepare('SELECT id, amount, status, created_at as "createdAt", \'withdrawal\' as type FROM withdrawal_requests WHERE user_id = :userId');
                    $withStmt->execute(['userId' => $userId]);
                    $transactions = array_merge($transactions, $withStmt->fetchAll());

                    usort($transactions, function($a, $b) { return strcmp($b['createdAt'], $a['createdAt']); });

                    $total = count($transactions);
                    $sliced = array_slice($transactions, $offset, $limit);

                    jsonResponse([
                        'transactions' => $sliced,
                        'totalPages' => ceil($total / $limit)
                    ]);
                }

                else if (isset($routeParts[3]) && $routeParts[3] === 'status' && $method === 'PATCH') {
                    // Toggle User Status (Suspend / Active)
                    $isActive = (bool)($input['isActive'] ?? true);
                    $stmt = $pdo->prepare('UPDATE users SET is_active = :isActive WHERE id = :id');
                    $stmt->execute(['isActive' => $isActive ? 'TRUE' : 'FALSE', 'id' => $userId]);
                    recordAdminAudit($currentAdmin['id'], $isActive ? 'user_activate' : 'user_suspend', 'users', $userId);
                    jsonResponse(['success' => true]);
                }

                else if ($method === 'DELETE') {
                    // Soft-delete user account to preserve financial history.
                    $stmt = $pdo->prepare('UPDATE users SET is_active = FALSE, deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND is_admin = FALSE');
                    $stmt->execute(['id' => $userId]);
                    recordAdminAudit($currentAdmin['id'], 'user_soft_delete', 'users', $userId);
                    jsonResponse(['success' => true]);
                }
            }

            else if ($route === 'products/admin/all' && $method === 'GET') {
                $stmt = $pdo->query('SELECT id, name, image_url as "imageUrl", price, duration_days as "durationDays", daily_reward_percent as "dailyRewardPercent", ad_watch_seconds as "adWatchSeconds", is_active as "isActive" FROM products WHERE deleted_at IS NULL ORDER BY created_at DESC');
                jsonResponse(['products' => $stmt->fetchAll()]);
            }

            else if ($route === 'products/admin/create' && $method === 'POST') {
                $name = trim($_POST['name'] ?? '');
                $price = (int)($_POST['price'] ?? 0);
                $duration = (int)($_POST['durationDays'] ?? 0);
                $reward = (float)($_POST['dailyRewardPercent'] ?? 0);
                $seconds = (int)($_POST['adWatchSeconds'] ?? 120);

                if (!$name || $price <= 0 || $duration <= 0 || $reward <= 0) {
                    jsonError('Invalid product parameters', 400);
                }
                if ($seconds < 10 || $seconds > 3600) {
                    jsonError('Ad watch time must be between 10 seconds and 1 hour', 400);
                }

                $uploadRes = handleUploadedFile('productImage');
                if (isset($uploadRes['error'])) {
                    jsonError($uploadRes['error'], 400);
                }

                $newProductId = generateUUID();
                $stmt = $pdo->prepare('
                    INSERT INTO products (id, name, image_url, price, duration_days, daily_reward_percent, ad_watch_seconds, is_active)
                    VALUES (:id, :name, :imageUrl, :price, :duration, :reward, :seconds, TRUE)
                ');
                $stmt->execute([
                    'id' => $newProductId,
                    'name' => $name,
                    'imageUrl' => $uploadRes['path'],
                    'price' => $price,
                    'duration' => $duration,
                    'reward' => $reward,
                    'seconds' => $seconds
                ]);

                recordAdminAudit($currentAdmin['id'], 'product_create', 'products', $newProductId, [
                    'name' => $name,
                    'price' => $price,
                    'durationDays' => $duration
                ]);
                jsonResponse(['success' => true, 'productId' => $newProductId]);
            }

            else if (isset($routeParts[0]) && $routeParts[0] === 'products' && isset($routeParts[1]) && $routeParts[1] === 'admin' && isset($routeParts[2])) {
                $productId = $routeParts[2];

                if (isset($routeParts[3]) && $routeParts[3] === 'ad-links') {
                    if ($method === 'GET') {
                        $stmt = $pdo->prepare('SELECT day_number as "dayNumber", video_url as "videoUrl" FROM product_ad_links WHERE product_id = :id ORDER BY day_number ASC');
                        $stmt->execute(['id' => $productId]);
                        jsonResponse(['links' => $stmt->fetchAll()]);
                    } 
                    else if ($method === 'PUT') {
                        $links = $input['links'] ?? [];
                        
                        $pdo->beginTransaction();
                        try {
                            $pdo->prepare('DELETE FROM product_ad_links WHERE product_id = :id')
                                ->execute(['id' => $productId]);

                            $ins = $pdo->prepare('INSERT INTO product_ad_links (product_id, day_number, video_url) VALUES (:prodId, :day, :url)');
                            foreach ($links as $lnk) {
                                $videoUrl = trim($lnk['videoUrl'] ?? '');
                                if ($videoUrl !== '' && !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                                    throw new Exception('Invalid ad URL provided');
                                }
                                $ins->execute([
                                    'prodId' => $productId,
                                    'day' => (int)$lnk['dayNumber'],
                                    'url' => $videoUrl
                                ]);
                            }
                            recordAdminAudit($currentAdmin['id'], 'product_ad_schedule_update', 'products', $productId, ['linksCount' => count($links)]);
                            $pdo->commit();
                            jsonResponse(['success' => true]);
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            jsonError($e->getMessage(), 400);
                        }
                    }
                } 
                else if ($method === 'PUT') {
                    // Edit product details
                    $name = trim($input['name'] ?? '');
                    $price = (int)($input['price'] ?? 0);
                    $duration = (int)($input['durationDays'] ?? 0);
                    $reward = (float)($input['dailyRewardPercent'] ?? 0);
                    $seconds = (int)($input['adWatchSeconds'] ?? 120);

                    if (!$name || $price <= 0 || $duration <= 0 || $reward <= 0) {
                        jsonError('Invalid product parameters', 400);
                    }
                    if ($seconds < 10 || $seconds > 3600) {
                        jsonError('Ad watch time must be between 10 seconds and 1 hour', 400);
                    }

                    $stmt = $pdo->prepare('
                        UPDATE products 
                        SET name = :name, price = :price, duration_days = :duration, daily_reward_percent = :reward, ad_watch_seconds = :seconds
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        'name' => $name,
                        'price' => $price,
                        'duration' => $duration,
                        'reward' => $reward,
                        'seconds' => $seconds,
                        'id' => $productId
                    ]);
                    recordAdminAudit($currentAdmin['id'], 'product_update', 'products', $productId);
                    jsonResponse(['success' => true]);
                } 
                else if ($method === 'DELETE') {
                    $stmt = $pdo->prepare('UPDATE products SET is_active = FALSE, deleted_at = NOW(), deleted_by = :adminId WHERE id = :id');
                    $stmt->execute(['adminId' => $currentAdmin['id'], 'id' => $productId]);
                    recordAdminAudit($currentAdmin['id'], 'product_soft_delete', 'products', $productId);
                    jsonResponse(['success' => true]);
                }
            }

            else if ($route === 'products/admin/daily-ad' && $method === 'POST') {
                $url = trim($input['videoUrl'] ?? '');
                if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                    jsonError('Invalid global ad URL', 400);
                }
                $stmt = $pdo->prepare('INSERT INTO daily_ad (video_url, updated_by) VALUES (:url, :adminId)');
                $stmt->execute(['url' => $url, 'adminId' => $currentAdmin['id']]);
                recordAdminAudit($currentAdmin['id'], 'global_ad_update', 'daily_ad', null, ['videoUrl' => $url]);
                jsonResponse(['success' => true]);
            }

            else if ($route === 'admin/upi-id' && $method === 'PUT') {
                $upi = trim($input['upiId'] ?? '');
                if ($upi && !validateUPI($upi)) {
                    jsonError('Invalid UPI ID format. Expected user@bank', 400);
                }
                $stmt = $pdo->prepare('UPDATE admin_settings SET upi_id = :upi, updated_at = NOW()');
                $stmt->execute(['upi' => $upi]);
                recordAdminAudit($currentAdmin['id'], 'primary_upi_update', 'admin_settings', null, ['upiId' => $upi]);
                jsonResponse(['success' => true]);
            }

            else if ($route === 'admin/qr-code' && $method === 'POST') {
                $uploadRes = handleUploadedFile('qrCode');
                if (isset($uploadRes['error'])) {
                    jsonError($uploadRes['error'], 400);
                }

                $stmt = $pdo->prepare('UPDATE admin_settings SET qr_code_url = :qr, updated_at = NOW()');
                $stmt->execute(['qr' => $uploadRes['path']]);
                recordAdminAudit($currentAdmin['id'], 'primary_qr_update', 'admin_settings', null, ['qrCodeUrl' => $uploadRes['path']]);
                jsonResponse(['success' => true]);
            }

            else if ($route === 'admin/payment-methods' && $method === 'GET') {
                $stmt = $pdo->query('SELECT id, label, qr_code_url as "qrCodeUrl", upi_id as "upiId", is_active as "isActive", sort_order as "sortOrder" FROM payment_methods ORDER BY sort_order ASC');
                jsonResponse(['methods' => $stmt->fetchAll()]);
            }

            else if (isset($routeParts[0]) && $routeParts[0] === 'admin' && isset($routeParts[1]) && $routeParts[1] === 'payment-methods' && isset($routeParts[2])) {
                // POST/PUT admin/payment-methods/<id>. POST is preferred for multipart forms in PHP.
                if (!in_array($method, ['POST', 'PUT'], true)) jsonError('Method not allowed', 405);
                $pmId = $routeParts[2];

                $upiId = trim($_POST['upiId'] ?? '');
                if ($upiId && !validateUPI($upiId)) {
                    jsonError('Invalid UPI ID format. Expected user@bank', 400);
                }
                $uploadRes = null;
                if (isset($_FILES['qrImage']) && $_FILES['qrImage']['error'] === UPLOAD_ERR_OK) {
                    $uploadRes = handleUploadedFile('qrImage');
                    if (isset($uploadRes['error'])) {
                        jsonError($uploadRes['error'], 400);
                    }
                }

                if ($uploadRes) {
                    $stmt = $pdo->prepare('UPDATE payment_methods SET upi_id = :upi, qr_code_url = :qr, updated_at = NOW() WHERE id = :id');
                    $stmt->execute(['upi' => $upiId ?: null, 'qr' => $uploadRes['path'], 'id' => $pmId]);
                } else {
                    $stmt = $pdo->prepare('UPDATE payment_methods SET upi_id = :upi, updated_at = NOW() WHERE id = :id');
                    $stmt->execute(['upi' => $upiId ?: null, 'id' => $pmId]);
                }
                recordAdminAudit($currentAdmin['id'], 'payment_method_update', 'payment_methods', $pmId, ['upiId' => $upiId ?: null, 'qrUpdated' => (bool)$uploadRes]);
                jsonResponse(['success' => true]);
            }

            else if (isset($routeParts[0]) && $routeParts[0] === 'admin' && isset($routeParts[1]) && $routeParts[1] === 'deposits' && isset($routeParts[2]) && isset($routeParts[3])) {
                $depositId = $routeParts[2];
                $action = $routeParts[3];

                if ($action === 'approve' && $method === 'POST') {
                    $pdo->beginTransaction();
                    try {
                        // Get deposit request
                        $depStmt = $pdo->prepare('SELECT * FROM deposit_requests WHERE id = :id FOR UPDATE');
                        $depStmt->execute(['id' => $depositId]);
                        $deposit = $depStmt->fetch();

                        if (!$deposit) throw new Exception('Deposit request not found');
                        if ($deposit['status'] !== 'pending') throw new Exception('Deposit request already processed');

                        // Get user
                        $userStmt = $pdo->prepare('SELECT id, wallet_balance, referred_by FROM users WHERE id = :userId FOR UPDATE');
                        $userStmt->execute(['userId' => $deposit['user_id']]);
                        $user = $userStmt->fetch();

                        if (!$user) throw new Exception('User not found');

                        // Credit user balance
                        $newBalance = $user['wallet_balance'] + $deposit['amount'];
                        $pdo->prepare('UPDATE users SET wallet_balance = :bal WHERE id = :id')
                            ->execute(['bal' => $newBalance, 'id' => $user['id']]);

                        // Update deposit request status FIRST (before any non-critical inserts)
                        $pdo->prepare('UPDATE deposit_requests SET status = \'approved\', processed_at = NOW(), processed_by = :adminId WHERE id = :id')
                            ->execute(['adminId' => $currentAdmin['id'], 'id' => $depositId]);

                        // Generate referral bonus if referred
                        if ($user['referred_by']) {
                            // 5% bonus
                            $bonusAmount = (int)floor($deposit['amount'] * 0.05);
                            if ($bonusAmount > 0) {
                                $bonusId = generateUUID();
                                $insBonus = $pdo->prepare('
                                    INSERT INTO referral_bonuses (id, referrer_id, referred_user_id, deposit_id, deposit_amount, bonus_amount, is_claimed)
                                    VALUES (:id, :referrerId, :referredId, :depId, :depAmt, :bonusAmt, FALSE)
                                ');
                                $insBonus->execute([
                                    'id' => $bonusId,
                                    'referrerId' => $user['referred_by'],
                                    'referredId' => $user['id'],
                                    'depId' => $depositId,
                                    'depAmt' => $deposit['amount'],
                                    'bonusAmt' => $bonusAmount
                                ]);
                            }
                        }

                        // Inline wallet ledger insert
                        $ledgerId = generateUUID();
                        $pdo->prepare('
                            INSERT INTO wallet_ledger (id, user_id, amount, direction, entry_type, reference_table, reference_id, balance_after, note, created_by)
                            VALUES (:id, :userId, :amount, :direction, :entryType, :referenceTable, :referenceId, :balanceAfter, :note, :createdBy)
                        ')->execute([
                            'id' => $ledgerId,
                            'userId' => $user['id'],
                            'amount' => (int)$deposit['amount'],
                            'direction' => 'credit',
                            'entryType' => 'deposit_approved',
                            'referenceTable' => 'deposit_requests',
                            'referenceId' => $depositId,
                            'balanceAfter' => $newBalance,
                            'note' => 'Deposit approved by admin',
                            'createdBy' => $currentAdmin['id']
                        ]);

                        // Inline admin audit insert
                        $auditId = generateUUID();
                        $pdo->prepare('
                            INSERT INTO admin_audit_logs (id, admin_id, action, target_table, target_id, details)
                            VALUES (:id, :adminId, :action, :targetTable, :targetId, :details)
                        ')->execute([
                            'id' => $auditId,
                            'adminId' => $currentAdmin['id'],
                            'action' => 'deposit_approve',
                            'targetTable' => 'deposit_requests',
                            'targetId' => $depositId,
                            'details' => json_encode(['amount' => $deposit['amount'], 'userId' => $deposit['user_id']])
                        ]);

                        $pdo->commit();
                        jsonResponse(['success' => true]);
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        error_log('Deposit approval failed: ' . $e->getMessage() . ' | depositId=' . $depositId);
                        jsonError($e->getMessage(), 400);
                    }
                } 
                else if ($action === 'reject' && $method === 'POST') {
                    $reason = trim($input['reason'] ?? '');
                    $stmt = $pdo->prepare('
                        UPDATE deposit_requests 
                        SET status = \'rejected\', processed_at = NOW(), processed_by = :adminId, rejection_reason = :reason 
                        WHERE id = :id AND status = \'pending\'
                    ');
                    $stmt->execute(['adminId' => $currentAdmin['id'], 'reason' => $reason ?: null, 'id' => $depositId]);
                    recordAdminAudit($currentAdmin['id'], 'deposit_reject', 'deposit_requests', $depositId, ['reason' => $reason ?: null]);
                    jsonResponse(['success' => true]);
                }
            }

            else if (isset($routeParts[0]) && $routeParts[0] === 'admin' && isset($routeParts[1]) && $routeParts[1] === 'withdrawals' && isset($routeParts[2]) && isset($routeParts[3])) {
                $withdrawalId = $routeParts[2];
                $action = $routeParts[3];

                if ($action === 'confirm' && $method === 'POST') {
                    $stmt = $pdo->prepare('
                        UPDATE withdrawal_requests 
                        SET status = \'completed\', processed_at = NOW(), processed_by = :adminId 
                        WHERE id = :id AND status = \'pending\'
                    ');
                    $stmt->execute(['adminId' => $currentAdmin['id'], 'id' => $withdrawalId]);
                    recordAdminAudit($currentAdmin['id'], 'withdrawal_confirm', 'withdrawal_requests', $withdrawalId);
                    jsonResponse(['success' => true]);
                } 
                else if ($action === 'reject' && $method === 'POST') {
                    $reason = trim($input['reason'] ?? '');
                    
                    $pdo->beginTransaction();
                    try {
                        $withStmt = $pdo->prepare('SELECT * FROM withdrawal_requests WHERE id = :id FOR UPDATE');
                        $withStmt->execute(['id' => $withdrawalId]);
                        $withdrawal = $withStmt->fetch();

                        if (!$withdrawal) throw new Exception('Withdrawal request not found');
                        if ($withdrawal['status'] !== 'pending') throw new Exception('Withdrawal already processed');

                        // Refund money to user balance
                        $balStmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + :amount WHERE id = :id');
                        $balStmt->execute(['amount' => $withdrawal['amount'], 'id' => $withdrawal['user_id']]);
                        
                        $selStmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE id = :id');
                        $selStmt->execute(['id' => $withdrawal['user_id']]);
                        $balanceAfter = (int)$selStmt->fetchColumn();
                        recordWalletLedger($withdrawal['user_id'], $withdrawal['amount'], 'credit', 'withdrawal_rejected_refund', 'withdrawal_requests', $withdrawalId, $balanceAfter, 'Withdrawal request rejected and hold refunded', $currentAdmin['id']);

                        // Update status
                        $pdo->prepare('
                            UPDATE withdrawal_requests 
                            SET status = \'rejected\', processed_at = NOW(), processed_by = :adminId, rejection_reason = :reason 
                            WHERE id = :id
                        ')->execute(['adminId' => $currentAdmin['id'], 'reason' => $reason ?: null, 'id' => $withdrawalId]);

                        $pdo->commit();
                        recordAdminAudit($currentAdmin['id'], 'withdrawal_reject', 'withdrawal_requests', $withdrawalId, ['reason' => $reason ?: null]);
                        jsonResponse(['success' => true]);
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        jsonError($e->getMessage(), 400);
                    }
                }
            }

            else if (isset($routeParts[0]) && $routeParts[0] === 'referral-bonus' && isset($routeParts[1]) && $routeParts[1] === 'admin' && isset($routeParts[2]) && $routeParts[2] === 'claims' && isset($routeParts[3]) && isset($routeParts[4])) {
                // Route: referral-bonus/admin/claims/<id>/approve or referral-bonus/admin/claims/<id>/reject
                // parts: [0 => referral-bonus, 1 => admin, 2 => claims, 3 => id, 4 => action]
                $claimId = $routeParts[3];
                $action = $routeParts[4];

                if ($action === 'approve' && $method === 'POST') {
                    $pdo->beginTransaction();
                    try {
                        $claimStmt = $pdo->prepare('SELECT * FROM bonus_claim_requests WHERE id = :id FOR UPDATE');
                        $claimStmt->execute(['id' => $claimId]);
                        $claim = $claimStmt->fetch();

                        if (!$claim) throw new Exception('Claim request not found');
                        if ($claim['status'] !== 'pending') throw new Exception('Claim already processed');

                        // Credit referrer balance
                        $balStmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + :amount WHERE id = :id');
                        $balStmt->execute(['amount' => $claim['amount'], 'id' => $claim['user_id']]);
                        
                        $selStmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE id = :id');
                        $selStmt->execute(['id' => $claim['user_id']]);
                        $balanceAfter = (int)$selStmt->fetchColumn();
                        recordWalletLedger($claim['user_id'], $claim['amount'], 'credit', 'referral_bonus_approved', 'bonus_claim_requests', $claimId, $balanceAfter, 'Referral bonus approved by admin', $currentAdmin['id']);

                        // Mark bonus as claimed
                        $pdo->prepare('UPDATE referral_bonuses SET is_claimed = TRUE WHERE id = :id')
                            ->execute(['id' => $claim['bonus_id']]);

                        // Update status
                        $pdo->prepare('
                            UPDATE bonus_claim_requests 
                            SET status = \'approved\', processed_at = NOW(), processed_by = :adminId 
                            WHERE id = :id
                        ')->execute(['adminId' => $currentAdmin['id'], 'id' => $claimId]);

                        $pdo->commit();
                        recordAdminAudit($currentAdmin['id'], 'referral_claim_approve', 'bonus_claim_requests', $claimId, ['amount' => $claim['amount'], 'userId' => $claim['user_id']]);
                        jsonResponse(['success' => true]);
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        jsonError($e->getMessage(), 400);
                    }
                } 
                else if ($action === 'reject' && $method === 'POST') {
                    $reason = trim($input['reason'] ?? '');
                    $stmt = $pdo->prepare('
                        UPDATE bonus_claim_requests 
                        SET status = \'rejected\', processed_at = NOW(), processed_by = :adminId, rejection_reason = :reason 
                        WHERE id = :id AND status = \'pending\'
                    ');
                    $stmt->execute(['adminId' => $currentAdmin['id'], 'reason' => $reason ?: null, 'id' => $claimId]);
                    recordAdminAudit($currentAdmin['id'], 'referral_claim_reject', 'bonus_claim_requests', $claimId, ['reason' => $reason ?: null]);
                    jsonResponse(['success' => true]);
                }
            }
            
            else if ($route === 'admin/settings' && $method === 'PUT') {
                $requiredRefs = (int)($input['requiredReferrals'] ?? 5);
                if ($requiredRefs < 0 || $requiredRefs > 1000) {
                    jsonError('Required referrals must be between 0 and 1000', 400);
                }
                $stmt = $pdo->prepare('UPDATE admin_settings SET required_referrals = :refs, updated_at = NOW()');
                $stmt->execute(['refs' => $requiredRefs]);
                recordAdminAudit($currentAdmin['id'], 'settings_update', 'admin_settings', null, ['requiredReferrals' => $requiredRefs]);
                jsonResponse(['success' => true]);
            }
            
            else {
                jsonError('API Endpoint not found', 404, 'NOT_FOUND');
            }
        }
    }
} catch (Exception $e) {
    jsonError($e->getMessage(), 500, 'SERVER_ERROR');
}
