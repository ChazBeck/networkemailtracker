<?php

namespace App\Controllers;

use App\Core\JsonResponse;
use App\Services\OutlookDraftService;
use Psr\Log\LoggerInterface;

class DraftController
{
    private OutlookDraftService $draftService;
    private LoggerInterface $logger;
    
    public function __construct(OutlookDraftService $draftService, LoggerInterface $logger)
    {
        $this->draftService = $draftService;
        $this->logger = $logger;
    }
    
    public function create(): void
    {
        try {
            $rawPayload = file_get_contents('php://input');
            $data = json_decode($rawPayload, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                JsonResponse::badRequest('Invalid JSON')->send();
                return;
            }
            
            $user = $data['user'] ?? '';
            $to = $data['to'] ?? '';
            $subject = $data['subject'] ?? '';
            $body = $data['body'] ?? '';
            
            if (empty($user)) {
                JsonResponse::badRequest('User is required')->send();
                return;
            }
            
            if (empty($to)) {
                JsonResponse::badRequest('To email is required')->send();
                return;
            }
            
            if (empty($subject)) {
                JsonResponse::badRequest('Subject is required')->send();
                return;
            }
            
            if (empty($body)) {
                JsonResponse::badRequest('Email body is required')->send();
                return;
            }
            
            $result = $this->draftService->createDraft($user, $to, $subject, $body);
            
            if ($result['success']) {
                JsonResponse::success([
                    'success' => true,
                    'message' => $result['message'],
                    'draft_id' => $result['draft_id'] ?? null
                ])->send();
            } else {
                JsonResponse::serverError($result['error'] ?? 'Failed to create draft')->send();
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error in draft controller', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            JsonResponse::serverError('Internal server error')->send();
        }
    }
}
