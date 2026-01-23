<?php

namespace App\Services;

use App\Repositories\LinkTrackingRepository;
use Psr\Log\LoggerInterface;
use DOMDocument;
use DOMXPath;

/**
 * Link Tracking Service
 * 
 * Parses email HTML, identifies links, shortens them via YOURLS,
 * and replaces them in the email body
 */
class LinkTrackingService
{
    private YourlsClient $yourlsClient;
    private LinkTrackingRepository $linkRepo;
    private LoggerInterface $logger;
    private array $processedLinks = [];
    
    public function __construct(
        YourlsClient $yourlsClient,
        LinkTrackingRepository $linkRepo,
        LoggerInterface $logger
    ) {
        $this->yourlsClient = $yourlsClient;
        $this->linkRepo = $linkRepo;
        $this->logger = $logger;
    }
    
    /**
     * Process email HTML and replace all links with tracked short URLs
     * 
     * @param string $htmlBody Original email HTML
     * @param int|null $emailId Email ID for tracking (optional, can be set later)
     * @return string Modified HTML with short URLs
     */
    public function processLinks(string $htmlBody, ?int $emailId = null): string
    {
        $this->processedLinks = [];
        
        if (empty($htmlBody)) {
            return $htmlBody;
        }
        
        $this->logger->info('Processing links in email HTML', [
            'email_id' => $emailId,
            'html_length' => strlen($htmlBody)
        ]);
        
        // Parse HTML
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $htmlBody, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        $links = $xpath->query('//a[@href]');
        
        if ($links->length === 0) {
            $this->logger->debug('No links found in email');
            return $htmlBody;
        }
        
        $this->logger->info('Found links in email', ['count' => $links->length]);
        
        $replacements = [];
        
        foreach ($links as $link) {
            $originalUrl = $link->getAttribute('href');
            
            // Skip mailto:, tel:, and anchor links
            if ($this->shouldSkipUrl($originalUrl)) {
                continue;
            }
            
            // Check if URL is veerless.com or external
            $isVeerless = $this->isVeerlessUrl($originalUrl);
            
            // Generate short URL
            $shortUrlData = $this->createShortUrl($originalUrl, $isVeerless);
            
            if ($shortUrlData === null) {
                $this->logger->warning('Failed to create short URL, skipping', [
                    'url' => $originalUrl
                ]);
                continue;
            }
            
            // Store in database
            $this->linkRepo->create([
                'email_id' => $emailId,
                'original_url' => $originalUrl,
                'short_url' => $shortUrlData['shorturl'],
                'yourls_keyword' => $shortUrlData['keyword'],
                'url_type' => $isVeerless ? 'veerless' : 'external',
                'tracking_code' => $shortUrlData['tracking_code'] ?? null
            ]);
            
            // Store for replacement
            $replacements[$originalUrl] = $shortUrlData['shorturl'];
            $this->processedLinks[] = $shortUrlData;
        }
        
        // Replace URLs in HTML
        $modifiedHtml = $htmlBody;
        foreach ($replacements as $original => $short) {
            $modifiedHtml = str_replace(
                'href="' . $original . '"',
                'href="' . $short . '"',
                $modifiedHtml
            );
        }
        
        $this->logger->info('Link processing complete', [
            'total_links' => $links->length,
            'shortened_links' => count($replacements)
        ]);
        
        return $modifiedHtml;
    }
    
    /**
     * Create a short URL via YOURLS
     * 
     * @param string $url Original URL
     * @param bool $isVeerless Whether this is a veerless.com URL
     * @return array|null Short URL data
     */
    private function createShortUrl(string $url, bool $isVeerless): ?array
    {
        if ($isVeerless) {
            // For veerless URLs, preserve path and add tracking code
            return $this->createVeerlessShortUrl($url);
        } else {
            // For external URLs, use standard short URL
            return $this->createExternalShortUrl($url);
        }
    }
    
    /**
     * Create short URL for veerless.com links
     * Preserves path and adds tracking code
     * 
     * Example: https://veerless.com/page → https://veerl.es/page-abc123
     */
    private function createVeerlessShortUrl(string $url): ?array
    {
        $parsedUrl = parse_url($url);
        $path = trim($parsedUrl['path'] ?? '', '/');
        
        // Generate 3-character tracking code
        $trackingCode = $this->generateTrackingCode(3);
        
        // Create keyword: path + tracking code
        // For root domain (veerless.com or veerless.com/), use "home"
        // Example: veerless.com → "home-abc"
        // Example: veerless.com/our-services → "our-services-xyz"
        if (empty($path)) {
            $keyword = 'home-' . $trackingCode;
        } else {
            $keyword = $path . '-' . $trackingCode;
        }
        
        // Sanitize keyword for YOURLS (lowercase, alphanumeric and hyphens only)
        $keyword = strtolower(preg_replace('/[^a-z0-9\-]/', '', str_replace('/', '-', $keyword)));
        
        $result = $this->yourlsClient->createShortUrl($url, $keyword);
        
        if ($result) {
            $result['tracking_code'] = $trackingCode;
        }
        
        return $result;
    }
    
    /**
     * Create standard short URL for external links
     * 
     * Example: https://espn.com/article → https://veerl.es/abc123
     */
    private function createExternalShortUrl(string $url): ?array
    {
        // Let YOURLS generate random keyword
        return $this->yourlsClient->createShortUrl($url);
    }
    
    /**
     * Check if URL is a veerless.com domain
     */
    private function isVeerlessUrl(string $url): bool
    {
        $parsedUrl = parse_url($url);
        $host = strtolower($parsedUrl['host'] ?? '');
        
        return $host === 'veerless.com' 
            || $host === 'www.veerless.com'
            || str_ends_with($host, '.veerless.com');
    }
    
    /**
     * Check if URL should be skipped (mailto, tel, anchors, etc.)
     */
    private function shouldSkipUrl(string $url): bool
    {
        $url = strtolower(trim($url));
        
        // Skip special protocols
        if (str_starts_with($url, 'mailto:') 
            || str_starts_with($url, 'tel:')
            || str_starts_with($url, '#')
            || str_starts_with($url, 'javascript:')
        ) {
            return true;
        }
        
        // Skip empty or invalid URLs
        if (empty($url) || $url === '#') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate random tracking code
     * 
     * @param int $length Code length
     * @return string Lowercase alphanumeric code
     */
    private function generateTrackingCode(int $length = 3): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $code;
    }
    
    /**
     * Get processed links from last processLinks() call
     * 
     * @return array List of processed link data
     */
    public function getProcessedLinks(): array
    {
        return $this->processedLinks;
    }
}
