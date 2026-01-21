<?php
/**
 * Web-based Migration Runner
 * Access this file once via browser to run migrations on production
 * DELETE THIS FILE after running for security
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Security: Simple confirmation to prevent accidental access
$confirmed = $_GET['confirm'] ?? '';

if ($confirmed !== 'yes' && !isset($_POST['run_migration'])) {
    echo '<div style="font-family: Arial; max-width: 600px; margin: 100px auto; padding: 30px; border: 3px solid #ffc107; border-radius: 8px; background: #fff3cd;">';
    echo '<h2>⚠️ Database Migration Tool</h2>';
    echo '<p>This will modify your database. Make sure you have a backup!</p>';
    echo '<p><a href="?confirm=yes" style="background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">Yes, I want to run migrations</a></p>';
    echo '<p><strong>IMPORTANT:</strong> Delete this file after running migrations!</p>';
    echo '</div>';
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .migration-item {
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔧 Database Migration Runner</h1>
        
        <div class="warning">
            ⚠️ <strong>Important:</strong> DELETE THIS FILE after running migrations for security!
        </div>

<?php

if (isset($_POST['run_migration'])) {
    $migrationFile = $_POST['migration_file'];
    
    echo "<h2>Running Migration...</h2>";
    
    try {
        // Connect to database
        $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
        $dbName = $_ENV['DB_NAME'] ?? '';
        $dbUser = $_ENV['DB_USER'] ?? 'root';
        $dbPass = $_ENV['DB_PASS'] ?? '';
        
        $db = new PDO(
            "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
            $dbUser,
            $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        echo "<div class='success'>✓ Connected to database: $dbName</div>";
        
        // Read migration file
        $migrationPath = __DIR__ . '/migrations/' . $migrationFile;
        
        if (!file_exists($migrationPath)) {
            throw new Exception("Migration file not found: $migrationFile");
        }
        
        $sql = file_get_contents($migrationPath);
        
        echo "<h3>Migration SQL:</h3>";
        echo "<pre>" . htmlspecialchars($sql) . "</pre>";
        
        // Execute migration
        $db->exec($sql);
        
        echo "<div class='success'>";
        echo "<strong>✓ Migration completed successfully!</strong><br>";
        echo "File: $migrationFile<br>";
        echo "Database: $dbName<br>";
        echo "Time: " . date('Y-m-d H:i:s');
        echo "</div>";
        
        echo "<div class='warning'>";
        echo "<strong>⚠️ IMPORTANT: Delete this migrate.php file now!</strong><br>";
        echo "Run: <code>rm migrate.php</code> or delete via FTP";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "<strong>❌ Migration failed:</strong><br>";
        echo htmlspecialchars($e->getMessage());
        echo "</div>";
    }
    
} else {
    // Show available migrations
    echo "<h2>Available Migrations</h2>";
    
    $migrationsDir = __DIR__ . '/migrations';
    $migrations = glob($migrationsDir . '/*.sql');
    
    if (empty($migrations)) {
        echo "<div class='error'>No migration files found in /migrations directory</div>";
    } else {
        echo "<p>Select a migration to run:</p>";
        
        foreach ($migrations as $migration) {
            $filename = basename($migration);
            $content = file_get_contents($migration);
            
            // Check if migration creates a table
            preg_match('/CREATE TABLE.*?`(\w+)`/i', $content, $matches);
            $tableName = $matches[1] ?? 'Unknown';
            
            echo "<div class='migration-item'>";
            echo "<form method='POST' style='margin: 0;'>";
            echo "<input type='hidden' name='migration_file' value='" . htmlspecialchars($filename) . "'>";
            echo "<strong>" . htmlspecialchars($filename) . "</strong><br>";
            echo "<small>Creates/modifies table: <code>$tableName</code></small><br>";
            echo "<button type='submit' name='run_migration' class='btn' style='margin-top: 10px;'>Run This Migration</button>";
            echo "</form>";
            echo "</div>";
        }
    }
    
    echo "<h2>Current Database Info</h2>";
    echo "<pre>";
    echo "Host: " . ($_ENV['DB_HOST'] ?? 'localhost') . "\n";
    echo "Database: " . ($_ENV['DB_NAME'] ?? 'not set') . "\n";
    echo "User: " . ($_ENV['DB_USER'] ?? 'not set') . "\n";
    echo "</pre>";
    
    echo "<div class='warning'>";
    echo "<strong>Before running:</strong><br>";
    echo "1. Backup your database<br>";
    echo "2. Verify you're running this on the correct environment<br>";
    echo "3. DELETE this file after running migrations";
    echo "</div>";
}

?>

    </div>
</body>
</html>
