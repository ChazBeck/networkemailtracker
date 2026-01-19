<?php

namespace App\Controllers;

use App\Services\WebhookService;
use App\Services\MondayService;
use App\Services\PayloadNormalizer;
use Psr\Log\LoggerInterface;

class WebhookController
{
    private WebhookService $webhookService;
    private ?MondayService $mondayService;
    private LoggerInterface $logger;
    
    public function __construct(
        WebhookService $webhookService,
        LoggerInterface $logger,
        ?MondayService $mondayService = null
    ) {
        $this->webhookService = $webhookService;
        $this->logger = $logger;
        $this->mondayService = $mondayService;
    }
    
    /**
     * Handle incoming email webhook
     * POST /api/webhook/email
     */
    public function ingest(): void
    {
        try {
            // Get raw payload
            $rawPayload = file_get_contents('php://input');
            
            // Parse JSON
            $payload = json_decode($rawPayload, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Invalid JSON in webhook', [
                    'error' => json_last_error_msg(),
                    'payload_preview' => substr($rawPayload, 0, 200)
                ]);
                
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON'
                ]);
                return;
            }
            
            // Normalize Power Automate payload to internal format
            $normalizedPayload = PayloadNormalizer::normalize($payload);
            
            // Log webhook received
            $this->logger->info('Webhook received', [
                'event_type' => $normalizedPayload['event_type'] ?? 'unknown',
                'graph_message_id' => $normalizedPayload['data']['graph_message_id'] ?? null,
                'internet_message_id' => $normalizedPayload['data']['internet_message_id'] ?? null,
                'from_email' => $normalizedPayload['data']['from_email'] ?? null
            ]);
            
            // Process webhook
            $result = $this->webhookService->processEmailWebhook($normalizedPayload);
            
            // Auto-sync to Monday.com if enabled and not a duplicate
            if ($this->mondayService && !$result['duplicate']) {
                try {
                    // Get the full thread data
                    $thread = $this->webhookService->getThreadById($result['thread_id']);
                    if ($thread && $thread['external_email']) {
                        $this->mondayService->syncThread($thread);
                        $this->logger->info('Auto-synced thread to Monday', [
                            'thread_id' => $result['thread_id']
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log but don't fail webhook if Monday sync fails
                    $this->logger->warning('Monday sync failed', [
                        'thread_id' => $result['thread_id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Return 202 Accepted (webhook processed)
            http_response_code(202);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'email_id' => $result['email_id'],
                'thread_id' => $result['thread_id'],
                'duplicate' => $result['duplicate'],
                'message' => $result['duplicate'] ? 'Email already processed' : 'Email processed successfully'
            ]);
            
        } catch (\PDOException $e) {
            $this->logger->error('Database error processing webhook', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Database error'
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error processing webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error'
            ]);
        }
    }
}
