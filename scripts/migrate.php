<?php
// migrate.php - Idempotent database migration runner for AeroPay

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Access Denied: This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/src/db.php';

$migrationDir = dirname(__DIR__) . '/db/migrations';
if (!is_dir($migrationDir)) {
    echo "No migrations directory found.\n";
    exit(0);
}

$files = glob($migrationDir . '/*.sql');
sort($files);

if (!$files) {
    echo "No SQL migration files found.\n";
    exit(0);
}

try {
    foreach ($files as $file) {
        echo "Running " . basename($file) . "...\n";
        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            echo "  skipped empty file\n";
            continue;
        }

        // Split by semicolon to run statements individually
        $queries = explode(';', $sql);
        foreach ($queries as $query) {
            $query = trim($query);
            if ($query === '') {
                continue;
            }
            try {
                $pdo->exec($query);
            } catch (PDOException $e) {
                $errorCode = $e->errorInfo[1] ?? 0;
                // Ignore safe errors:
                // 1050: Table already exists
                // 1060: Duplicate column name
                // 1061: Duplicate key name / index already exists
                // 1062: Duplicate entry (seed data)
                if (in_array($errorCode, [1050, 1060, 1061, 1062])) {
                    continue;
                }
                throw $e;
            }
        }
        echo "  ok\n";
    }
    echo "All migrations completed.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
