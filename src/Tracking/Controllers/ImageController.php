<?php

namespace App\Tracking\Controllers;

use App\Tracking\Repositories\TrackingRepository;
use App\Tracking\Services\TrackingService;
use Psr\Log\LoggerInterface;

/**
 * Controller for Tracking Beacon Image Endpoint
 * 
 * Handles requests to /public/img/spacer.gif?cache={beacon_id}
 * Returns a 1x1 transparent GIF while recording open events
 */
class ImageController
{
    private TrackingRepository $trackingRepo;
    private TrackingService $trackingService;
    private LoggerInterface $logger;
    
    // 1x1 transparent GIF (43 bytes)
    private const TRANSPARENT_GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    
    public function __construct(
        TrackingRepository $trackingRepo,
        TrackingService $trackingService,
        LoggerInterface $logger
    ) {
        $this->trackingRepo = $trackingRepo;
        $this->trackingService = $trackingService;
        $this->logger = $logger;
    }
    
    /**
     * Serve tracking beacon and record open event
     * 
     * Must respond < 100ms for email client compatibility
     */
    public function serveBeacon(): void
    {
        $startTime = microtime(true);
        
        try {
            // Extract beacon ID from query parameter
            $beaconId = $_GET['cache'] ?? null;
            
            if (empty($beaconId)) {
                $this->logger->warning('Beacon request missing cache parameter');
                $this->sendGif();
                return;
            }
            
            // Validate beacon ID format (32 hex chars)
            if (!preg_match('/^[a-f0-9]{32}$/i', $beaconId)) {
                $this->logger->warning('Invalid beacon ID format', [
                    'beacon_id' => $beaconId
                ]);
                $this->sendGif();
                return;
            }
            
            // Lookup tracking record
            $tracking = $this->trackingRepo->findByBeaconId($beaconId);
            
            if (!$tracking) {
                $this->logger->warning('Beacon not found', [
                    'beacon_id' => $beaconId
                ]);
                $this->sendGif();
                return;
            }
            
            // Check if beacon is active
            if ($tracking['status'] !== 'active') {
                $this->logger->info('Beacon not active yet', [
                    'beacon_id' => $beaconId,
                    'status' => $tracking['status']
                ]);
                $this->sendGif();
                return;
            }
            
            // Calculate time since activation
            $secondsSinceActivation = $this->trackingService->getSecondsSinceActivation(
                $tracking['activated_at']
            );
            
            // Capture request metadata
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $ipAddress = $this->getClientIp();
            
            // Determine if this is a bot
            $isBot = $this->trackingService->isBot($secondsSinceActivation, $userAgent);
            
            // Record the open event (async would be better for performance)
            $this->trackingRepo->recordOpen(
                $beaconId,
                $secondsSinceActivation,
                $userAgent,
                $ipAddress,
                $isBot
            );
            
            $elapsed = (microtime(true) - $startTime) * 1000;
            
            $this->logger->info('Beacon opened', [
                'beacon_id' => $beaconId,
                'email_id' => $tracking['email_id'],
                'seconds_since_activation' => $secondsSinceActivation,
                'is_bot' => $isBot,
                'response_time_ms' => round($elapsed, 2)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error processing beacon request', [
                'beacon_id' => $beaconId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        // Always send GIF regardless of errors
        $this->sendGif();
    }
    
    /**
     * Send 1x1 transparent GIF response
     */
    private function sendGif(): void
    {
        // Set headers to prevent caching
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        
        // Output the GIF
        echo base64_decode(self::TRANSPARENT_GIF_BASE64);
        exit;
    }
    
    /**
     * Get client IP address (handles proxies)
     * 
     * @return string|null IP address
     */
    private function getClientIp(): ?string
    {
        // Check for proxied requests
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // X-Forwarded-For can contain multiple IPs, take the first
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Validate IP format
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return null;
    }
}
