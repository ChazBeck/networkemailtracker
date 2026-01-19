<?php

namespace App\Repositories;

use PDO;

class ThreadRepository
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
}
