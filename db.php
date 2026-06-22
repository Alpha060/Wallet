<?php
// db.php - Database connection via PDO for PostgreSQL

// Load env variables from .env.local
function loadEnv($path = __DIR__ . '/.env.local') {
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

// Parse PostgreSQL URL
$parsedUrl = parse_url($dbUrl);
if (!$parsedUrl || !isset($parsedUrl['host'])) {
    die("Error: Invalid DATABASE_URL");
}

$host = $parsedUrl['host'];
$port = $parsedUrl['port'] ?? 5432;
$dbName = ltrim($parsedUrl['path'] ?? '/postgres', '/');
$user = $parsedUrl['user'] ?? '';
$password = $parsedUrl['pass'] ?? '';

// Build DSN with sslmode=require for Supabase
$dsn = "pgsql:host=$host;port=$port;dbname=$dbName;sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
