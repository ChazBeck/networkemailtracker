<?php

namespace App\Core;

use App\Core\Config\MicrosoftGraphConfig;
use App\Core\Config\MondayConfig;
use App\Core\Config\PerplexityConfig;
use App\Core\Config\YourlsConfig;

/**
 * Application Configuration
 * 
 * Central access point for all configuration values
 */
class Config
{
    private MicrosoftGraphConfig $microsoftGraph;
    private MondayConfig $monday;
    private PerplexityConfig $perplexity;
    private YourlsConfig $yourls;
    private array $env;
    
    public function __construct(array $env)
    {
        $this->env = $env;
        $this->microsoftGraph = new MicrosoftGraphConfig($env);
        $this->monday = new MondayConfig($env);
        $this->perplexity = new PerplexityConfig($env);
        $this->yourls = new YourlsConfig($env);
    }
    
    /**
     * Get Microsoft Graph configuration
     */
    public function microsoftGraph(): MicrosoftGraphConfig
    {
        return $this->microsoftGraph;
    }
    
    /**
     * Get Monday.com configuration
     */
    public function monday(): MondayConfig
    {
        return $this->monday;
    }
    
    /**
     * Get Perplexity AI configuration
     */
    public function perplexity(): PerplexityConfig
    {
        return $this->perplexity;
    }
    
    /**
     * Get YOURLS configuration
     */
    public function yourls(): YourlsConfig
    {
        return $this->yourls;
    }
    
    /**
     * Get raw environment variable
     * Use sparingly - prefer typed config objects
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->env[$key] ?? $default;
    }
}
