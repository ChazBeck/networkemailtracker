<?php

namespace App\Repositories;

use PDO;

class LinkedInMessageRepository
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Create new LinkedIn message
     * 
     * @param array $data [thread_id, sender_email, direction, message_text, sent_at]
     * @return int Message ID
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO linkedin_messages (thread_id, sender_email, direction, message_text, sent_at)
            VALUES (:thread_id, :sender_email, :direction, :message_text, :sent_at)
        ");
        
        $stmt->execute([
            'thread_id' => $data['thread_id'],
            'sender_email' => $data['sender_email'],
            'direction' => $data['direction'],
            'message_text' => $data['message_text'],
            'sent_at' => $data['sent_at']
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Get all messages for a thread
     * 
     * @param int $threadId
     * @return array
     */
    public function findByThreadId(int $threadId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM linkedin_messages 
            WHERE thread_id = ?
            ORDER BY sent_at ASC, created_at ASC
        ");
        $stmt->execute([$threadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get latest message for a thread
     * 
     * @param int $threadId
     * @return array|null
     */
    public function getLatestForThread(int $threadId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM linkedin_messages 
            WHERE thread_id = ?
            ORDER BY sent_at DESC, created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$threadId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Get first message for a thread
     * 
     * @param int $threadId
     * @return array|null
     */
    public function getFirstForThread(int $threadId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM linkedin_messages 
            WHERE thread_id = ?
            ORDER BY sent_at ASC, created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$threadId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Get message count for a thread
     * 
     * @param int $threadId
     * @return int
     */
    public function getCountForThread(int $threadId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM linkedin_messages 
            WHERE thread_id = ?
        ");
        $stmt->execute([$threadId]);
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Find message by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM linkedin_messages WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
