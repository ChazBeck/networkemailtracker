<?php

namespace App\Services;

use App\Repositories\EnrichmentRepository;
use App\Repositories\ThreadRepository;
use Psr\Log\LoggerInterface;

class EnrichmentService
{
    private EnrichmentRepository $enrichmentRepo;
    private ThreadRepository $threadRepo;
    private PerplexityService $perplexityService;
    private LoggerInterface $logger;
    
    public function __construct(
        EnrichmentRepository $enrichmentRepo,
        ThreadRepository $threadRepo,
        PerplexityService $perplexityService,
        LoggerInterface $logger
    ) {
        $this->enrichmentRepo = $enrichmentRepo;
        $this->threadRepo = $threadRepo;
        $this->perplexityService = $perplexityService;
        $this->logger = $logger;
    }
    
    /**
     * Enrich contact for a thread (main entry point)
     * 
     * @param array $thread Thread data
     * @param bool $forceRefresh Force re-enrichment even if exists
     * @return array Result with enrichment data
     */
    public function enrichThread(array $thread, bool $forceRefresh = false): array
    {
        // Skip if no external email
        if (empty($thread['external_email'])) {
            return [
                'success' => false,
                'skipped' => true,
                'reason' => 'No external email to enrich'
            ];
        }
        
        // Check if already enriched
        if (!$forceRefresh) {
            $existing = $this->enrichmentRepo->findByThreadId($thread['id']);
            if ($existing && $existing['enrichment_status'] === 'complete') {
                $this->logger->debug('Contact already enriched', [
                    'thread_id' => $thread['id'],
                    'email' => $thread['external_email']
                ]);
                
                return [
                    'success' => true,
                    'already_enriched' => true,
                    'data' => $existing
                ];
            }
        }
        
        // Build context for enrichment
        $context = [
            'subject' => $thread['subject_normalized'] ?? '',
            'body_preview' => $thread['body_preview'] ?? ''
        ];
        
        // Call Perplexity API
        $result = $this->perplexityService->enrichContact($thread['external_email'], $context);
        
        if ($result['success']) {
            // Save enrichment data
            $enrichmentId = $this->saveEnrichment($thread, $result);
            
            return [
                'success' => true,
                'enrichment_id' => $enrichmentId,
                'data' => $result['data']
            ];
        } else {
            // Save failed attempt
            $this->saveFailedEnrichment($thread, $result);
            
            return [
                'success' => false,
                'error' => $result['error']
            ];
        }
    }
    
    /**
     * Save successful enrichment
     * 
     * @param array $thread
     * @param array $result Perplexity result
     * @return int Enrichment ID
     */
    private function saveEnrichment(array $thread, array $result): int
    {
        $existing = $this->enrichmentRepo->findByThreadId($thread['id']);
        
        $enrichmentData = [
            'thread_id' => $thread['id'],
            'external_email' => $thread['external_email'],
            'first_name' => $result['data']['first_name'],
            'last_name' => $result['data']['last_name'],
            'full_name' => $result['data']['full_name'],
            'company_name' => $result['data']['company_name'],
            'company_url' => $result['data']['company_url'],
            'linkedin_url' => $result['data']['linkedin_url'],
            'job_title' => $result['data']['job_title'],
            'enrichment_source' => 'perplexity',
            'enrichment_status' => 'complete',
            'confidence_score' => $result['data']['confidence'] ?? null,
            'raw_prompt' => $result['raw_prompt'] ?? null,
            'raw_response' => $result['raw_response'] ?? null,
            'enriched_at' => date('Y-m-d H:i:s')
        ];
        
        if ($existing) {
            // Update existing
            $this->enrichmentRepo->update($thread['id'], $enrichmentData);
            return $existing['id'];
        } else {
            // Create new
            return $this->enrichmentRepo->create($enrichmentData);
        }
    }
    
    /**
     * Save failed enrichment attempt
     * 
     * @param array $thread
     * @param array $result Failed result
     * @return void
     */
    private function saveFailedEnrichment(array $thread, array $result): void
    {
        $existing = $this->enrichmentRepo->findByThreadId($thread['id']);
        
        $enrichmentData = [
            'enrichment_status' => 'failed',
            'error_message' => $result['error'] ?? 'Unknown error',
            'raw_prompt' => $result['raw_prompt'] ?? null
        ];
        
        if ($existing) {
            $this->enrichmentRepo->update($thread['id'], $enrichmentData);
        } else {
            $enrichmentData['thread_id'] = $thread['id'];
            $enrichmentData['external_email'] = $thread['external_email'];
            $enrichmentData['enrichment_source'] = 'perplexity';
            $this->enrichmentRepo->create($enrichmentData);
        }
    }
    
    /**
     * Get enrichment for a thread
     * 
     * @param int $threadId
     * @return array|null
     */
    public function getEnrichment(int $threadId): ?array
    {
        return $this->enrichmentRepo->findByThreadId($threadId);
    }
    
    /**
     * Batch enrich multiple threads
     * 
     * @param int $limit Maximum number to enrich
     * @return array Results
     */
    public function batchEnrich(int $limit = 10): array
    {
        $this->logger->info('Starting batch enrichment', ['limit' => $limit]);
        
        // Get threads with external emails that aren't enriched yet
        $stmt = $this->threadRepo->db->query("
            SELECT t.* 
            FROM threads t
            LEFT JOIN contact_enrichment ce ON t.id = ce.thread_id
            WHERE t.external_email IS NOT NULL 
            AND t.external_email != ''
            AND (ce.id IS NULL OR ce.enrichment_status = 'failed')
            ORDER BY t.created_at DESC
            LIMIT $limit
        ");
        
        $threads = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $results = [
            'total' => count($threads),
            'enriched' => 0,
            'failed' => 0,
            'skipped' => 0
        ];
        
        foreach ($threads as $thread) {
            $result = $this->enrichThread($thread);
            
            if ($result['success']) {
                $results['enriched']++;
            } elseif ($result['skipped'] ?? false) {
                $results['skipped']++;
            } else {
                $results['failed']++;
            }
            
            // Rate limiting - don't hammer Perplexity API
            usleep(500000); // 0.5 second delay between requests
        }
        
        $this->logger->info('Batch enrichment complete', $results);
        
        return $results;
    }
}
