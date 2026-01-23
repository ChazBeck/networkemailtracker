<?php

namespace App\Tracking\Repositories;

use App\Tracking\Contracts\TrackingRepositoryInterface;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * Repository for Email Tracking Operations
 * 
 * Handles all database operations for email open tracking
 */
class TrackingRepository implements TrackingRepositoryInterface
{
    private PDO $db;
    private LoggerInterface $logger;
    
    public function __construct(PDO $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    /**
     * Create a new tracking beacon (draft status)
     */
    public function createBeacon(string $beaconId): ?int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_tracking (beacon_id, status, created_at)
                VALUES (:beacon_id, 'draft', NOW())
            ");
            
            $stmt->execute(['beacon_id' => $beaconId]);
            
            $id = (int) $this->db->lastInsertId();
            
            $this->logger->info('Tracking beacon created', [
                'beacon_id' => $beaconId,
                'tracking_id' => $id
            ]);
            
            return $id;
            
        } catch (\PDOException $e) {
            $this->logger->error('Failed to create tracking beacon', [
                'beacon_id' => $beaconId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Activate a beacon when email is confirmed sent
     */
    public function activateBeacon(string $beaconId, int $emailId): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE email_tracking 
                SET status = 'active',
                    email_id = :email_id,
                    activated_at = NOW(),
                    updated_at = NOW()
                WHERE beacon_id = :beacon_id
                AND status = 'draft'
            ");
            
            $stmt->execute([
                'beacon_id' => $beaconId,
                'email_id' => $emailId
            ]);
            
            $rowCount = $stmt->rowCount();
            
            if ($rowCount > 0) {
                $this->logger->info('Tracking beacon activated', [
                    'beacon_id' => $beaconId,
                    'email_id' => $emailId
                ]);
                return true;
            } else {
                $this->logger->warning('Tracking beacon not found or already active', [
                    'beacon_id' => $beaconId,
                    'email_id' => $emailId
                ]);
                return false;
            }
            
        } catch (\PDOException $e) {
            $this->logger->error('Failed to activate tracking beacon', [
                'beacon_id' => $beaconId,
                'email_id' => $emailId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Find tracking record by beacon ID
     */
    public function findByBeaconId(string $beaconId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM email_tracking 
                WHERE beacon_id = :beacon_id
                LIMIT 1
            ");
            
            $stmt->execute(['beacon_id' => $beaconId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
            
        } catch (\PDOException $e) {
            $this->logger->error('Failed to find tracking by beacon ID', [
                'beacon_id' => $beaconId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Find tracking record by email ID
     */
    public function findByEmailId(int $emailId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM email_tracking 
                WHERE email_id = :email_id
                LIMIT 1
            ");
            
            $stmt->execute(['email_id' => $emailId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
            
        } catch (\PDOException $e) {
            $this->logger->error('Failed to find tracking by email ID', [
                'email_id' => $emailId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Record an open event and update tracking counters
     * 
     * Logic: First open is always BCC (not counted as recipient_opens)
     *        Opens < 30 seconds or bot user-agents are flagged as bots (not counted)
     */
    public function recordOpen(
        string $beaconId,
        int $secondsSinceActivation,
        ?string $userAgent,
        ?string $ipAddress,
        bool $isBot
    ): ?int
    {
        $this->db->beginTransaction();
        
        try {
            // Get current tracking state
            $tracking = $this->findByBeaconId($beaconId);
            
            if (!$tracking) {
                $this->logger->warning('Cannot record open: beacon not found', [
                    'beacon_id' => $beaconId
                ]);
                $this->db->rollBack();
                return null;
            }
            
            if ($tracking['status'] !== 'active') {
                $this->logger->warning('Cannot record open: beacon not active', [
                    'beacon_id' => $beaconId,
                    'status' => $tracking['status']
                ]);
                $this->db->rollBack();
                return null;
            }
            
            $currentTotalOpens = (int) $tracking['total_opens'];
            $newTotalOpens = $currentTotalOpens + 1;
            
            // Determine if this counts as a recipient open
            // First open = BCC (networking@veerless.com) - don't count
            // Bot opens - don't count
            $isFirstOpen = ($currentTotalOpens === 0);
            $countAsRecipientOpen = !$isFirstOpen && !$isBot;
            
            // Insert open event
            $stmt = $this->db->prepare("
                INSERT INTO open_events (
                    beacon_id, 
                    opened_at, 
                    seconds_since_activation,
                    user_agent, 
                    ip_address, 
                    is_bot,
                    counted_as_recipient_open
                )
                VALUES (
                    :beacon_id,
                    NOW(),
                    :seconds_since_activation,
                    :user_agent,
                    :ip_address,
                    :is_bot,
                    :counted_as_recipient_open
                )
            ");
            
            $stmt->execute([
                'beacon_id' => $beaconId,
                'seconds_since_activation' => $secondsSinceActivation,
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress,
                'is_bot' => $isBot ? 1 : 0,
                'counted_as_recipient_open' => $countAsRecipientOpen ? 1 : 0
            ]);
            
            $eventId = (int) $this->db->lastInsertId();
            
            // Update tracking counters
            $newRecipientOpens = $tracking['recipient_opens'];
            if ($countAsRecipientOpen) {
                $newRecipientOpens++;
            }
            
            $stmt = $this->db->prepare("
                UPDATE email_tracking 
                SET total_opens = :total_opens,
                    recipient_opens = :recipient_opens,
                    first_opened_at = COALESCE(first_opened_at, NOW()),
                    last_opened_at = NOW(),
                    updated_at = NOW()
                WHERE beacon_id = :beacon_id
            ");
            
            $stmt->execute([
                'total_opens' => $newTotalOpens,
                'recipient_opens' => $newRecipientOpens,
                'beacon_id' => $beaconId
            ]);
            
            $this->db->commit();
            
            $this->logger->info('Open event recorded', [
                'beacon_id' => $beaconId,
                'event_id' => $eventId,
                'total_opens' => $newTotalOpens,
                'recipient_opens' => $newRecipientOpens,
                'is_bot' => $isBot,
                'counted' => $countAsRecipientOpen,
                'seconds_since_activation' => $secondsSinceActivation
            ]);
            
            return $eventId;
            
        } catch (\PDOException $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to record open event', [
                'beacon_id' => $beaconId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get all open events for a beacon
     */
    public function getOpenEvents(string $beaconId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    opened_at,
                    seconds_since_activation,
                    user_agent,
                    ip_address,
                    is_bot,
                    counted_as_recipient_open
                FROM open_events 
                WHERE beacon_id = :beacon_id
                ORDER BY opened_at ASC
            ");
            
            $stmt->execute(['beacon_id' => $beaconId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (\PDOException $e) {
            $this->logger->error('Failed to get open events', [
                'beacon_id' => $beaconId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get tracking statistics for dashboard
     */
    public function getTrackingStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_tracked,
                    SUM(CASE WHEN recipient_opens > 0 THEN 1 ELSE 0 END) as emails_opened,
                    AVG(recipient_opens) as avg_opens_per_email
                FROM email_tracking
                WHERE status = 'active'
            ");
            
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $totalTracked = (int) ($stats['total_tracked'] ?? 0);
            $emailsOpened = (int) ($stats['emails_opened'] ?? 0);
            $openRate = $totalTracked > 0 ? ($emailsOpened / $totalTracked * 100) : 0;
            
            return [
                'total_tracked' => $totalTracked,
                'emails_opened' => $emailsOpened,
                'open_rate' => round($openRate, 2),
                'avg_opens_per_email' => round((float) ($stats['avg_opens_per_email'] ?? 0), 2)
            ];
            
        } catch (\PDOException $e) {
            $this->logger->error('Failed to get tracking stats', [
                'error' => $e->getMessage()
            ]);
            return [
                'total_tracked' => 0,
                'emails_opened' => 0,
                'open_rate' => 0,
                'avg_opens_per_email' => 0
            ];
        }
    }
}
