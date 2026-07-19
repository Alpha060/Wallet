<?php
// public/test_ssl.php - Web-based SSL connection diagnostic (independent of db.php side effects)
header('Content-Type: text/plain');
echo "=== SSL CONNECTION DIAGNOSTIC (WEB) ===\n\n";

// Manual environment loader helper
function manualLoadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            $value = preg_replace('/^["\']|["\']$/', '', $value);
            if (!getenv($name)) {
                putenv("$name=$value");
            }
        }
    }
}

manualLoadEnv(dirname(__DIR__) . '/.env.local');

$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    die("DATABASE_URL not found.\n");
}

// Clean quotes
$dbUrl = preg_replace('/^["\']|["\']$/', '', trim($dbUrl));

$parsedUrl = parse_url($dbUrl);
$host = $parsedUrl['host'] ?? '127.0.0.1';
$port = $parsedUrl['port'] ?? 3306;
$dbName = ltrim($parsedUrl['path'] ?? '', '/');
$user = $parsedUrl['user'] ?? '';
$password = $parsedUrl['pass'] ?? '';

$dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";

echo "--- mysqli connection tests for detailed OpenSSL errors ---\n";

// Attempt 6: mysqli with local cert
echo "Attempt 6: mysqli with local ca-cert.pem...\n";
$link = mysqli_init();
if (!$link) {
    echo "  mysqli_init failed\n\n";
} else {
    mysqli_ssl_set($link, NULL, NULL, dirname(__DIR__) . '/db/ca-cert.pem', NULL, NULL);
    // Disable server verify (equivalent to 1013=false)
    mysqli_options($link, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    
    if (@mysqli_real_connect($link, $host, $user, $password, $dbName, $port)) {
        echo "  --> SUCCESS!\n\n";
        mysqli_close($link);
    } else {
        echo "  --> FAILED: " . mysqli_connect_error() . " (Code: " . mysqli_connect_errno() . ")\n\n";
    }
}

// Attempt 7: mysqli with system cert
echo "Attempt 7: mysqli with system ca-certificates.crt...\n";
$link = mysqli_init();
if (!$link) {
    echo "  mysqli_init failed\n\n";
} else {
    mysqli_ssl_set($link, NULL, NULL, '/etc/ssl/certs/ca-certificates.crt', NULL, NULL);
    mysqli_options($link, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    
    if (@mysqli_real_connect($link, $host, $user, $password, $dbName, $port)) {
        echo "  --> SUCCESS!\n\n";
        mysqli_close($link);
    } else {
        echo "  --> FAILED: " . mysqli_connect_error() . " (Code: " . mysqli_connect_errno() . ")\n\n";
    }
}

echo "--- PDO connection tests ---\n";

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
