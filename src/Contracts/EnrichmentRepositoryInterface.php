<?php

namespace App\Contracts;

interface EnrichmentRepositoryInterface
{
    /**
     * Find enrichment by thread ID
     * 
     * @param int $threadId
     * @return array|null
     */
    public function findByThreadId(int $threadId): ?array;
    
    /**
     * Find enrichment by external email
     * 
     * @param string $externalEmail
     * @return array|null
     */
    public function findByEmail(string $externalEmail): ?array;
    
    /**
     * Create enrichment record
     * 
     * @param array $data Enrichment data
     * @return int Enrichment ID
     */
    public function create(array $data): int;
    
    /**
     * Update enrichment record
     * 
     * @param int $threadId
     * @param array $data Updated data
     * @return bool
     */
    public function update(int $threadId, array $data): bool;
    
    /**
     * Get all enrichments with thread info
     * 
     * @return array
     */
    public function getAllWithThreadInfo(): array;
    
    /**
     * Get pending enrichments
     * 
     * @return array
     */
    public function getPending(): array;
    
    /**
     * Get failed enrichments for retry
     * 
     * @return array
     */
    public function getFailed(): array;
}
