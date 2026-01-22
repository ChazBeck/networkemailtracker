<?php

namespace App\Contracts;

interface EmailRepositoryInterface
{
    /**
     * Find email by graph message ID
     * 
     * @param string $graphMessageId
     * @return array|null
     */
    public function findByGraphMessageId(string $graphMessageId): ?array;
    
    /**
     * Find email by internet message ID
     * 
     * @param string $internetMessageId
     * @return array|null
     */
    public function findByInternetMessageId(string $internetMessageId): ?array;
    
    /**
     * Create new email
     * 
     * @param array $data Email data
     * @return int|null Email ID or null if duplicate
     */
    public function create(array $data): ?int;
    
    /**
     * Get emails by thread ID
     * 
     * @param int $threadId
     * @return array
     */
    public function getByThreadId(int $threadId): array;
    
    /**
     * Get recent emails
     * 
     * @param int $limit
     * @return array
     */
    public function getRecent(int $limit = 50): array;
    
    /**
     * Get first email in a thread
     * 
     * @param int $threadId
     * @return array|null
     */
    public function getFirstByThreadId(int $threadId): ?array;
}
