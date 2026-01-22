<?php

namespace App\Contracts;

interface MondaySyncRepositoryInterface
{
    /**
     * Find sync record by thread ID
     * 
     * @param int $threadId
     * @return array|null
     */
    public function findByThreadId(int $threadId): ?array;
    
    /**
     * Create sync record
     * 
     * @param array $data Sync data
     * @return int Thread ID
     */
    public function create(array $data): int;
    
    /**
     * Update sync record
     * 
     * @param string $mondayItemId
     * @param array $data Updated data
     * @return bool
     */
    public function update(string $mondayItemId, array $data): bool;
    
    /**
     * Get threads that need syncing
     * 
     * @return array
     */
    public function getThreadsNeedingSync(): array;
    
    /**
     * Update sync status
     * 
     * @param int $threadId
     * @param string $status
     * @param string|null $errorMessage
     * @return bool
     */
    public function updateStatus(int $threadId, string $status, ?string $errorMessage = null): bool;
    
    /**
     * Get all sync records with thread info
     * 
     * @return array
     */
    public function getAllWithThreadInfo(): array;
}
