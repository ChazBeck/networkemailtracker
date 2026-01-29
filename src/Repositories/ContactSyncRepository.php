<?php

namespace App\Repositories;

use PDO;

class ContactSyncRepository
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Find sync record by email
     * 
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM contact_sync 
            WHERE email = ? 
            LIMIT 1
        ');
        $stmt->execute([$email]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Create sync record
     * 
     * @param array $data [email, monday_item_id, last_synced_at, last_sync_status]
     * @return bool
     */
    public function create(array $data): bool
    {
        $stmt = $this->db->prepare('
            INSERT INTO contact_sync (email, monday_item_id, last_synced_at, last_sync_status)
            VALUES (?, ?, ?, ?)
        ');
        
        return $stmt->execute([
            $data['email'],
            $data['monday_item_id'] ?? null,
            $data['last_synced_at'] ?? null,
            $data['last_sync_status'] ?? 'ok'
        ]);
    }
    
    /**
     * Update sync record
     * 
     * @param string $email
     * @param array $data
     * @return bool
     */
    public function update(string $email, array $data): bool
    {
        $fields = [];
        $params = [];
        
        $allowedFields = ['monday_item_id', 'last_synced_at', 'last_sync_status', 'last_error'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $email;
        $sql = "UPDATE contact_sync SET " . implode(', ', $fields) . " WHERE email = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Get all synced contacts
     * 
     * @return array
     */
    public function getAll(): array
    {
        $stmt = $this->db->query('
            SELECT * FROM contact_sync
            ORDER BY last_synced_at DESC
        ');
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
