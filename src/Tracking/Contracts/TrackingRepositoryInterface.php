<?php

namespace App\Tracking\Contracts;

/**
 * Interface for Email Tracking Repository
 * 
 * Manages email open tracking beacon data and open events
 */
interface TrackingRepositoryInterface
{
    /**
     * Create a new tracking beacon (draft status)
     * 
     * @param string $beaconId Unique 32-char hex identifier
     * @return int|null Tracking record ID, or null on failure
     */
    public function createBeacon(string $beaconId): ?int;
    
    /**
     * Activate a beacon when email is confirmed sent
     * 
     * @param string $beaconId Beacon identifier
     * @param int $emailId Email record ID to link
     * @return bool Success status
     */
    public function activateBeacon(string $beaconId, int $emailId): bool;
    
    /**
     * Find tracking record by beacon ID
     * 
     * @param string $beaconId Beacon identifier
     * @return array|null Tracking record or null if not found
     */
    public function findByBeaconId(string $beaconId): ?array;
    
    /**
     * Find tracking record by email ID
     * 
     * @param int $emailId Email record ID
     * @return array|null Tracking record or null if not found
     */
    public function findByEmailId(int $emailId): ?array;
    
    /**
     * Record an open event and update tracking counters
     * 
     * @param string $beaconId Beacon identifier
     * @param int $secondsSinceActivation Seconds elapsed since email was sent
     * @param string|null $userAgent Browser/client user agent
     * @param string|null $ipAddress IP address of opener
     * @param bool $isBot Whether this open is identified as bot/scanner
     * @return int|null Event ID, or null on failure
     */
    public function recordOpen(
        string $beaconId,
        int $secondsSinceActivation,
        ?string $userAgent,
        ?string $ipAddress,
        bool $isBot
    ): ?int;
    
    /**
     * Get all open events for a beacon
     * 
     * @param string $beaconId Beacon identifier
     * @return array List of open event records
     */
    public function getOpenEvents(string $beaconId): array;
    
    /**
     * Get tracking statistics for dashboard
     * 
     * @return array ['total_tracked' => int, 'emails_opened' => int, 'open_rate' => float]
     */
    public function getTrackingStats(): array;
}
