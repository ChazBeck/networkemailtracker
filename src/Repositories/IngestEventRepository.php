<?php

namespace App\Repositories;

use PDO;

class IngestEventRepository
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Create new ingest event
     * 
     * @param string $rawJson
     * @param bool $webhookSecretValid
     * @return int Event ID
     */
    public function create(string $rawJson, bool $webhookSecretValid = false): int
    {
        // TODO: Implement create method
        // INSERT INTO ingest_events (raw_json, webhook_secret_valid)
        // VALUES (?, ?)
        return 0;
    }
    
    /**
     * Get unprocessed events
     * 
     * @return array
     */
    public function getUnprocessed(): array
    {
        // TODO: Implement getUnprocessed method
        // SELECT * FROM ingest_events
        // WHERE processed = FALSE
        // ORDER BY created_at ASC
        return [];
    }
    
    /**
     * Mark event as processed
     * 
     * @param int $id
     * @return bool
     */
    public function markAsProcessed(int $id): bool
    {
        // TODO: Implement markAsProcessed method
        // UPDATE ingest_events SET processed = TRUE WHERE id = ?
        return false;
    }
    
    /**
     * Get recent events
     * 
     * @param int $limit
     * @return array
     */
    public function getRecent(int $limit = 50): array
    {
        // TODO: Implement getRecent method
        // SELECT * FROM ingest_events
        // ORDER BY created_at DESC
        // LIMIT ?
        return [];
    }
}
