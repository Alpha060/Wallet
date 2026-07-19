<?php
// reset_admin.php - Utility script to reset system admin password
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access Denied: This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/helpers.php';

try {
    echo "=== RESET ADMIN PASSWORD ===\n";
    
    $email = 'admin@example.com';
    $password = 'admin123';
    $hash = hashPassword($password);

    $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, is_active = TRUE, deleted_at = NULL WHERE email = :email');
    $stmt->execute(['hash' => $hash, 'email' => $email]);

    if ($stmt->rowCount() > 0) {
        echo "Admin password reset successfully to: '$password'\n";
    } else {
        // Admin user might not exist, let's create it
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $checkStmt->execute(['email' => $email]);
        if (!$checkStmt->fetch()) {
            echo "Admin user not found. Creating a new admin user...\n";
            $adminId = generateUUID();
            $insStmt = $pdo->prepare('
                INSERT INTO users (id, email, password_hash, name, is_admin, is_active)
                VALUES (:id, :email, :hash, \'System Administrator\', TRUE, TRUE)
            ');
            $insStmt->execute(['id' => $adminId, 'email' => $email, 'hash' => $hash]);
            echo "Admin user '$email' created successfully with password '$password'!\n";
        } else {
            echo "Admin user exists but password was already set to that hash, or update failed.\n";
        }
    }
} catch (Exception $e) {
    echo "Reset admin script failed: " . $e->getMessage() . "\n";
}
