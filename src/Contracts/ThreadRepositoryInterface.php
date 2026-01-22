<?php

namespace App\Contracts;

interface ThreadRepositoryInterface
{
    /**
     * Find thread by unique combination
     * 
     * @param string $externalEmail
     * @param string $internalEmail
     * @param string|null $subjectNormalized
     * @return array|null
     */
    public function findByUniqueKey(string $externalEmail, string $internalEmail, ?string $subjectNormalized): ?array;
    
    /**
     * Create new thread
     * 
     * @param array $data Thread data
     * @return int Thread ID
     */
    public function create(array $data): int;
    
    /**
     * Get all threads with email count
     * 
     * @return array
     */
    public function getAllWithEmailCount(): array;
    
    /**
     * Update thread
     * 
     * @param int $id Thread ID
     * @param array $data Updated data
     * @return bool
     */
    public function update(int $id, array $data): bool;
    
    /**
     * Find thread by ID
     * 
     * @param int $id Thread ID
     * @return array|null
     */
    public function findById(int $id): ?array;
    
    /**
     * Get threads that need enrichment
     * 
     * @param int $limit Maximum number of threads to return
     * @return array
     */
    public function getThreadsNeedingEnrichment(int $limit = 10): array;
}
