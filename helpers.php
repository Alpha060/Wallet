<?php
// helpers.php - Core helper utilities for sessions, CSRF, uploads, and data validation

// Secure session startup
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_path', '/');
        // If running under HTTPS, make session cookies secure
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        session_start();
    }
}

// Response helpers
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError($message, $statusCode = 400, $code = 'BAD_REQUEST') {
    jsonResponse([
        'error' => [
            'code' => $code,
            'message' => $message,
            'timestamp' => date('c')
        ]
    ], $statusCode);
}

// CSRF Protection
function generateCsrfToken() {
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    initSession();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Authentication Guards
function getAuthenticatedUser() {
    initSession();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['email'],
        'name' => $_SESSION['name'] ?? null,
        'isAdmin' => !empty($_SESSION['is_admin'])
    ];
}

function requireAuth() {
    $user = getAuthenticatedUser();
    if (!$user) {
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            jsonError('Missing or invalid authentication', 401, 'UNAUTHORIZED');
        } else {
            header('Location: /login');
            exit;
        }
    }
    
    // Check if user is active
    global $pdo;
    if (!$user['isAdmin'] && isset($pdo)) {
        $stmt = $pdo->prepare('SELECT is_active, deleted_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);
        $dbUser = $stmt->fetch();
        if (!$dbUser || $dbUser['is_active'] === false || !empty($dbUser['deleted_at'])) {
            session_destroy();
            if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
                jsonError('Your account has been deactivated. Please contact admin.', 403, 'ACCOUNT_DEACTIVATED');
            } else {
                header('Location: /login?error=deactivated');
                exit;
            }
        }
    }
    return $user;
}

function requireAdmin() {
    $user = requireAuth();
    if (!$user['isAdmin']) {
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            jsonError('Admin access required', 403, 'FORBIDDEN');
        } else {
            header('Location: /user-dashboard');
            exit;
        }
    }
    return $user;
}

// Password hashing and comparison
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

function comparePassword($password, $hash) {
    return password_verify($password, $hash);
}

// Safe Image Resizing and Optimization using GD
function optimizeUploadedImage($sourcePath, $destinationPath, $maxWidth = 1920, $maxHeight = 1920, $quality = 80) {
    if (!extension_loaded('gd')) {
        // GD is not loaded, just copy the file directly as fallback
        if ($sourcePath !== $destinationPath) {
            copy($sourcePath, $destinationPath);
        }
        return true;
    }

    list($width, $height, $type) = getimagesize($sourcePath);
    if (!$width || !$height) {
        return false;
    }

    // Determine scale ratio
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    if ($ratio < 1) {
        $newWidth = (int)round($width * $ratio);
        $newHeight = (int)round($height * $ratio);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }

    // Load original image
    switch ($type) {
        case IMAGETYPE_JPEG:
            $srcImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $srcImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $srcImage = imagecreatefromgif($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $srcImage = imagecreatefromwebp($sourcePath);
            } else {
                $srcImage = false;
            }
            break;
        default:
            $srcImage = false;
            break;
    }

    if (!$srcImage) {
        if ($sourcePath !== $destinationPath) {
            copy($sourcePath, $destinationPath);
        }
        return true;
    }

    // Create container and resize
    $dstImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG and GIF
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
        $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
        imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Save optimized image (always output to match input type if possible, otherwise JPEG fallback)
    $success = false;
    switch ($type) {
        case IMAGETYPE_PNG:
            $success = imagepng($dstImage, $destinationPath, 6); // PNG compression level 0-9
            break;
        case IMAGETYPE_GIF:
            $success = imagegif($dstImage, $destinationPath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagewebp')) {
                $success = imagewebp($dstImage, $destinationPath, $quality);
            } else {
                $success = imagejpeg($dstImage, $destinationPath, $quality);
            }
            break;
        case IMAGETYPE_JPEG:
        default:
            $success = imagejpeg($dstImage, $destinationPath, $quality);
            break;
    }

    imagedestroy($srcImage);
    imagedestroy($dstImage);

    return $success;
}

