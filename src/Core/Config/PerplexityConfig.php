<?php

namespace App\Core\Config;

/**
 * Perplexity AI Configuration
 */
class PerplexityConfig
{
    private string $apiKey;
    private string $model;
    
    public function __construct(array $env)
    {
        $this->apiKey = $env['PERPLEXITY_API_KEY'] ?? '';
        $this->model = $env['PERPLEXITY_MODEL'] ?? 'sonar';
    }
    
    public function apiKey(): string
    {
        return $this->apiKey;
    }
    
    public function model(): string
    {
        return $this->model;
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
}
