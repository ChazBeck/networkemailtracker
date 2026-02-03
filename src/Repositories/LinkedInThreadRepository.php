<?php

namespace App\Repositories;

use PDO;

class LinkedInThreadRepository
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Find thread by LinkedIn URL
     * 
     * @param string $linkedInUrl Normalized LinkedIn URL
     * @return array|null
     */
    public function findByLinkedInUrl(string $linkedInUrl): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM linkedin_threads 
            WHERE external_linkedin_url = ?
            LIMIT 1
        ");
        $stmt->execute([$linkedInUrl]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Create new LinkedIn thread
     * 
     * @param array $data [external_linkedin_url, owner_email, status, last_activity_at]
     * @return int Thread ID
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO linkedin_threads (external_linkedin_url, owner_email, status, last_activity_at)
            VALUES (:external_linkedin_url, :owner_email, :status, :last_activity_at)
        ");
        
        $stmt->execute([
            'external_linkedin_url' => $data['external_linkedin_url'],
            'owner_email' => $data['owner_email'],
            'status' => $data['status'] ?? 'Sent',
            'last_activity_at' => $data['last_activity_at'] ?? null
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Update thread last activity and status
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        
        if (isset($data['status'])) {
            $fields[] = 'status = ?';
            $params[] = $data['status'];
        }
        
        if (isset($data['last_activity_at'])) {
            $fields[] = 'last_activity_at = ?';
            $params[] = $data['last_activity_at'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        $sql = "UPDATE linkedin_threads SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Find thread by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM linkedin_threads WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Get all threads with message count
     * 
     * @return array
     */
    public function getAllWithMessageCount(): array
    {
        $stmt = $this->db->query("
            SELECT lt.*, COUNT(lm.id) as message_count
            FROM linkedin_threads lt
            LEFT JOIN linkedin_messages lm ON lt.id = lm.thread_id
            GROUP BY lt.id
            ORDER BY lt.last_activity_at DESC, lt.created_at DESC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Get threads that need enrichment
     * Returns threads that don't have completed enrichment
     * 
     * @param int $limit Maximum number of threads to return
     * @return array
     */
    public function getThreadsNeedingEnrichment(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT lt.* 
            FROM linkedin_threads lt
            LEFT JOIN contact_enrichment ce ON lt.id = ce.linkedin_thread_id
            WHERE lt.external_linkedin_url IS NOT NULL 
            AND lt.external_linkedin_url != ''
            AND (ce.id IS NULL OR ce.enrichment_status = 'failed')
            ORDER BY lt.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all LinkedIn contacts grouped by URL with enrichment data
     * 
     * @return array
     */
    public function getContactsGroupedByUrl(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                lt.external_linkedin_url,
                lt.owner_email,
                COUNT(DISTINCT lt.id) as thread_count,
                MAX(lt.updated_at) as last_contact,
                MAX(ce.id) as enrichment_id,
                MAX(ce.first_name) as first_name,
                MAX(ce.last_name) as last_name,
                MAX(ce.full_name) as full_name,
                MAX(ce.company_name) as company_name,
                MAX(ce.company_url) as company_url,
                MAX(ce.linkedin_url) as linkedin_url,
                MAX(ce.job_title) as job_title,
                MAX(ce.enrichment_status) as enrichment_status
            FROM linkedin_threads lt
            LEFT JOIN contact_enrichment ce ON lt.id = ce.linkedin_thread_id
            GROUP BY lt.external_linkedin_url, lt.owner_email
            ORDER BY MAX(lt.updated_at) DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
