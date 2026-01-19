<?php

namespace App\Core;

use Psr\Log\LoggerInterface;

class Logger
{
    private static ?LoggerInterface $instance = null;
    
    /**
     * Get singleton logger instance
     */
    public static function getInstance(): LoggerInterface
    {
        if (self::$instance === null) {
            self::$instance = require __DIR__ . '/../../config/logging.php';
        }
        
        return self::$instance;
    }
}
