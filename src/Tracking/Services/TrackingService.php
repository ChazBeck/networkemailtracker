<?php

namespace App\Tracking\Services;

use Psr\Log\LoggerInterface;

/**
 * Service for Email Tracking Beacon Management
 * 
 * Handles beacon generation, HTML injection, extraction, and bot detection
 */
class TrackingService
{
    private LoggerInterface $logger;
    private array $botUserAgentPatterns;
    private int $botDelaySeconds;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->botDelaySeconds = (int) ($_ENV['TRACKING_BOT_DELAY_SECONDS'] ?? 30);
        
        // Bot/scanner user-agent patterns (case-insensitive)
        $this->botUserAgentPatterns = [
            'Mimecast',
            'Proofpoint',
            'Barracuda',
            'Office.*Existence',
            'Link.*Check',
            'Security.*Scan',
            'Mail.*Security',
            'ZoomInfo',
            'Email.*Security',
            'Virus.*Scan',
            'Protection.*Service'
        ];
    }
    
    /**
     * Generate a unique cryptographically secure beacon ID
     * 
     * @return string 32-character hexadecimal string
     */
    public function generateBeaconId(): string
    {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Generate HTML for tracking beacon image
     * 
     * @param string $appUrl Base application URL (e.g., https://yourdomain.com)
     * @param string $beaconId Unique beacon identifier
     * @return string HTML img tag
     */
    public function generateBeaconHtml(string $appUrl, string $beaconId): string
    {
        $appUrl = rtrim($appUrl, '/');
        $beaconUrl = "{$appUrl}/public/img/spacer.gif?cache={$beaconId}";
        
        // 1x1 transparent image with no display:none to avoid being stripped
        // Relying on tiny size (1x1) for invisibility
        return '<img src="' . htmlspecialchars($beaconUrl, ENT_QUOTES, 'UTF-8') 
             . '" width="1" height="1" alt="" />';
    }
    
    /**
     * Extract beacon ID from email body HTML
     * 
     * @param string|null $bodyText Email body HTML content
     * @return string|null Beacon ID if found, null otherwise
     */
    public function extractBeaconIdFromBody(?string $bodyText): ?string
    {
        if (empty($bodyText)) {
            return null;
        }
        
        // Match pattern: /public/img/spacer.gif?cache={32-char-hex}
        $pattern = '/spacer\.gif\?cache=([a-f0-9]{32})/i';
        
        if (preg_match($pattern, $bodyText, $matches)) {
            $beaconId = $matches[1];
            
            $this->logger->debug('Extracted beacon ID from email body', [
                'beacon_id' => $beaconId
            ]);
            
            return $beaconId;
        }
        
        $this->logger->warning('Failed to extract beacon ID from email body', [
            'body_length' => strlen($bodyText),
            'body_preview' => substr($bodyText, 0, 200)
        ]);
        
        return null;
    }
    
    /**
     * Check if user agent matches known bot/scanner patterns
     * 
     * @param string|null $userAgent User agent string
     * @return bool True if identified as bot
     */
    public function isBotUserAgent(?string $userAgent): bool
    {
        if (empty($userAgent)) {
            return false;
        }
        
        foreach ($this->botUserAgentPatterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $userAgent)) {
                $this->logger->debug('Bot user-agent detected', [
                    'user_agent' => $userAgent,
                    'matched_pattern' => $pattern
                ]);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calculate seconds elapsed since beacon activation
     * 
     * @param string|null $activatedAt Activation timestamp (Y-m-d H:i:s)
     * @return int Seconds elapsed, or 0 if invalid
     */
    public function getSecondsSinceActivation(?string $activatedAt): int
    {
        if (empty($activatedAt)) {
            return 0;
        }
        
        try {
            $activatedTime = strtotime($activatedAt);
            if ($activatedTime === false) {
                return 0;
            }
            
            $elapsed = time() - $activatedTime;
            return max(0, $elapsed);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to calculate seconds since activation', [
                'activated_at' => $activatedAt,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Determine if an open should be flagged as bot
     * 
     * Bots are identified by:
     * 1. Opens within configured delay period (default 30 seconds)
     * 2. User-agent matching known scanner patterns
     * 
     * @param int $secondsSinceActivation Seconds since email was sent
     * @param string|null $userAgent User agent string
     * @return bool True if should be flagged as bot
     */
    public function isBot(int $secondsSinceActivation, ?string $userAgent): bool
    {
        // Check timing - bots typically open immediately
        if ($secondsSinceActivation < $this->botDelaySeconds) {
            $this->logger->debug('Bot detected via timing', [
                'seconds_since_activation' => $secondsSinceActivation,
                'threshold' => $this->botDelaySeconds
            ]);
            return true;
        }
        
        // Check user-agent patterns
        if ($this->isBotUserAgent($userAgent)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get the configured bot delay threshold in seconds
     * 
     * @return int Seconds
     */
    public function getBotDelaySeconds(): int
    {
        return $this->botDelaySeconds;
    }
}
