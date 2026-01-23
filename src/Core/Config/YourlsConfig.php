<?php

namespace App\Core\Config;

/**
 * YOURLS URL Shortener Configuration
 */
class YourlsConfig
{
    private string $apiUrl;
    private string $apiSignature;
    
    public function __construct(array $env)
    {
        $this->apiUrl = $env['YOURLS_API_URL'] ?? '';
        $this->apiSignature = $env['YOURLS_API_SIGNATURE'] ?? '';
    }
    
    public function apiUrl(): string
    {
        return $this->apiUrl;
    }
    
    public function apiSignature(): string
    {
        return $this->apiSignature;
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->apiUrl) && !empty($this->apiSignature);
    }
}
