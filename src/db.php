<?php
// db.php - Database connection via PDO (MySQL / PostgreSQL / SQLite)

// Load env variables from .env.local
function loadEnv($path = null) {
    if ($path === null) {
        $path = dirname(__DIR__) . '/.env.local';
    }
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
            // Remove optional surrounding quotes
            $value = preg_replace('/^["\']|["\']$/', '', $value);
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Load env variables
loadEnv();

$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    die("Error: DATABASE_URL not found in .env.local");
}

// Clean optional surrounding quotes if pasted literally in hosting panel
$dbUrl = preg_replace('/^["\']|["\']$/', '', trim($dbUrl));

// Parse Database URL
$parsedUrl = parse_url($dbUrl);
if (!$parsedUrl || !isset($parsedUrl['host'])) {
    die("Error: Invalid DATABASE_URL");
}

$scheme = strtolower($parsedUrl['scheme'] ?? 'mysql');
$host = $parsedUrl['host'] ?? '127.0.0.1';
$port = $parsedUrl['port'] ?? null;
$dbName = ltrim($parsedUrl['path'] ?? '', '/');
$user = $parsedUrl['user'] ?? '';
$password = $parsedUrl['pass'] ?? '';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

if ($scheme === 'mysql' || $scheme === 'mysqls') {
    $port = $port ?? 3306;
    $dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";

    // Check if SSL is required (either by hostname detection or url query params like ?sslmode=require)
    parse_str($parsedUrl['query'] ?? '', $queryOptions);
    $sslRequired = isset($queryOptions['sslmode']) || 
                   isset($queryOptions['ssl']) || 
                   (strpos($host, 'tidbcloud.com') !== false) || 
                   (strpos($host, 'aivencloud.com') !== false);

    if ($sslRequired) {
        $caPath = dirname(__DIR__) . '/db/ca-cert.pem';
        if (!file_exists($caPath)) {
            // Download standard Let's Encrypt / ISRG Root X1 cert used by modern cloud databases
            $pemContent = @file_get_contents('https://letsencrypt.org/certs/isrgrootx1.pem');
            if ($pemContent) {
                @file_put_contents($caPath, $pemContent);
            }
        }

        // Dynamically resolve SSL constants for PHP 8.2 through 8.5+
        // PHP 8.4+ moved MySQL constants to Pdo\Mysql subclass; PHP 8.5 removed old PDO:: constants
        $sslCaKey = null;
        $sslVerifyKey = null;

        if (class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_SSL_CA')) {
            // PHP 8.4+ / 8.5+ with new Pdo\Mysql subclass
            $sslCaKey = constant('Pdo\\Mysql::ATTR_SSL_CA');
            $sslVerifyKey = defined('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')
                ? constant('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')
                : null;
        } elseif (defined('PDO::MYSQL_ATTR_SSL_CA')) {
            // PHP 8.2 / 8.3 legacy constants
            $sslCaKey = PDO::MYSQL_ATTR_SSL_CA;
            $sslVerifyKey = defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')
                ? PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT
                : null;
        } else {
            // Ultimate fallback: use raw PDO MySQL driver attribute integer values
            $sslCaKey = 1009;
            $sslVerifyKey = 1014;
        }

        if ($sslCaKey !== null && file_exists($caPath)) {
            $options[$sslCaKey] = $caPath;
        }
        if ($sslVerifyKey !== null) {
            $options[$sslVerifyKey] = false;
        }
    }
} else if ($scheme === 'postgres' || $scheme === 'postgresql' || $scheme === 'pgsql') {
    $port = $port ?? 5432;
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbName";
    
    // Enable SSL mode
    parse_str($parsedUrl['query'] ?? '', $queryOptions);
    $sslMode = $queryOptions['sslmode'] ?? 'prefer';
    $dsn .= ";sslmode=$sslMode";
} else if ($scheme === 'sqlite') {
    $dbPath = $parsedUrl['path'] ?? '';
    // Resolve relative path against project root
    if ($dbPath !== ':memory:' && !preg_match('/^([a-zA-Z]:\\\\|\\/)/', $dbPath)) {
        $dbPath = dirname(__DIR__) . '/' . $dbPath;
    }
    $dsn = "sqlite:$dbPath";
} else {
    die("Error: Unsupported database scheme '$scheme'");
}

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
