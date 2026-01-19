<?php

namespace App\Repositories;

use PDO;

class EmailRepository
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Find email by provider message ID
     * 
     * @param string $providerMessageId
     * @return array|null
     */
    public function findByProviderMessageId(string $providerMessageId): ?array
    {
        // TODO: Implement findByProviderMessageId method
        // SELECT * FROM emails WHERE provider_message_id = ? LIMIT 1
        return null;
    }
    
    /**
     * Create new email with idempotency check
     * 
     * @param array $data
     * @return int|null Email ID or null if duplicate
     */
    public function create(array $data): ?int
    {
        // TODO: Implement create method with INSERT IGNORE or duplicate check
        // INSERT IGNORE INTO emails (thread_id, provider_message_id, internet_message_id, raw_json)
        // VALUES (?, ?, ?, ?)
        return null;
    }
    
    /**
     * Get emails by thread ID
     * 
     * @param int $threadId
     * @return array
     */
    public function getByThreadId(int $threadId): array
    {
        // TODO: Implement getByThreadId method
        // SELECT * FROM emails WHERE thread_id = ? ORDER BY created_at DESC
        return [];
    }
    
    /**
     * Get recent emails across all threads
     * 
     * @param int $limit
     * @return array
     */
    public function getRecent(int $limit = 50): array
    {
        // TODO: Implement getRecent method
        // SELECT e.*, t.subject as thread_subject
        // FROM emails e
        // JOIN threads t ON e.thread_id = t.id
        // ORDER BY e.created_at DESC
        // LIMIT ?
        return [];
    }
}
