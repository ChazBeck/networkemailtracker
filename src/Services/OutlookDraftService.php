<?php

namespace App\Services;

use App\Core\HttpClient;
use App\Tracking\Repositories\TrackingRepository;
use App\Tracking\Services\TrackingService;
use Psr\Log\LoggerInterface;

class OutlookDraftService
{
    private HttpClient $httpClient;
    private LoggerInterface $logger;
    private TrackingRepository $trackingRepo;
    private TrackingService $trackingService;
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $tokenCacheFile;
    
    private array $userEmails = [
        'charlie' => '',
        'marcy' => '',
        'ann' => '',
        'kristen' => '',
        'katie' => '',
        'tameka' => ''
    ];
    
    // Safe HTML tags for email content (including img for tracking beacon)
    private string $allowedTags = '<p><br><strong><em><u><s><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><span><div><img>';
    
    public function __construct(
        LoggerInterface $logger,
        TrackingRepository $trackingRepo,
        TrackingService $trackingService,
        ?HttpClient $httpClient = null
    ) {
        $this->logger = $logger;
        $this->trackingRepo = $trackingRepo;
        $this->trackingService = $trackingService;
        $this->httpClient = $httpClient ?? new HttpClient();
        
        $this->tenantId = $_ENV['MS_GRAPH_TENANT_ID'] ?? '';
        $this->clientId = $_ENV['MS_GRAPH_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['MS_GRAPH_CLIENT_SECRET'] ?? '';
        
        $this->userEmails['charlie'] = $_ENV['MS_GRAPH_USER_CHARLIE'] ?? '';
        $this->userEmails['marcy'] = $_ENV['MS_GRAPH_USER_MARCY'] ?? '';
        $this->userEmails['ann'] = $_ENV['MS_GRAPH_USER_ANN'] ?? '';
        $this->userEmails['kristen'] = $_ENV['MS_GRAPH_USER_KRISTEN'] ?? '';
        $this->userEmails['katie'] = $_ENV['MS_GRAPH_USER_KATIE'] ?? '';
        $this->userEmails['tameka'] = $_ENV['MS_GRAPH_USER_TAMEKA'] ?? '';
        
        $this->tokenCacheFile = __DIR__ . '/../../logs/graph_token.json';
    }
    
    public function createDraft(string $userName, string $toEmail, string $subject, string $htmlBody): array
    {
        try {
            $userKey = strtolower($userName);
            if (!isset($this->userEmails[$userKey]) || empty($this->userEmails[$userKey])) {
                throw new \Exception("Email not configured for user: $userName");
            }
            
            $userEmail = $this->userEmails[$userKey];
            
            $token = $this->getAccessToken();
            if (!$token) {
                throw new \Exception('Failed to obtain access token');
            }
            
            // Sanitize HTML content
            $sanitizedBody = $this->sanitizeHtml($htmlBody);
            
            // Generate and inject tracking beacon
            $beaconId = $this->trackingService->generateBeaconId();
            $appUrl = $_ENV['APP_URL'] ?? 'https://yourdomain.com';
            $beaconHtml = $this->trackingService->generateBeaconHtml($appUrl, $beaconId);
            
            // Append beacon to email body
            $bodyWithBeacon = $sanitizedBody . $beaconHtml;
            
            // Create tracking record (draft status)
            $this->trackingRepo->createBeacon($beaconId);
            
            $this->logger->info('Tracking beacon injected', [
                'beacon_id' => $beaconId,
                'user' => $userName,
                'to' => $toEmail
            ]);
            
            $message = [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $bodyWithBeacon
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'address' => $toEmail
                        ]
                    ]
                ],
                'bccRecipients' => [
                    [
                        'emailAddress' => [
                            'address' => 'networking@veerless.com'
                        ]
                    ]
                ]
            ];
            
            $result = $this->httpClient->post(
                "https://graph.microsoft.com/v1.0/users/{$userEmail}/mailFolders/drafts/messages",
                $message,
                [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ]
            );
            
            if (!$result['success']) {
                throw new \Exception('Graph API error: ' . ($result['error'] ?? 'Unknown error'));
            }
            
            $response = json_decode($result['body'], true);
            
            $this->logger->info('Draft created successfully', [
                'user' => $userName,
                'to' => $toEmail,
                'subject' => $subject,
                'draft_id' => $response['id'] ?? null
            ]);
            
            return [
                'success' => true,
                'draft_id' => $response['id'] ?? null,
                'message' => 'Draft created successfully in ' . $userName . "'s mailbox"
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to create draft', [
                'user' => $userName ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function sanitizeHtml(string $html): string
    {
        // Remove dangerous scripts and event handlers
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $html);
        $html = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/on\w+\s*=\s*\S+/i', '', $html);
        
        // Strip tags except allowed ones
        $html = strip_tags($html, $this->allowedTags);
        
        // Remove javascript: protocol from links
        $html = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $html);
        
        return $html;
    }
    
    private function getAccessToken(): ?string
    {
        // Check cached token
        if (file_exists($this->tokenCacheFile)) {
            $cached = json_decode(file_get_contents($this->tokenCacheFile), true);
            if ($cached && isset($cached['access_token'], $cached['expires_at'])) {
                // Valid if expires more than 5 minutes from now
                if ($cached['expires_at'] > time() + 300) {
                    return $cached['access_token'];
                }
            }
        }
        
        // Request new token
        if (empty($this->tenantId) || empty($this->clientId) || empty($this->clientSecret)) {
            $this->logger->error('Graph API credentials not configured');
            return null;
        }
        
        try {
            $result = $this->httpClient->post(
                "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
                http_build_query([
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials'
                ]),
                ['Content-Type: application/x-www-form-urlencoded']
            );
            
            if (!$result['success']) {
                $this->logger->error('Failed to get access token', ['error' => $result['error']]);
                return null;
            }
            
            $data = json_decode($result['body'], true);
            $accessToken = $data['access_token'] ?? null;
            
            if ($accessToken) {
                // Cache token with expiration
                $expiresIn = $data['expires_in'] ?? 3599;
                file_put_contents($this->tokenCacheFile, json_encode([
                    'access_token' => $accessToken,
                    'expires_at' => time() + $expiresIn
                ]));
            }
            
            return $accessToken;
            
        } catch (\Exception $e) {
            $this->logger->error('Exception getting access token', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
