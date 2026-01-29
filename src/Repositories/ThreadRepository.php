<?php

namespace App\Repositories;

use PDO;
use App\Contracts\ThreadRepositoryInterface;

class ThreadRepository implements ThreadRepositoryInterface
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Find thread by unique combination
     * 
     * @param string $externalEmail
     * @param string $internalEmail
     * @param string|null $subjectNormalized
     * @return array|null
     */
    public function findByUniqueKey(string $externalEmail, string $internalEmail, ?string $subjectNormalized): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM threads 
            WHERE external_email = ? 
            AND internal_sender_email = ? 
            AND subject_normalized <=> ?
            LIMIT 1
        ");
        $stmt->execute([$externalEmail, $internalEmail, $subjectNormalized]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Create new thread
     * 
     * @param array $data [external_email, internal_sender_email, subject_normalized, status, last_activity_at]
     * @return int Thread ID
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO threads (external_email, internal_sender_email, subject_normalized, status, last_activity_at)
            VALUES (:external_email, :internal_sender_email, :subject_normalized, :status, :last_activity_at)
        ");
        
        $stmt->execute([
            'external_email' => $data['external_email'],
            'internal_sender_email' => $data['internal_sender_email'],
            'subject_normalized' => $data['subject_normalized'] ?? null,
            'status' => $data['status'] ?? 'Sent',
            'last_activity_at' => $data['last_activity_at'] ?? null
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Get all threads with email count
     * 
     * @return array
     */
    public function getAllWithEmailCount(): array
    {
        $stmt = $this->db->query("
            SELECT t.*, COUNT(e.id) as email_count
            FROM threads t
            LEFT JOIN emails e ON t.id = e.thread_id
            GROUP BY t.id
            ORDER BY t.last_activity_at DESC, t.created_at DESC
        ");
        return $stmt->fetchAll();
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
        $sql = "UPDATE threads SET " . implode(', ', $fields) . " WHERE id = ?";
        
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
        $stmt = $this->db->prepare("SELECT * FROM threads WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Get threads that need enrichment
     * Returns threads with external emails that don't have completed enrichment
     * 
     * @param int $limit Maximum number of threads to return
     * @return array
     */
    public function getThreadsNeedingEnrichment(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT t.* 
            FROM threads t
            LEFT JOIN contact_enrichment ce ON t.id = ce.thread_id
            WHERE t.external_email IS NOT NULL 
            AND t.external_email != ''
            AND (ce.id IS NULL OR ce.enrichment_status = 'failed')
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all contacts grouped by email address with enrichment data
     * 
     * @return array
     */
    public function getContactsGroupedByEmail(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                t.external_email as email,
                COUNT(DISTINCT t.id) as thread_count,
                MAX(t.updated_at) as last_contact,
                MAX(ce.id) as enrichment_id,
                MAX(ce.first_name) as first_name,
                MAX(ce.last_name) as last_name,
                MAX(ce.full_name) as full_name,
                MAX(ce.company_name) as company_name,
                MAX(ce.company_url) as company_url,
                MAX(ce.linkedin_url) as linkedin_url,
                MAX(ce.job_title) as job_title,
                MAX(ce.enrichment_status) as enrichment_status
            FROM threads t
            LEFT JOIN contact_enrichment ce ON t.id = ce.thread_id
            GROUP BY t.external_email
            ORDER BY MAX(t.updated_at) DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
