<?php
/**
 * Error Diagnostic Script
 * Visit this file to see what's causing the 500 error
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnostics</h1>";

// Check if vendor directory exists
echo "<h2>1. Checking Composer Dependencies</h2>";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "✅ vendor/autoload.php exists<br>";
    require_once __DIR__ . '/vendor/autoload.php';
    echo "✅ Autoload successful<br>";
} else {
    echo "❌ vendor/autoload.php missing - run 'composer install'<br>";
    exit;
}

// Check .env file
echo "<h2>2. Checking Environment File</h2>";
if (file_exists(__DIR__ . '/.env')) {
    echo "✅ .env file exists<br>";
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        echo "✅ .env loaded successfully<br>";
        
        // Check required vars
        $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        foreach ($required as $var) {
            if (isset($_ENV[$var]) && !empty($_ENV[$var])) {
                echo "✅ $var is set<br>";
            } else {
                echo "❌ $var is missing or empty<br>";
            }
        }
    } catch (Exception $e) {
        echo "❌ Failed to load .env: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ .env file missing<br>";
}

// Check database connection
echo "<h2>3. Checking Database Connection</h2>";
try {
    $db = new PDO(
        'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . ($_ENV['DB_NAME'] ?? ''),
        $_ENV['DB_USER'] ?? '',
        $_ENV['DB_PASS'] ?? ''
    );
    echo "✅ Database connection successful<br>";
    
    // Check if tables exist
    $tables = ['threads', 'emails', 'monday_sync'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' missing<br>";
        }
    }
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

// Check logs directory
echo "<h2>4. Checking Logs Directory</h2>";
$logsDir = __DIR__ . '/logs';
if (is_dir($logsDir)) {
    echo "✅ logs directory exists<br>";
    if (is_writable($logsDir)) {
        echo "✅ logs directory is writable<br>";
    } else {
        echo "❌ logs directory is NOT writable (permissions issue)<br>";
    }
} else {
    echo "❌ logs directory missing<br>";
}

// Try to load index.php to see specific error
echo "<h2>5. Testing Index Load</h2>";
echo "Attempting to include index.php...<br><pre>";
ob_start();
try {
    include __DIR__ . '/index.php';
    $output = ob_get_clean();
    echo "Index loaded without fatal errors\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "❌ Error loading index.php:\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString();
}
echo "</pre>";

echo "<hr>";
echo "<p><strong>Diagnostic complete.</strong> Check the errors above to identify the issue.</p>";