// Image upload and validate wrapper
function handleUploadedFile($fileInputName, $targetDir = 'uploads') {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'No file uploaded or upload error occurred'];
    }

    $file = $_FILES[$fileInputName];
    $size = $file['size'];
    $tmpName = $file['tmp_name'];
    $mimetype = mime_content_type($tmpName);

    // Validate size (5MB limit)
    if ($size > 5 * 1024 * 1024) {
        return ['error' => 'File size exceeds 5MB limit'];
    }

    // Validate MIME type
    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mimetype, $allowedMimes)) {
        return ['error' => 'Invalid file format. Only JPEG, PNG, WEBP, and GIF are allowed.'];
    }

    // Check target directory
    $uploadsPath = __DIR__ . '/' . $targetDir;
    if (!file_exists($uploadsPath)) {
        mkdir($uploadsPath, 0755, true);
    }

    // Generate unique safe name
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($ext)) {
        $ext = ($mimetype === 'image/png') ? 'png' : (($mimetype === 'image/gif') ? 'gif' : 'jpg');
    }
    $filename = time() . '-' . bin2hex(random_bytes(8)) . '.' . strtolower($ext);
    $destPath = $uploadsPath . '/' . $filename;

    // Move and optimize
    if (optimizeUploadedImage($tmpName, $destPath)) {
        return ['filename' => $filename, 'path' => '/' . $targetDir . '/' . $filename];
    }

    return ['error' => 'Failed to process and optimize image'];
}

// Convert amount (paise to rupees formatted)
function formatRupees($paise) {
    return '₹' . number_format($paise / 100, 2, '.', ',');
}

// Helper to safely cast or format integers/strings
function validateUPI($upi) {
    return preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9]+$/', $upi);
}

function validateIFSC($ifsc) {
    return preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc);
}

function validateMobileNumber($mobile) {
    return preg_match('/^[6-9][0-9]{9}$/', $mobile);
}

function validateAadharNumber($aadhar) {
    return preg_match('/^[0-9]{12}$/', $aadhar);
}

function validatePAN($pan) {
    return preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', strtoupper($pan));
}

function enforceSessionRateLimit($key, $maxAttempts, $windowSeconds) {
    initSession();
    $now = time();
    $bucketKey = 'rate_limit_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
    $bucket = $_SESSION[$bucketKey] ?? ['count' => 0, 'reset_at' => $now + $windowSeconds];

    if ($now >= ($bucket['reset_at'] ?? 0)) {
        $bucket = ['count' => 0, 'reset_at' => $now + $windowSeconds];
    }

    $bucket['count']++;
    $_SESSION[$bucketKey] = $bucket;

    if ($bucket['count'] > $maxAttempts) {
        $wait = max(1, $bucket['reset_at'] - $now);
        jsonError("Too many attempts. Please wait {$wait} seconds and try again.", 429, 'RATE_LIMITED');
    }
}

function recordWalletLedger($userId, $amount, $direction, $entryType, $referenceTable = null, $referenceId = null, $balanceAfter = null, $note = null, $createdBy = null) {
    global $pdo;
    if (!isset($pdo)) {
        return;
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO wallet_ledger (user_id, amount, direction, entry_type, reference_table, reference_id, balance_after, note, created_by)
            VALUES (:userId, :amount, :direction, :entryType, :referenceTable, :referenceId, :balanceAfter, :note, :createdBy)
        ');
        $stmt->execute([
            'userId' => $userId,
            'amount' => (int)$amount,
            'direction' => $direction,
            'entryType' => $entryType,
            'referenceTable' => $referenceTable,
            'referenceId' => $referenceId,
            'balanceAfter' => $balanceAfter,
            'note' => $note,
            'createdBy' => $createdBy
        ]);
    } catch (Exception $e) {
        error_log('wallet_ledger insert failed: ' . $e->getMessage());
    }
}

function recordAdminAudit($adminId, $action, $targetTable = null, $targetId = null, $details = []) {
    global $pdo;
    if (!isset($pdo)) {
        return;
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO admin_audit_logs (admin_id, action, target_table, target_id, details)
            VALUES (:adminId, :action, :targetTable, :targetId, :details)
        ');
        $stmt->execute([
            'adminId' => $adminId,
            'action' => $action,
            'targetTable' => $targetTable,
            'targetId' => $targetId,
            'details' => json_encode($details)
        ]);
    } catch (Exception $e) {
        error_log('admin_audit_logs insert failed: ' . $e->getMessage());
    }
}
