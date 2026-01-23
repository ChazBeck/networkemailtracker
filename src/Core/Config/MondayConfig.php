<?php

namespace App\Core\Config;

/**
 * Monday.com API Configuration
 */
class MondayConfig
{
    private string $apiKey;
    private string $boardId;
    private string $domain;
    private array $columnIds;
    
    public function __construct(array $env)
    {
        $this->apiKey = $env['MONDAY_API_KEY'] ?? '';
        $this->boardId = $env['MONDAY_BOARD_ID'] ?? '';
        $this->domain = $env['MONDAY_DOMAIN'] ?? '';
        
        $this->columnIds = [
            'subject' => $env['MONDAY_COLUMN_SUBJECT'] ?? '',
            'from_email' => $env['MONDAY_COLUMN_FROM_EMAIL'] ?? '',
            'first_name' => $env['MONDAY_COLUMN_FIRST_NAME'] ?? '',
            'last_name' => $env['MONDAY_COLUMN_LAST_NAME'] ?? '',
            'company_name' => $env['MONDAY_COLUMN_COMPANY_NAME'] ?? '',
            'company_url' => $env['MONDAY_COLUMN_COMPANY_URL'] ?? '',
            'linkedin_url' => $env['MONDAY_COLUMN_LINKEDIN_URL'] ?? '',
            'job_title' => $env['MONDAY_COLUMN_JOB_TITLE'] ?? '',
            'confidence' => $env['MONDAY_COLUMN_CONFIDENCE'] ?? '',
            'email_count' => $env['MONDAY_COLUMN_EMAIL_COUNT'] ?? '',
            'last_contact' => $env['MONDAY_COLUMN_LAST_CONTACT'] ?? '',
            'source' => $env['MONDAY_COLUMN_SOURCE'] ?? '',
            'enrichment_status' => $env['MONDAY_COLUMN_ENRICHMENT_STATUS'] ?? '',
            'sync_status' => $env['MONDAY_COLUMN_SYNC_STATUS'] ?? ''
        ];
    }
    
    public function apiKey(): string
    {
        return $this->apiKey;
    }
    
    public function boardId(): string
    {
        return $this->boardId;
    }
    
    public function domain(): string
    {
        return $this->domain;
    }
    
    public function columnIds(): array
    {
        return $this->columnIds;
    }
    
    public function getColumnId(string $name): ?string
    {
        return $this->columnIds[$name] ?? null;
    }
    
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->boardId);
    }
}
