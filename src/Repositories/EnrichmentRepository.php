<?php

namespace App\Repositories;

use PDO;
use App\Contracts\EnrichmentRepositoryInterface;

class EnrichmentRepository implements EnrichmentRepositoryInterface
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Find enrichment by thread ID
     * 
     * @param int $threadId
     * @return array|null
     */
    public function findByThreadId(int $threadId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM contact_enrichment 
            WHERE thread_id = ? 
            LIMIT 1
        ');
        $stmt->execute([$threadId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Find enrichment by external email
     * 
     * @param string $externalEmail
     * @return array|null
     */
    public function findByEmail(string $externalEmail): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM contact_enrichment 
            WHERE external_email = ? 
            ORDER BY created_at DESC
            LIMIT 1
        ');
        $stmt->execute([$externalEmail]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Create enrichment record
     * 
     * @param array $data
     * @return int Enrichment ID
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO contact_enrichment (
                thread_id,
                linkedin_thread_id,
                external_email,
                external_linkedin_url,
                first_name,
                last_name,
                full_name,
                company_name,
                company_url,
                linkedin_url,
                job_title,
                enrichment_source,
                enrichment_status,
                confidence_score,
                raw_prompt,
                raw_response,
                error_message,
                enriched_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $data['thread_id'] ?? null,
            $data['linkedin_thread_id'] ?? null,
            $data['external_email'] ?? null,
            $data['external_linkedin_url'] ?? null,
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['full_name'] ?? null,
            $data['company_name'] ?? null,
            $data['company_url'] ?? null,
            $data['linkedin_url'] ?? null,
            $data['job_title'] ?? null,
            $data['enrichment_source'],
            $data['enrichment_status'] ?? 'pending',
            $data['confidence_score'] ?? null,
            $data['raw_prompt'] ?? null,
            $data['raw_response'] ?? null,
            $data['error_message'] ?? null,
            $data['enriched_at'] ?? null
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Update enrichment record
     * 
     * @param int $threadId
     * @param array $data
     * @return bool
     */
    public function update(int $threadId, array $data): bool
    {
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'first_name', 'last_name', 'full_name', 'company_name', 
            'company_url', 'linkedin_url', 'job_title',
            'enrichment_status', 'confidence_score', 
            'raw_prompt', 'raw_response', 'error_message', 'enriched_at'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $threadId;
        $sql = "UPDATE contact_enrichment SET " . implode(', ', $fields) . " WHERE thread_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Get all enrichments with thread info
     * 
     * @return array
     */
    public function getAllWithThreadInfo(): array
    {
        $stmt = $this->db->query('
            SELECT 
                ce.*,
                t.external_email as thread_email,
                t.subject_normalized,
                t.status as thread_status
            FROM contact_enrichment ce
            JOIN threads t ON ce.thread_id = t.id
            ORDER BY ce.created_at DESC
        ');
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get pending enrichments
     * 
     * @return array
     */
    public function getPending(): array
    {
        $stmt = $this->db->query('
            SELECT ce.*, t.external_email, t.subject_normalized
            FROM contact_enrichment ce
            JOIN threads t ON ce.thread_id = t.id
            WHERE ce.enrichment_status = \'pending\'
            ORDER BY ce.created_at ASC
        ');
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get failed enrichments for retry
     * 
     * @return array
     */
    public function getFailed(): array
    {
        $stmt = $this->db->query('
            SELECT ce.*, t.external_email, t.subject_normalized
            FROM contact_enrichment ce
            JOIN threads t ON ce.thread_id = t.id
            WHERE ce.enrichment_status = \'failed\'
            ORDER BY ce.created_at DESC
        ');
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update enrichment record by ID
     * 
     * @param int $id Enrichment ID
     * @param array $data
     * @return bool
     */
    public function updateById(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'first_name', 'last_name', 'full_name', 'company_name', 
            'company_url', 'linkedin_url', 'job_title'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        $sql = "UPDATE contact_enrichment SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Find enrichment by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM contact_enrichment WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Find enrichment by LinkedIn thread ID
     * 
     * @param int $linkedInThreadId
     * @return array|null
     */
    public function findByLinkedInThreadId(int $linkedInThreadId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM contact_enrichment 
            WHERE linkedin_thread_id = ? 
            LIMIT 1
        ');
        $stmt->execute([$linkedInThreadId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Find enrichment by LinkedIn URL (across any thread type)
     * 
     * @param string $linkedInUrl
     * @return array|null
     */
    public function findByLinkedInUrl(string $linkedInUrl): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM contact_enrichment 
            WHERE external_linkedin_url = ? OR linkedin_url = ?
            ORDER BY created_at DESC
            LIMIT 1
        ');
        $stmt->execute([$linkedInUrl, $linkedInUrl]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Update enrichment by LinkedIn thread ID
     * 
     * @param int $linkedInThreadId
     * @param array $data
     * @return bool
     */
    public function updateByLinkedInThreadId(int $linkedInThreadId, array $data): bool
    {
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'first_name', 'last_name', 'full_name', 'company_name', 'company_url',
            'linkedin_url', 'job_title', 'enrichment_status', 'confidence_score',
            'raw_prompt', 'raw_response', 'error_message', 'enriched_at',
            'external_linkedin_url'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $linkedInThreadId;
        $sql = "UPDATE contact_enrichment SET " . implode(', ', $fields) . " WHERE linkedin_thread_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}

