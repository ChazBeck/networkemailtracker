<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    
    /**
     * Get singleton PDO instance
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/database.php';
            
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                $config['host'],
                $config['name'],
                $config['charset']
            );
            
            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['user'],
                    $config['pass'],
                    $config['options']
                );
            } catch (PDOException $e) {
                throw new PDOException(
                    "Database connection failed: " . $e->getMessage()
                );
            }
        }
        
        return self::$instance;
    }
    
    /**
     * Begin transaction
     */
    public static function beginTransaction(): void
    {
        self::getInstance()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public static function commit(): void
    {
        self::getInstance()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public static function rollBack(): void
    {
        self::getInstance()->rollBack();
    }
}
