<?php

namespace App\Services;

use Psr\Log\LoggerInterface;

/**
 * YOURLS API Client
 * 
 * Handles communication with YOURLS URL shortener API
 */
class YourlsClient
{
    private string $apiUrl;
    private string $signature;
    private LoggerInterface $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->apiUrl = $_ENV['YOURLS_API_URL'] ?? 'https://veerl.es/yourls-api.php';
        $this->signature = $_ENV['YOURLS_API_SIGNATURE'] ?? '';
        
        if (empty($this->signature)) {
            $this->logger->warning('YOURLS API signature not configured');
        }
    }
    
    /**
     * Create a short URL
     * 
     * @param string $url Original URL to shorten
     * @param string|null $keyword Custom keyword/slug (optional)
     * @return array|null ['shorturl' => string, 'keyword' => string] or null on failure
     */
    public function createShortUrl(string $url, ?string $keyword = null): ?array
    {
        try {
            $params = [
                'signature' => $this->signature,
                'action' => 'shorturl',
                'url' => $url,
                'format' => 'json'
            ];
            
            if ($keyword !== null) {
                $params['keyword'] = $keyword;
            }
            
            $queryString = http_build_query($params);
            $fullUrl = $this->apiUrl . '?' . $queryString;
            
            $this->logger->debug('Creating YOURLS short URL', [
                'url' => $url,
                'keyword' => $keyword
            ]);
            
            $response = file_get_contents($fullUrl);
            
            if ($response === false) {
                $this->logger->error('Failed to connect to YOURLS API', [
                    'api_url' => $this->apiUrl
                ]);
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (!isset($data['status']) || $data['status'] !== 'success') {
                $this->logger->error('YOURLS API error', [
                    'response' => $data,
                    'url' => $url
                ]);
                return null;
            }
            
            $result = [
                'shorturl' => $data['shorturl'],
                'keyword' => $data['url']['keyword'] ?? $keyword,
                'title' => $data['title'] ?? null
            ];
            
            $this->logger->info('YOURLS short URL created', [
                'original' => $url,
                'short' => $result['shorturl'],
                'keyword' => $result['keyword']
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('Exception creating YOURLS short URL', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get statistics for a short URL
     * 
     * @param string $keyword YOURLS keyword
     * @return array|null ['clicks' => int, ...] or null on failure
     */
    public function getStats(string $keyword): ?array
    {
        try {
            $params = [
                'signature' => $this->signature,
                'action' => 'url-stats',
                'shorturl' => $keyword,
                'format' => 'json'
            ];
            
            $queryString = http_build_query($params);
            $fullUrl = $this->apiUrl . '?' . $queryString;
            
            $response = file_get_contents($fullUrl);
            
            if ($response === false) {
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (!isset($data['link'])) {
                return null;
            }
            
            return [
                'clicks' => (int) ($data['link']['clicks'] ?? 0),
                'url' => $data['link']['url'] ?? null
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Exception getting YOURLS stats', [
                'keyword' => $keyword,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
