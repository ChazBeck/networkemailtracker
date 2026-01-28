<?php

namespace App\Services;

use App\Core\HttpClient;
use Psr\Log\LoggerInterface;

class OutlookDraftService
{
    private HttpClient $httpClient;
    private LoggerInterface $logger;
    private ?LinkTrackingService $linkTrackingService;
    private \PDO $db;
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
    
    // Safe HTML tags for email content (includes <img> for signatures)
    private string $allowedTags = '<p><br><strong><em><u><s><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><span><div><img><table><tbody><tr><td>';
    
    public function __construct(
        LoggerInterface $logger,
        \PDO $db,
        ?LinkTrackingService $linkTrackingService = null,
        ?HttpClient $httpClient = null
    ) {
        $this->logger = $logger;
        $this->db = $db;
        $this->linkTrackingService = $linkTrackingService;
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
            
            // Get user signature
            $signatureData = $this->getUserSignature($userKey);
            
            // Append signature HTML to body
            if ($signatureData && !empty($signatureData['html'])) {
                $sanitizedBody .= "<br><br>" . $signatureData['html'];
            }
            
            // Process links for tracking (if link tracking enabled)
            $draftId = null;
            if ($this->linkTrackingService !== null) {
                // Generate unique draft ID for matching later
                $draftId = uniqid('draft_', true);
                $sanitizedBody = $this->linkTrackingService->processLinks($sanitizedBody, null, $draftId);
                
                // Embed draft_id as hidden comment in HTML for webhook matching
                $sanitizedBody = "<!-- tracking-draft-id:$draftId -->" . $sanitizedBody;
                
                $this->logger->info('Links processed for tracking', [
                    'user' => $userName,
                    'draft_id' => $draftId,
                    'processed_count' => count($this->linkTrackingService->getProcessedLinks())
                ]);
            }
            
            $message = [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $sanitizedBody
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
            $messageId = $response['id'] ?? null;
            
            // Add inline attachments for signature images
            if ($messageId && $signatureData && !empty($signatureData['attachments'])) {
                foreach ($signatureData['attachments'] as $attachment) {
                    $attachResult = $this->addInlineAttachment(
                        $userEmail,
                        $messageId,
                        $attachment['path'],
                        $attachment['cid'],
                        $token
                    );
                    
                    if (!$attachResult['success']) {
                        $this->logger->warning('Failed to attach signature image', [
                            'cid' => $attachment['cid'],
                            'error' => $attachResult['error'] ?? 'Unknown'
                        ]);
                    }
                }
            }
            
            $this->logger->info('Draft created successfully', [
                'user' => $userName,
                'to' => $toEmail,
                'subject' => $subject,
                'draft_id' => $messageId,
                'tracking_draft_id' => $draftId,
                'signature_attachments' => count($signatureData['attachments'] ?? [])
            ]);
            
            return [
                'success' => true,
                'draft_id' => $messageId,
                'tracking_draft_id' => $draftId,
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
        
        // Remove javascript: protocol from links and images
        $html = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']]/i', 'href="#"', $html);
        $html = preg_replace('/src\s*=\s*["\']javascript:[^"\']*["\']]/i', 'src="#"', $html);
        
        return $html;
    }
        /**
     * Get user signature HTML and inline attachment data
     * 
     * @param string $userName User key (lowercase username)
     * @return array|null ['html' => string, 'attachments' => array] or null
     */
    private function getUserSignature(string $userName): ?array
    {
        try {
            $signatureKey = 'signature_' . strtolower($userName);
            $stmt = $this->db->prepare("SELECT value FROM sync_state WHERE name = ?");
            $stmt->execute([$signatureKey]);
            $signatureHtml = $stmt->fetchColumn();
            
            if (!$signatureHtml) {
                return null;
            }
            
            // Parse signature HTML to find CID references
            $attachments = [];
            $signatureDir = __DIR__ . '/../../public/signatures/';
            
            // Extract all cid: references from img tags
            preg_match_all('/src=["\']cid:([^"\']+)["\']/i', $signatureHtml, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $cid) {
                    // Map CID to file path (e.g., "marcy-logo" -> "marcy-logo.png")
                    $possibleExtensions = ['png', 'jpg', 'jpeg', 'gif'];
                    $filePath = null;
                    
                    foreach ($possibleExtensions as $ext) {
                        $testPath = $signatureDir . $cid . '.' . $ext;
                        if (file_exists($testPath)) {
                            $filePath = $testPath;
                            break;
                        }
                    }
                    
                    if ($filePath && file_exists($filePath)) {
                        $attachments[] = [
                            'cid' => $cid,
                            'path' => $filePath
                        ];
                    } else {
                        $this->logger->warning('Signature image not found', [
                            'user' => $userName,
                            'cid' => $cid
                        ]);
                    }
                }
            }
            
            return [
                'html' => $signatureHtml,
                'attachments' => $attachments
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get user signature', [
                'user' => $userName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Add an inline attachment to an existing draft message
     * 
     * @param string $userEmail User's email address
     * @param string $messageId Draft message ID
     * @param string $filePath Path to image file
     * @param string $contentId Content-ID for cid: reference
     * @param string $token Access token
     * @return array ['success' => bool, 'error' => string]
     */
    private function addInlineAttachment(
        string $userEmail,
        string $messageId,
        string $filePath,
        string $contentId,
        string $token
    ): array {
        try {
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'error' => 'File not found: ' . $filePath
                ];
            }
            
            $imageData = file_get_contents($filePath);
            $base64Image = base64_encode($imageData);
            $fileName = basename($filePath);
            
            // Determine MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            
            $attachment = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $fileName,
                'contentType' => $mimeType,
                'contentBytes' => $base64Image,
                'contentId' => $contentId,
                'isInline' => true
            ];
            
            $result = $this->httpClient->post(
                "https://graph.microsoft.com/v1.0/users/{$userEmail}/messages/{$messageId}/attachments",
                $attachment,
                [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                ]
            );
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Unknown error'
                ];
            }
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
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
