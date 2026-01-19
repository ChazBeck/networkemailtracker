#!/usr/bin/env php
<?php

/**
 * Database Migration Runner
 * 
 * Executes migration files in order and tracks executed migrations
 * 
 * Usage: php migrations/migrate.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

try {
    // Connect to database
    $config = require __DIR__ . '/../config/database.php';
    $dsn = sprintf(
        "mysql:host=%s;charset=%s",
        $config['host'],
        $config['charset']
    );
    
    $pdo = new PDO($dsn, $config['user'], $config['pass'], $config['options']);
    
    // Create database if not exists
    echo "Checking database...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$config['name']} CHARACTER SET {$config['charset']} COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE {$config['name']}");
    echo "Database '{$config['name']}' ready.\n\n";
    
    // Create migrations tracking table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Get already executed migrations
    $stmt = $pdo->query("SELECT migration FROM migrations ORDER BY id");
    $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Find migration files
    $files = glob(__DIR__ . '/*.php');
    sort($files);
    
    $newMigrations = 0;
    
    foreach ($files as $file) {
        $migration = basename($file);
        
        // Skip this script
        if ($migration === 'migrate.php') {
            continue;
        }
        
        // Skip already executed migrations
        if (in_array($migration, $executed)) {
            echo "⏭️  Skipping: {$migration} (already executed)\n";
            continue;
        }
        
        echo "🔄 Running: {$migration}\n";
        
        // Load migration
        $config = require $file;
        
        if (!isset($config['up'])) {
            echo "❌ Error: Migration {$migration} missing 'up' definition\n";
            continue;
        }
        
        try {
            // Execute up migration
            $pdo->exec($config['up']);
            
            // Record migration
            $stmt = $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
            $stmt->execute([$migration]);
            
            echo "✅ Completed: {$migration}\n";
            $newMigrations++;
            
        } catch (PDOException $e) {
            echo "❌ Failed: {$migration}\n";
            echo "   Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    echo "\n";
    if ($newMigrations > 0) {
        echo "✨ Successfully executed {$newMigrations} new migration(s)!\n";
    } else {
        echo "✨ All migrations up to date!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
