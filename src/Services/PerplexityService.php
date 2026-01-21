<?php

namespace App\Services;

use Psr\Log\LoggerInterface;

class PerplexityService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.perplexity.ai/chat/completions';
    private string $model;
    private LoggerInterface $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->apiKey = $_ENV['PERPLEXITY_API_KEY'] ?? '';
        $this->model = $_ENV['PERPLEXITY_MODEL'] ?? 'sonar';
        $this->logger = $logger;
        $this->apiUrl = 'https://api.perplexity.ai/chat/completions';
        
        if (empty($this->apiKey)) {
            $this->logger->warning('PERPLEXITY_API_KEY not configured - enrichment will be skipped');
        }
    }
    
    /**
     * Enrich contact from email address and context
     * 
     * @param string $email Email address to enrich
     * @param array $context Additional context (subject, body preview, etc.)
     * @return array Enriched data
     */
    public function enrichContact(string $email, array $context = []): array
    {
        // Skip if API key not configured
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error' => 'Perplexity API key not configured',
                'data' => []
            ];
        }
        
        $prompt = $this->buildEnrichmentPrompt($email, $context);
        
        $this->logger->debug('Perplexity enrichment request', [
            'email' => $email,
            'prompt_length' => strlen($prompt)
        ]);
        
        try {
            $response = $this->callAPI($prompt);
            $enrichedData = $this->parseEnrichmentResponse($response);
            
            $this->logger->info('Contact enriched via Perplexity', [
                'email' => $email,
                'fields_found' => array_keys(array_filter($enrichedData))
            ]);
            
            return [
                'success' => true,
                'data' => $enrichedData,
                'raw_response' => $response,
                'raw_prompt' => $prompt
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Perplexity enrichment failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
                'raw_prompt' => $prompt
            ];
        }
    }
    
    /**
     * Build enrichment prompt for Perplexity
     * 
     * @param string $email
     * @param array $context
     * @return string
     */
    private function buildEnrichmentPrompt(string $email, array $context): string
    {
        $emailDomain = substr(strrchr($email, "@"), 1);
        
        $prompt = "Research and provide information about the person with email address: {$email}\n\n";
        
        if (!empty($context['subject'])) {
            $prompt .= "Email subject: {$context['subject']}\n";
        }
        
        if (!empty($context['body_preview'])) {
            $prompt .= "Email preview: " . substr($context['body_preview'], 0, 200) . "\n";
        }
        
        $prompt .= "\nProvide the following information in JSON format:\n";
        $prompt .= "{\n";
        $prompt .= '  "first_name": "string or null",'."\n";
        $prompt .= '  "last_name": "string or null",'."\n";
        $prompt .= '  "full_name": "string or null",'."\n";
        $prompt .= '  "company_name": "string or null (company associated with '.$emailDomain.')",'."\n";
        $prompt .= '  "company_url": "string or null (official website)",'."\n";
        $prompt .= '  "linkedin_url": "string or null (LinkedIn profile URL)",'."\n";
        $prompt .= '  "job_title": "string or null",'."\n";
        $prompt .= '  "confidence": 0.0-1.0 (how confident you are in this data)'."\n";
        $prompt .= "}\n\n";
        $prompt .= "Only return the JSON object, no additional text. If you cannot find information, use null for that field.";
        
        return $prompt;
    }
    
    /**
     * Call Perplexity API
     * 
     * @param string $prompt
     * @return string Raw response
     */
    private function callAPI(string $prompt): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a contact enrichment assistant. Always respond with valid JSON only, no markdown formatting or additional text.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.1,
            'max_tokens' => 500
        ];
        
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 2
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new \Exception("Perplexity API request failed: $curlError");
        }
        
        if ($httpCode !== 200) {
            throw new \Exception("Perplexity API error: HTTP $httpCode - $response");
        }
        
        return $response;
    }
    
    /**
     * Parse Perplexity API response and extract enrichment data
     * 
     * @param string $response Raw API response
     * @return array Parsed enrichment data
     */
    private function parseEnrichmentResponse(string $response): array
    {
        $data = json_decode($response, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid response format from Perplexity');
        }
        
        $content = $data['choices'][0]['message']['content'];
        
        // Remove markdown code blocks if present
        $content = preg_replace('/```json\s*|\s*```/', '', $content);
        $content = trim($content);
        
        $enrichedData = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to parse JSON from Perplexity: ' . json_last_error_msg());
        }
        
        // Normalize the data structure
        return [
            'first_name' => $enrichedData['first_name'] ?? null,
            'last_name' => $enrichedData['last_name'] ?? null,
            'full_name' => $enrichedData['full_name'] ?? null,
            'company_name' => $enrichedData['company_name'] ?? null,
            'company_url' => $enrichedData['company_url'] ?? null,
            'linkedin_url' => $enrichedData['linkedin_url'] ?? null,
            'job_title' => $enrichedData['job_title'] ?? null,
            'confidence' => $enrichedData['confidence'] ?? 0.5
        ];
    }
}
