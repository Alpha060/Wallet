<?php
// migrate.php - Idempotent database migration runner for AeroPay

require_once __DIR__ . '/db.php';

$migrationDir = __DIR__ . '/migrations';
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
        $pdo->exec($sql);
        echo "  ok\n";
    }
    echo "All migrations completed.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
