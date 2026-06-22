<?php
// reset_admin.php - Utility script to reset system admin password
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

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
            $insStmt = $pdo->prepare('
                INSERT INTO users (email, password_hash, name, is_admin, is_active)
                VALUES (:email, :hash, \'System Administrator\', TRUE, TRUE)
            ');
            $insStmt->execute(['email' => $email, 'hash' => $hash]);
            echo "Admin user '$email' created successfully with password '$password'!\n";
        } else {
            echo "Admin user exists but password was already set to that hash, or update failed.\n";
        }
    }
} catch (Exception $e) {
    echo "Reset admin script failed: " . $e->getMessage() . "\n";
}
