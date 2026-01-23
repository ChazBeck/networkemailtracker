<?php

namespace App\Repositories;

use Psr\Log\LoggerInterface;
use PDO;

/**
 * Base Repository
 * 
 * Provides common functionality for all repositories
 */
abstract class BaseRepository
{
    protected PDO $db;
    protected LoggerInterface $logger;
    
    public function __construct(PDO $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    /**
     * Execute a query safely with error handling
     * 
     * @param callable $query Query function to execute
     * @param string $operation Operation name for logging
     * @param mixed $fallback Value to return on error
     * @return mixed Query result or fallback value
     */
    protected function executeSafely(callable $query, string $operation, mixed $fallback = null): mixed
    {
        try {
            return $query();
        } catch (\PDOException $e) {
            $this->logger->error("Repository operation failed: $operation", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $fallback;
        }
    }
    
    /**
     * Log query execution
     * 
     * @param string $operation Operation name
     * @param array $context Additional context
     */
    protected function logQuery(string $operation, array $context = []): void
    {
        $this->logger->debug("Repository: $operation", $context);
    }
}
