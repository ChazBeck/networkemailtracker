<?php

namespace App\Repositories;

use PDO;

class BizDevSyncRepository
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Find sync record by enrichment ID
     * 
     * @param int $enrichmentId
     * @return array|null
     */
    public function findByEnrichmentId(int $enrichmentId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM bizdev_sync 
            WHERE enrichment_id = ? 
            LIMIT 1
        ');
        $stmt->execute([$enrichmentId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Find sync record by Monday item ID
     * 
     * @param string $itemId
     * @return array|null
     */
    public function findByItemId(string $itemId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM bizdev_sync 
            WHERE item_id = ? 
            LIMIT 1
        ');
        $stmt->execute([$itemId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Create sync record
     * 
     * @param array $data [enrichment_id, board_id, item_id, last_pushed_at, last_push_status]
     * @return int|false Last insert ID or false on failure
     */
    public function create(array $data)
    {
        $stmt = $this->db->prepare('
            INSERT INTO bizdev_sync (enrichment_id, board_id, item_id, last_pushed_at, last_push_status)
            VALUES (?, ?, ?, ?, ?)
        ');
        
        $result = $stmt->execute([
            $data['enrichment_id'],
            $data['board_id'] ?? null,
            $data['item_id'] ?? null,
            $data['last_pushed_at'] ?? date('Y-m-d H:i:s'),
            $data['last_push_status'] ?? 'ok'
        ]);
        
        return $result ? (int)$this->db->lastInsertId() : false;
    }
    
    /**
     * Update sync record by enrichment ID
     * 
     * @param int $enrichmentId
     * @param array $data
     * @return bool
     */
    public function update(int $enrichmentId, array $data): bool
    {
        $fields = [];
        $params = [];
        
        $allowedFields = ['board_id', 'item_id', 'last_pushed_at', 'last_push_status', 'last_error'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $enrichmentId;
        $sql = "UPDATE bizdev_sync SET " . implode(', ', $fields) . " WHERE enrichment_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Get all synced records
     * 
     * @return array
     */
    public function getAll(): array
    {
        $stmt = $this->db->query('
            SELECT bs.*, ce.external_email, ce.external_linkedin_url, ce.company_name, ce.full_name
            FROM bizdev_sync bs
            JOIN contact_enrichment ce ON bs.enrichment_id = ce.id
            ORDER BY bs.last_pushed_at DESC
        ');
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
