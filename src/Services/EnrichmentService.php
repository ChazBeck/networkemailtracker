<?php

namespace App\Services;

use App\Repositories\EnrichmentRepository;
use App\Repositories\ThreadRepository;
use App\Repositories\LinkedInThreadRepository;
use Psr\Log\LoggerInterface;

class EnrichmentService
{
    private EnrichmentRepository $enrichmentRepo;
    private ThreadRepository $threadRepo;
    private ?LinkedInThreadRepository $linkedInThreadRepo;
    private PerplexityService $perplexityService;
    private ?MondayService $mondayService;
    private LoggerInterface $logger;
    
    public function __construct(
        EnrichmentRepository $enrichmentRepo,
        ThreadRepository $threadRepo,
        PerplexityService $perplexityService,
        LoggerInterface $logger,
        ?LinkedInThreadRepository $linkedInThreadRepo = null,
        ?MondayService $mondayService = null
    ) {
        $this->enrichmentRepo = $enrichmentRepo;
        $this->threadRepo = $threadRepo;
        $this->linkedInThreadRepo = $linkedInThreadRepo;
        $this->perplexityService = $perplexityService;
        $this->mondayService = $mondayService;
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
        
        // Check if THIS thread already has enrichment
        if (!$forceRefresh) {
            $existingForThread = $this->enrichmentRepo->findByThreadId($thread['id']);
            if ($existingForThread && $existingForThread['enrichment_status'] === 'complete') {
                $this->logger->debug('Thread already enriched', [
                    'thread_id' => $thread['id'],
                    'email' => $thread['external_email']
                ]);
                
                return [
                    'success' => true,
                    'already_enriched' => true,
                    'data' => $existingForThread
                ];
            }
        }
        
        // Check if this EMAIL has been enriched before (in any other thread)
        if (!$forceRefresh) {
            $existingForEmail = $this->enrichmentRepo->findByEmail($thread['external_email']);
            if ($existingForEmail && $existingForEmail['enrichment_status'] === 'complete') {
                $this->logger->info('Email already enriched in another thread, copying data', [
                    'thread_id' => $thread['id'],
                    'email' => $thread['external_email'],
                    'source_thread_id' => $existingForEmail['thread_id']
                ]);
                
                // Copy the enrichment data to this thread
                $enrichmentId = $this->copyEnrichmentToThread($thread, $existingForEmail);
                
                return [
                    'success' => true,
                    'copied_from_email' => true,
                    'enrichment_id' => $enrichmentId,
                    'data' => $existingForEmail
                ];
            }
        }
        
        // No existing enrichment found - call Perplexity API
        $context = [
            'subject' => $thread['subject_normalized'] ?? '',
            'body_preview' => $thread['body_preview'] ?? ''
        ];
        
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
     * Copy enrichment data from one thread to another
     * Used when the same email appears in multiple threads
     * 
     * @param array $thread Target thread
     * @param array $sourceEnrichment Source enrichment data
     * @return int New enrichment ID
     */
    private function copyEnrichmentToThread(array $thread, array $sourceEnrichment): int
    {
        $enrichmentData = [
            'thread_id' => $thread['id'],
            'external_email' => $thread['external_email'],
            'first_name' => $sourceEnrichment['first_name'],
            'last_name' => $sourceEnrichment['last_name'],
            'full_name' => $sourceEnrichment['full_name'],
            'company_name' => $sourceEnrichment['company_name'],
            'company_url' => $sourceEnrichment['company_url'],
            'linkedin_url' => $sourceEnrichment['linkedin_url'],
            'job_title' => $sourceEnrichment['job_title'],
            'enrichment_source' => 'perplexity',
            'enrichment_status' => 'complete',
            'confidence_score' => $sourceEnrichment['confidence_score'],
            'raw_prompt' => $sourceEnrichment['raw_prompt'],
            'raw_response' => $sourceEnrichment['raw_response'],
            'enriched_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->enrichmentRepo->create($enrichmentData);
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
        
        $enrichmentId = null;
        
        if ($existing) {
            // Update existing
            $this->enrichmentRepo->update($thread['id'], $enrichmentData);
            $enrichmentId = $existing['id'];
        } else {
            // Create new
            $enrichmentId = $this->enrichmentRepo->create($enrichmentData);
        }
        
        // Trigger BizDev Pipeline sync after successful enrichment
        if ($this->mondayService && $enrichmentId) {
            try {
                // Get full enrichment record with thread data for internal sender
                $enrichment = $this->enrichmentRepo->findById($enrichmentId);
                if ($enrichment) {
                    // Add internal sender from thread
                    $enrichment['internal_sender_email'] = $thread['internal_sender_email'] ?? null;
                    $enrichment['last_activity_at'] = $thread['last_activity_at'] ?? $thread['updated_at'] ?? null;
                    
                    $this->mondayService->syncToBizDevPipeline($enrichment);
                    $this->logger->info('Triggered BizDev Pipeline sync for enrichment', [
                        'enrichment_id' => $enrichmentId,
                        'external_email' => $thread['external_email']
                    ]);
                }
            } catch (\Exception $e) {
                // Log but don't fail enrichment if BizDev sync fails
                $this->logger->warning('Failed to sync to BizDev Pipeline', [
                    'enrichment_id' => $enrichmentId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $enrichmentId;
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
        $threads = $this->threadRepo->getThreadsNeedingEnrichment($limit);
        
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
    
    /**
     * Enrich contact for a LinkedIn thread
     * 
     * @param array $linkedInThread LinkedIn thread data
     * @param bool $forceRefresh Force re-enrichment even if exists
     * @return array Result with enrichment data
     */
    public function enrichLinkedInThread(array $linkedInThread, bool $forceRefresh = false): array
    {
        // Skip if no LinkedIn URL
        if (empty($linkedInThread['external_linkedin_url'])) {
            return [
                'success' => false,
                'skipped' => true,
                'reason' => 'No LinkedIn URL to enrich'
            ];
        }
        
        // Check if THIS LinkedIn thread already has enrichment
        if (!$forceRefresh) {
            $existingForThread = $this->enrichmentRepo->findByLinkedInThreadId($linkedInThread['id']);
            if ($existingForThread && $existingForThread['enrichment_status'] === 'complete') {
                $this->logger->debug('LinkedIn thread already enriched', [
                    'linkedin_thread_id' => $linkedInThread['id'],
                    'linkedin_url' => $linkedInThread['external_linkedin_url']
                ]);
                
                return [
                    'success' => true,
                    'already_enriched' => true,
                    'data' => $existingForThread
                ];
            }
        }
        
        // Check if this LinkedIn URL has been enriched before (in any thread - email or LinkedIn)
        if (!$forceRefresh) {
            $existingForLinkedIn = $this->enrichmentRepo->findByLinkedInUrl($linkedInThread['external_linkedin_url']);
            if ($existingForLinkedIn && $existingForLinkedIn['enrichment_status'] === 'complete') {
                $this->logger->info('LinkedIn URL already enriched, copying data', [
                    'linkedin_thread_id' => $linkedInThread['id'],
                    'linkedin_url' => $linkedInThread['external_linkedin_url'],
                    'source_enrichment_id' => $existingForLinkedIn['id']
                ]);
                
                // Copy the enrichment data to this LinkedIn thread
                $enrichmentId = $this->copyEnrichmentToLinkedInThread($linkedInThread, $existingForLinkedIn);
                
                return [
                    'success' => true,
                    'copied_from_linkedin' => true,
                    'enrichment_id' => $enrichmentId,
                    'data' => $existingForLinkedIn
                ];
            }
        }
        
        // No existing enrichment found - call Perplexity API with LinkedIn URL
        $context = [
            'linkedin_url' => $linkedInThread['external_linkedin_url']
        ];
        
        $result = $this->perplexityService->enrichContactFromLinkedIn(
            $linkedInThread['external_linkedin_url'], 
            $context
        );
        
        if ($result['success']) {
            // Save enrichment data
            $enrichmentId = $this->saveLinkedInEnrichment($linkedInThread, $result);
            
            return [
                'success' => true,
                'enrichment_id' => $enrichmentId,
                'data' => $result['data']
            ];
        } else {
            // Save failed attempt
            $this->saveFailedLinkedInEnrichment($linkedInThread, $result);
            
            return [
                'success' => false,
                'error' => $result['error']
            ];
        }
    }
    
    /**
     * Copy enrichment data to a LinkedIn thread
     * 
     * @param array $linkedInThread Target LinkedIn thread
     * @param array $sourceEnrichment Source enrichment data
     * @return int New enrichment ID
     */
    private function copyEnrichmentToLinkedInThread(array $linkedInThread, array $sourceEnrichment): int
    {
        $enrichmentData = [
            'linkedin_thread_id' => $linkedInThread['id'],
            'external_linkedin_url' => $linkedInThread['external_linkedin_url'],
            'first_name' => $sourceEnrichment['first_name'],
            'last_name' => $sourceEnrichment['last_name'],
            'full_name' => $sourceEnrichment['full_name'],
            'company_name' => $sourceEnrichment['company_name'],
            'company_url' => $sourceEnrichment['company_url'],
            'linkedin_url' => $sourceEnrichment['linkedin_url'],
            'job_title' => $sourceEnrichment['job_title'],
            'enrichment_source' => 'perplexity',
            'enrichment_status' => 'complete',
            'confidence_score' => $sourceEnrichment['confidence_score'],
            'raw_prompt' => $sourceEnrichment['raw_prompt'],
            'raw_response' => $sourceEnrichment['raw_response'],
            'enriched_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->enrichmentRepo->create($enrichmentData);
    }
    
    /**
     * Save successful LinkedIn enrichment
     * 
     * @param array $linkedInThread
     * @param array $result Perplexity result
     * @return int Enrichment ID
     */
    private function saveLinkedInEnrichment(array $linkedInThread, array $result): int
    {
        $existing = $this->enrichmentRepo->findByLinkedInThreadId($linkedInThread['id']);
        
        $enrichmentData = [
            'linkedin_thread_id' => $linkedInThread['id'],
            'external_linkedin_url' => $linkedInThread['external_linkedin_url'],
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
        
        $enrichmentId = null;
        
        if ($existing) {
            // Update existing
            $this->enrichmentRepo->updateByLinkedInThreadId($linkedInThread['id'], $enrichmentData);
            $enrichmentId = $existing['id'];
        } else {
            // Create new
            $enrichmentId = $this->enrichmentRepo->create($enrichmentData);
        }
        
        // Trigger BizDev Pipeline sync after successful LinkedIn enrichment
        if ($this->mondayService && $enrichmentId) {
            try {
                // Get full enrichment record with LinkedIn thread data
                $enrichment = $this->enrichmentRepo->findById($enrichmentId);
                if ($enrichment) {
                    // Add internal sender from LinkedIn thread (stored as owner_email)
                    $enrichment['internal_sender_email'] = $linkedInThread['owner_email'] ?? null;
                    $enrichment['last_activity_at'] = $linkedInThread['last_activity_at'] ?? $linkedInThread['updated_at'] ?? null;
                    
                    $this->mondayService->syncToBizDevPipeline($enrichment);
                    $this->logger->info('Triggered BizDev Pipeline sync for LinkedIn enrichment', [
                        'enrichment_id' => $enrichmentId,
                        'external_linkedin_url' => $linkedInThread['external_linkedin_url']
                    ]);
                }
            } catch (\Exception $e) {
                // Log but don't fail enrichment if BizDev sync fails
                $this->logger->warning('Failed to sync LinkedIn to BizDev Pipeline', [
                    'enrichment_id' => $enrichmentId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $enrichmentId;
    }
    
    /**
     * Save failed LinkedIn enrichment attempt
     * 
     * @param array $linkedInThread
     * @param array $result Failed result
     * @return void
     */
    private function saveFailedLinkedInEnrichment(array $linkedInThread, array $result): void
    {
        $existing = $this->enrichmentRepo->findByLinkedInThreadId($linkedInThread['id']);
        
        $enrichmentData = [
            'enrichment_status' => 'failed',
            'error_message' => $result['error'] ?? 'Unknown error',
            'raw_prompt' => $result['raw_prompt'] ?? null
        ];
        
        if ($existing) {
            $this->enrichmentRepo->updateByLinkedInThreadId($linkedInThread['id'], $enrichmentData);
        } else {
            $enrichmentData['linkedin_thread_id'] = $linkedInThread['id'];
            $enrichmentData['external_linkedin_url'] = $linkedInThread['external_linkedin_url'];
            $enrichmentData['enrichment_source'] = 'perplexity';
            $this->enrichmentRepo->create($enrichmentData);
        }
    }
    
    /**
     * Get enrichment for a LinkedIn thread
     * 
     * @param int $linkedInThreadId
     * @return array|null
     */
    public function getLinkedInEnrichment(int $linkedInThreadId): ?array
    {
        return $this->enrichmentRepo->findByLinkedInThreadId($linkedInThreadId);
    }
}
