<?php
// public/check_logo.php - Diagnostic script to check logo file existence and permissions
header('Content-Type: text/plain');
echo "=== LOGO FILE DIAGNOSTIC ===\n\n";

$logoPath = dirname(__DIR__) . '/public/icons/aeropay-logo.png';
echo "Absolute Path: $logoPath\n";
echo "File exists: " . (file_exists($logoPath) ? "YES" : "NO") . "\n";
echo "File is readable: " . (is_readable($logoPath) ? "YES" : "NO") . "\n";
echo "File size: " . filesize($logoPath) . " bytes\n";
echo "File permissions: " . substr(sprintf('%o', fileperms($logoPath)), -4) . "\n\n";

echo "Apache Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
