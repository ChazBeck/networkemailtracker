<?php

namespace App\Core\Config;

/**
 * Microsoft Graph API Configuration
 */
class MicrosoftGraphConfig
{
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private array $userEmails;
    private string $mailbox;
    
    public function __construct(array $env)
    {
        $this->tenantId = $env['MS_GRAPH_TENANT_ID'] ?? '';
        $this->clientId = $env['MS_GRAPH_CLIENT_ID'] ?? '';
        $this->clientSecret = $env['MS_GRAPH_CLIENT_SECRET'] ?? '';
        $this->mailbox = $env['MS_GRAPH_MAILBOX'] ?? '';
        
        $this->userEmails = [
            'charlie' => $env['MS_GRAPH_USER_CHARLIE'] ?? '',
            'sherry' => $env['MS_GRAPH_USER_SHERRY'] ?? '',
            'justin' => $env['MS_GRAPH_USER_JUSTIN'] ?? '',
            'jesse' => $env['MS_GRAPH_USER_JESSE'] ?? '',
            'andrew' => $env['MS_GRAPH_USER_ANDREW'] ?? '',
            'kyle' => $env['MS_GRAPH_USER_KYLE'] ?? '',
            'matt' => $env['MS_GRAPH_USER_MATT'] ?? '',
            'eric' => $env['MS_GRAPH_USER_ERIC'] ?? '',
            'derek' => $env['MS_GRAPH_USER_DEREK'] ?? ''
        ];
    }
    
    public function tenantId(): string
    {
        return $this->tenantId;
    }
    
    public function clientId(): string
    {
        return $this->clientId;
    }
    
    public function clientSecret(): string
    {
        return $this->clientSecret;
    }
    
    public function mailbox(): string
    {
        return $this->mailbox;
    }
    
    public function userEmails(): array
    {
        return $this->userEmails;
    }
    
    public function getUserEmail(string $user): ?string
    {
        return $this->userEmails[$user] ?? null;
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->tenantId) 
            && !empty($this->clientId) 
            && !empty($this->clientSecret);
    }
}
