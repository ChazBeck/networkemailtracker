<?php

namespace App\Repositories;

use PDO;

class LinkedInMondaySyncRepository
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Find sync record by LinkedIn thread ID
     * 
     * @param int $threadId
     * @return array|null
     */
    public function findByThreadId(int $threadId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM monday_sync_linkedin 
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
     * @param array $data Sync data
     * @return int Thread ID
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO monday_sync_linkedin (
                thread_id, 
                board_id,
                item_id, 
                last_pushed_at,
                last_push_status
            ) VALUES (?, ?, ?, ?, ?)
        ');
        
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
     * @param string $mondayItemId Monday item ID
     * @param array $data Update data
     * @return bool
     */
    public function update(string $mondayItemId, array $data): bool
    {
        $fields = [];
        $params = [];
        
        if (isset($data['last_synced_at'])) {
            $fields[] = 'last_pushed_at = ?';
            $params[] = $data['last_synced_at'];
        }
        
        if (isset($data['last_push_status'])) {
            $fields[] = 'last_push_status = ?';
            $params[] = $data['last_push_status'];
        }
        
        if (isset($data['last_error'])) {
            $fields[] = 'last_error = ?';
            $params[] = $data['last_error'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $mondayItemId;
        $sql = "UPDATE monday_sync_linkedin SET " . implode(', ', $fields) . " WHERE item_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Get threads that need syncing
     * 
     * @param int $limit
     * @return array
     */
    public function getThreadsNeedingSync(int $limit = 10): array
    {
        $stmt = $this->db->prepare('
            SELECT lt.* 
            FROM linkedin_threads lt
            LEFT JOIN monday_sync_linkedin ms ON lt.id = ms.thread_id
            WHERE ms.thread_id IS NULL OR ms.last_push_status = "error"
            ORDER BY lt.created_at DESC
            LIMIT ?
        ');
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
