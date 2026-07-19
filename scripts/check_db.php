<?php
// check_db.php - Utility script to verify database tables schema

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access Denied: This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/src/db.php';

try {
    echo "=== DATABASE SCHEMA STATUS ===\n\n";

    $tables = [
        'users',
        'admin_settings',
        'payment_methods',
        'products',
        'product_ad_links',
        'daily_ad',
        'ad_watch_log',
        'user_investments',
        'deposit_requests',
        'withdrawal_requests',
        'referral_bonuses',
        'bonus_claim_requests',
        'wallet_ledger',
        'admin_audit_logs'
    ];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table
            ORDER BY ordinal_position
        ");
        $stmt->execute(['table' => $table]);
        $rows = $stmt->fetchAll();
        
        if (count($rows) > 0) {
            echo "Table '$table' columns:\n";
            foreach ($rows as $row) {
                echo "  - " . str_pad($row['column_name'], 28) . " (" . $row['data_type'] . ")\n";
            }
            echo "\n";
        } else {
            echo "Table '$table' does not exist in schema.\n\n";
        }
    }
} catch (Exception $e) {
    echo "Database Check Failed: " . $e->getMessage() . "\n";
}
