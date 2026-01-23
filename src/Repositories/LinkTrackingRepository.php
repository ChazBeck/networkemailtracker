<?php

namespace App\Repositories;

use PDO;
use Psr\Log\LoggerInterface;

/**
 * Repository for Link Tracking Operations
 */
class LinkTrackingRepository
{
    private PDO $db;
    private LoggerInterface $logger;
    
    public function __construct(PDO $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    /**
     * Create a link tracking record
     * 
     * @param array $data Link data
     * @return int|null Link tracking ID
     */
    public function create(array $data): ?int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO link_tracking (
                    email_id,
                    original_url,
                    short_url,
                    yourls_keyword,
                    url_type,
                    tracking_code,
                    clicks
                ) VALUES (
                    :email_id,
                    :original_url,
                    :short_url,
                    :yourls_keyword,
                    :url_type,
                    :tracking_code,
                    0
                )
            ");
            
            $stmt->execute([
                'email_id' => $data['email_id'] ?? null,
                'original_url' => $data['original_url'],
                'short_url' => $data['short_url'],
                'yourls_keyword' => $data['yourls_keyword'],
                'url_type' => $data['url_type'] ?? 'external',
                'tracking_code' => $data['tracking_code'] ?? null
            ]);
            
            $id = (int) $this->db->lastInsertId();
            
            $this->logger->info('Link tracking record created', [
                'id' => $id,
                'original_url' => $data['original_url'],
                'short_url' => $data['short_url']
            ]);
            
            return $id;
            
        } catch (\PDOException $e) {
            $this->logger->error('Failed to create link tracking record', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return null;
        }
    }
    
    /**
     * Get all links for an email
     * 
     * @param int $emailId Email ID
     * @return array List of link records
     */
    public function getByEmailId(int $emailId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM link_tracking
                WHERE email_id = :email_id
                ORDER BY created_at ASC
            ");
            
            $stmt->execute(['email_id' => $emailId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (\PDOException $e) {
            $this->logger->error('Failed to get links by email ID', [
                'email_id' => $emailId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Update click count for a link
     * 
     * @param string $yourlsKeyword YOURLS keyword
     * @param int $clicks New click count
     * @return bool Success
     */
    public function updateClicks(string $yourlsKeyword, int $clicks): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE link_tracking
                SET clicks = :clicks,
                    updated_at = NOW()
                WHERE yourls_keyword = :keyword
            ");
            
            $stmt->execute([
                'clicks' => $clicks,
                'keyword' => $yourlsKeyword
            ]);
            
            return true;
            
        } catch (\PDOException $e) {
            $this->logger->error('Failed to update click count', [
                'keyword' => $yourlsKeyword,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get link statistics summary for dashboard
     * 
     * @return array Stats
     */
    public function getLinkStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_links,
                    SUM(clicks) as total_clicks,
                    AVG(clicks) as avg_clicks_per_link
                FROM link_tracking
            ");
            
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total_links' => (int) ($stats['total_links'] ?? 0),
                'total_clicks' => (int) ($stats['total_clicks'] ?? 0),
                'avg_clicks_per_link' => round((float) ($stats['avg_clicks_per_link'] ?? 0), 2)
            ];
            
        } catch (\PDOException $e) {
            $this->logger->error('Failed to get link stats', [
                'error' => $e->getMessage()
            ]);
            return [
                'total_links' => 0,
                'total_clicks' => 0,
                'avg_clicks_per_link' => 0
            ];
        }
    }
}
