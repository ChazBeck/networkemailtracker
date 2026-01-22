<?php

namespace App\Repositories;

use PDO;
use App\Contracts\MondaySyncRepositoryInterface;

class MondaySyncRepository implements MondaySyncRepositoryInterface
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Find sync record by thread ID
     * 
     * @param int $threadId
     * @return array|null
     */
    public function findByThreadId(int $threadId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM monday_sync 
            WHERE thread_id = ? 
            LIMIT 1
        ');
        $stmt->execute([$threadId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Create sync record
     * 
     * @param array $data Sync data (thread_id, monday_item_id, status, last_synced_at, sync_data)
     * @return int Thread ID
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO monday_sync (
                thread_id, 
                board_id,
                item_id, 
                last_pushed_at,
                last_push_status
            ) VALUES (?, ?, ?, ?, ?)
        ');
        
        // Map status: 'synced' -> 'ok', 'failed' -> 'error'
        $status = ($data['status'] ?? 'synced') === 'synced' ? 'ok' : 'error';
        
        $stmt->execute([
            $data['thread_id'],
            $data['board_id'] ?? $_ENV['MONDAY_BOARD_ID'] ?? null,
            $data['monday_item_id'],
            $data['last_synced_at'] ?? date('Y-m-d H:i:s'),
            $status
        ]);
        
        return (int) $data['thread_id'];
    }
    
    /**
     * Update sync record
     * 
     * @param string $mondayItemId
     * @param array $data
     * @return bool
     */
    public function update(string $mondayItemId, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE monday_sync 
            SET 
                last_pushed_at = ?,
                last_push_status = ?
            WHERE item_id = ?
        ');
        
        // Map status: 'synced' -> 'ok', 'failed' -> 'error'
        $status = ($data['status'] ?? 'synced') === 'synced' ? 'ok' : 'error';
        
        return $stmt->execute([
            $data['last_synced_at'] ?? date('Y-m-d H:i:s'),
            $status,
            $mondayItemId
        ]);
    }
    
    /**
     * Get threads that need syncing (no sync record or status = failed)
     * 
     * @return array
     */
    public function getThreadsNeedingSync(): array
    {
        $stmt = $this->db->query('
            SELECT t.* 
            FROM threads t
            LEFT JOIN monday_sync ms ON t.id = ms.thread_id
            WHERE ms.thread_id IS NULL OR ms.last_push_status = \'error\'
        ');
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update sync status
     * 
     * @param int $threadId
     * @param string $status
     * @param string|null $errorMessage
     * @return bool
     */
    public function updateStatus(int $threadId, string $status, ?string $errorMessage = null): bool
    {
        $stmt = $this->db->prepare('
            UPDATE monday_sync
            SET last_push_status = ?, last_error = ?, last_pushed_at = NOW()
            WHERE thread_id = ?
        ');
        
        return $stmt->execute([$status, $errorMessage, $threadId]);
    }
    
    /**
     * Get all sync records with thread info
     * 
     * @return array
     */
    public function getAllWithThreadInfo(): array
    {
        $stmt = $this->db->query('
            SELECT 
                ms.*,
                t.external_email,
                t.internal_sender_email,
                t.subject_normalized,
                t.status as thread_status
            FROM monday_sync ms
            JOIN threads t ON ms.thread_id = t.id
            ORDER BY ms.last_pushed_at DESC
        ');
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
