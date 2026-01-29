<?php

namespace App\Repositories;

use PDO;
use App\Contracts\EmailRepositoryInterface;

class EmailRepository implements EmailRepositoryInterface
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Find email by graph message ID (primary deduplication key)
     * 
     * @param string $graphMessageId
     * @return array|null
     */
    public function findByGraphMessageId(string $graphMessageId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM emails 
            WHERE graph_message_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$graphMessageId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Find email by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM emails 
            WHERE id = ? 
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Find email by internet message ID (secondary deduplication key)
     * 
     * @param string $internetMessageId
     * @return array|null
     */
    public function findByInternetMessageId(string $internetMessageId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM emails 
            WHERE internet_message_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$internetMessageId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Create new email with idempotency via UNIQUE constraints
     * Returns email ID on success, null if duplicate
     * 
     * @param array $data
     * @return int|null Email ID or null if duplicate
     */
    public function create(array $data): ?int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO emails (
                    thread_id, direction, graph_message_id, internet_message_id,
                    subject, from_email, to_json, cc_json, bcc_json,
                    sent_at, received_at, body_preview, body_text, raw_payload
                ) VALUES (
                    :thread_id, :direction, :graph_message_id, :internet_message_id,
                    :subject, :from_email, :to_json, :cc_json, :bcc_json,
                    :sent_at, :received_at, :body_preview, :body_text, :raw_payload
                )
            ");
            
            $stmt->execute([
                'thread_id' => $data['thread_id'],
                'direction' => $data['direction'] ?? 'unknown',
                'graph_message_id' => $data['graph_message_id'] ?? null,
                'internet_message_id' => $data['internet_message_id'] ?? null,
                'subject' => $data['subject'] ?? null,
                'from_email' => $data['from_email'] ?? null,
                'to_json' => isset($data['to']) ? json_encode($data['to']) : null,
                'cc_json' => isset($data['cc']) ? json_encode($data['cc']) : null,
                'bcc_json' => isset($data['bcc']) ? json_encode($data['bcc']) : null,
                'sent_at' => $data['sent_at'] ?? null,
                'received_at' => $data['received_at'] ?? null,
                'body_preview' => $data['body_preview'] ?? null,
                'body_text' => $data['body_text'] ?? null,
                'raw_payload' => isset($data['raw_payload']) ? json_encode($data['raw_payload']) : null
            ]);
            
            return (int) $this->db->lastInsertId();
            
        } catch (\PDOException $e) {
            // Check if duplicate key error
            if ($e->getCode() === '23000') {
                return null; // Duplicate, idempotency check passed
            }
            throw $e; // Re-throw other errors
        }
    }
    
    /**
     * Get emails by thread ID
     * 
     * @param int $threadId
     * @return array
     */
    public function getByThreadId(int $threadId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM emails 
            WHERE thread_id = ? 
            ORDER BY sent_at DESC, created_at DESC
        ");
        $stmt->execute([$threadId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get recent emails across all threads
     * 
     * @param int $limit
     * @return array
     */
    public function getRecent(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, 
                   t.external_email as thread_external_email,
                   t.internal_sender_email as thread_internal_email,
                   t.subject_normalized as thread_subject
            FROM emails e
            JOIN threads t ON e.thread_id = t.id
            ORDER BY e.received_at DESC, e.sent_at DESC, e.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get first email in a thread (chronologically)
     * 
     * @param int $threadId
     * @return array|null
     */
    public function getFirstByThreadId(int $threadId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM emails 
            WHERE thread_id = ? 
            ORDER BY created_at ASC 
            LIMIT 1
        ");
        $stmt->execute([$threadId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
