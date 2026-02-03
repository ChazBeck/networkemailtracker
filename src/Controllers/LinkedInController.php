<?php

namespace App\Controllers;

use App\Core\JsonResponse;
use App\Services\LinkedInWebhookService;
use App\Services\EnrichmentService;
use App\Services\MondayService;
use App\Repositories\LinkedInThreadRepository;
use App\Repositories\LinkedInMessageRepository;
use Psr\Log\LoggerInterface;

class LinkedInController
{
    private LinkedInWebhookService $webhookService;
    private EnrichmentService $enrichmentService;
    private MondayService $mondayService;
    private LinkedInThreadRepository $threadRepo;
    private LinkedInMessageRepository $messageRepo;
    private LoggerInterface $logger;
    
    public function __construct(
        LinkedInWebhookService $webhookService,
        EnrichmentService $enrichmentService,
        MondayService $mondayService,
        LinkedInThreadRepository $threadRepo,
        LinkedInMessageRepository $messageRepo,
        LoggerInterface $logger
    ) {
        $this->webhookService = $webhookService;
        $this->enrichmentService = $enrichmentService;
        $this->mondayService = $mondayService;
        $this->threadRepo = $threadRepo;
        $this->messageRepo = $messageRepo;
        $this->logger = $logger;
    }
    
    /**
     * Submit a LinkedIn message
     * POST /api/linkedin/submit
     * 
     * @return void
     */
    public function submitMessage(): void
    {
        try {
            // Get JSON payload
            $input = file_get_contents('php://input');
            $payload = json_decode($input, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                JsonResponse::error('Invalid JSON payload', 400)->send();
                return;
            }
            
            // Validate required fields
            $requiredFields = ['linkedin_url', 'message_text', 'sender_email', 'direction'];
            foreach ($requiredFields as $field) {
                if (empty($payload[$field])) {
                    JsonResponse::error("Missing required field: $field", 400)->send();
                    return;
                }
            }
            
            // Process the submission
            $result = $this->webhookService->processLinkedInSubmission($payload);
            
            if (!$result['success']) {
                JsonResponse::error($result['error'] ?? 'Failed to process submission', 500)->send();
                return;
            }
            
            // Auto-enrich if it's a new thread
            if ($result['new_thread']) {
                $thread = $this->threadRepo->findById($result['thread_id']);
                if ($thread) {
                    $this->logger->info('Auto-enriching new LinkedIn thread', [
                        'thread_id' => $result['thread_id']
                    ]);
                    
                    $enrichmentResult = $this->enrichmentService->enrichLinkedInThread($thread);
                    
                    if ($enrichmentResult['success']) {
                        $this->logger->info('LinkedIn thread enriched successfully', [
                            'thread_id' => $result['thread_id']
                        ]);
                        
                        // Auto-sync to Monday.com
                        $this->logger->info('Auto-syncing LinkedIn thread to Monday.com', [
                            'thread_id' => $result['thread_id']
                        ]);
                        
                        $syncResult = $this->mondayService->syncLinkedInThread($thread);
                        
                        if ($syncResult['success']) {
                            $this->logger->info('LinkedIn thread synced to Monday.com', [
                                'thread_id' => $result['thread_id'],
                                'monday_item_id' => $syncResult['monday_item_id']
                            ]);
                        }
                    }
                }
            }
            
            JsonResponse::success([
                'message_id' => $result['message_id'],
                'thread_id' => $result['thread_id'],
                'normalized_url' => $result['normalized_url'],
                'new_thread' => $result['new_thread']
            ])->send();
            
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Invalid LinkedIn submission', [
                'error' => $e->getMessage()
            ]);
            JsonResponse::error($e->getMessage(), 400)->send();
        } catch (\Exception $e) {
            $this->logger->error('LinkedIn submission failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            JsonResponse::error('Internal server error', 500)->send();
        }
    }
    
    /**
     * View a LinkedIn thread with messages
     * GET /api/linkedin/thread/{id}
     * 
     * @param int $threadId
     * @return void
     */
    public function viewThread(int $threadId): void
    {
        try {
            $data = $this->webhookService->getThreadWithMessages($threadId);
            
            if (!$data) {
                JsonResponse::error('Thread not found', 404)->send();
                return;
            }
            
            // Get enrichment data
            $enrichment = $this->enrichmentService->getLinkedInEnrichment($threadId);
            
            JsonResponse::success([
                'thread' => $data['thread'],
                'messages' => $data['messages'],
                'enrichment' => $enrichment
            ])->send();
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to load LinkedIn thread', [
                'thread_id' => $threadId,
                'error' => $e->getMessage()
            ]);
            JsonResponse::error('Internal server error', 500)->send();
        }
    }
    
    /**
     * Get all LinkedIn threads
     * GET /api/linkedin/threads
     * 
     * @return void
     */
    public function getThreads(): void
    {
        try {
            $threads = $this->webhookService->getAllThreads();
            
            JsonResponse::success([
                'threads' => $threads,
                'total' => count($threads)
            ])->send();
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to load LinkedIn threads', [
                'error' => $e->getMessage()
            ]);
            JsonResponse::error('Internal server error', 500)->send();
        }
    }
}
