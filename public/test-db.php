<?php

/**
 * Database Connection Test
 * 
 * Access this file via browser to verify database connectivity
 * URL: https://tools.veerl.es/apps/networkingemailtracker/test-db.php
 * 
 * DELETE THIS FILE after successful testing for security!
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
    echo "✅ Environment file loaded<br><br>";
} catch (Exception $e) {
    die("❌ Failed to load .env file: " . $e->getMessage());
}

// Display configuration (hide password)
echo "<h2>Database Configuration</h2>";
echo "<pre>";
echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "\n";
echo "DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NOT SET') . "\n";
echo "DB_USER: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "\n";
echo "DB_PASS: " . (isset($_ENV['DB_PASS']) && !empty($_ENV['DB_PASS']) ? '****** (set)' : 'NOT SET') . "\n";
echo "</pre><br>";

// Attempt database connection
echo "<h2>Connection Test</h2>";

try {
    $config = require __DIR__ . '/../config/database.php';
    
    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=%s",
        $config['host'],
        $config['name'],
        $config['charset']
    );
    
    $pdo = new PDO(
        $dsn,
        $config['user'],
        $config['pass'],
        $config['options']
    );
    
    echo "✅ <strong>Successfully connected to database!</strong><br><br>";
    
    // Get database name
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo "📊 Current database: <strong>{$dbName}</strong><br><br>";
    
    // List tables
    echo "<h2>Database Tables</h2>";
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "⚠️ No tables found. Run migrations: <code>php migrations/migrate.php</code><br>";
    } else {
        echo "<ul>";
        foreach ($tables as $table) {
            // Get row count
            $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            echo "<li><strong>{$table}</strong> - {$count} rows</li>";
        }
        echo "</ul>";
    }
    
    echo "<br><h2>Server Information</h2>";
    echo "<pre>";
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "MySQL Version: {$version}\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "PDO Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
    echo "</pre>";
    
    echo "<br><hr>";
    echo "<p style='color: red;'><strong>⚠️ IMPORTANT: Delete this file (test-db.php) after testing for security!</strong></p>";
    
} catch (PDOException $e) {
    echo "❌ <strong>Database connection failed!</strong><br><br>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br><br>";
    
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Verify DB_HOST is correct (try 'localhost', '127.0.0.1', or your hosting's MySQL hostname)</li>";
    echo "<li>Check that database name, username, and password are correct</li>";
    echo "<li>Ensure MySQL user has permissions for this database</li>";
    echo "<li>Check if MySQL is running</li>";
    echo "<li>Contact your hosting provider for MySQL connection details</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
}
