<?php
// public/test_ssl.php - Web-based SSL connection diagnostic
require_once dirname(__DIR__) . '/src/db.php';

header('Content-Type: text/plain');
echo "=== SSL CONNECTION DIAGNOSTIC (WEB) ===\n\n";

$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    die("DATABASE_URL not found.\n");
}

// Clean quotes if present
$dbUrl = preg_replace('/^["\']|["\']$/', '', trim($dbUrl));

$parsedUrl = parse_url($dbUrl);
$host = $parsedUrl['host'] ?? '127.0.0.1';
$port = $parsedUrl['port'] ?? 3306;
$dbName = ltrim($parsedUrl['path'] ?? '', '/');
$user = $parsedUrl['user'] ?? '';
$password = $parsedUrl['pass'] ?? '';

$dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";

$attempts = [
    'Attempt 1: Local ca-cert.pem + verify=false' => [
        1008 => dirname(__DIR__) . '/db/ca-cert.pem',
        1013 => false
    ],
    'Attempt 2: System ca-certificates.crt + verify=false' => [
        1008 => '/etc/ssl/certs/ca-certificates.crt',
        1013 => false
    ],
    'Attempt 3: Local ca-cert.pem + verify=true' => [
        1008 => dirname(__DIR__) . '/db/ca-cert.pem',
        1013 => true
    ],
    'Attempt 4: System ca-certificates.crt + verify=true' => [
        1008 => '/etc/ssl/certs/ca-certificates.crt',
        1013 => true
    ],
    'Attempt 5: NO CA file + verify=false' => [
        1013 => false
    ],
];

foreach ($attempts as $label => $sslOptions) {
    echo "$label...\n";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    foreach ($sslOptions as $key => $val) {
        $options[$key] = $val;
    }
    
    try {
        $pdo = new PDO($dsn, $user, $password, $options);
        echo "  --> SUCCESS!\n";
        $stmt = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'");
        $row = $stmt->fetch();
        echo "  Cipher: " . ($row['Value'] ?? 'None') . "\n\n";
    } catch (PDOException $e) {
        echo "  --> FAILED: " . $e->getMessage() . "\n\n";
    }
}
