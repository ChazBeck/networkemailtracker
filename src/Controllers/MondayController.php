<?php

namespace App\Controllers;

use App\Core\JsonResponse;
use App\Services\MondayService;
use App\Repositories\ThreadRepository;
use Psr\Log\LoggerInterface;

class MondayController
{
    private MondayService $mondayService;
    private ThreadRepository $threadRepo;
    private LoggerInterface $logger;
    
    public function __construct(
        MondayService $mondayService,
        ThreadRepository $threadRepo,
        LoggerInterface $logger
    ) {
        $this->mondayService = $mondayService;
        $this->threadRepo = $threadRepo;
        $this->logger = $logger;
    }
    
    /**
     * Sync a contact to Monday.com Networking Contacts board
     * POST /api/monday/sync-contact
     */
    public function syncContact(): void
    {
        try {
            // Get JSON body
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            if (!isset($data['email'])) {
                JsonResponse::error('Email is required', 400)->send();
                return;
            }
            
            $email = $data['email'];
            
            // Sync contact
            $result = $this->mondayService->syncContactByEmail($email);
            
            if ($result['success']) {
                JsonResponse::success([
                    'message' => 'Contact synced successfully',
                    'monday_item_id' => $result['monday_item_id'] ?? null,
                    'action' => $result['action'] ?? 'synced'
                ])->send();
            } else {
                JsonResponse::error($result['error'] ?? 'Failed to sync contact', 500)->send();
            }
        } catch (\Exception $e) {
            error_log("Error in syncContact: " . $e->getMessage());
            JsonResponse::error('Server error: ' . $e->getMessage(), 500)->send();
        }
    }
    
    /**
     * Sync all contacts to Monday.com
     * POST /api/monday/sync-all-contacts
     */
    public function syncAllContacts(): void
    {
        try {
            $contacts = $this->threadRepo->getContactsGroupedByEmail();
            
            $synced = 0;
            $failed = 0;
            $errors = [];
            
            foreach ($contacts as $contact) {
                try {
                    $result = $this->mondayService->syncContact($contact);
                    if ($result['success']) {
                        $synced++;
                    } else {
                        $failed++;
                        $errors[] = [
                            'email' => $contact['email'],
                            'error' => $result['error'] ?? 'Unknown error'
                        ];
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'email' => $contact['email'],
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            JsonResponse::success([
                'message' => "Synced $synced contacts, $failed failed",
                'synced' => $synced,
                'failed' => $failed,
                'errors' => $errors
            ])->send();
        } catch (\Exception $e) {
            error_log("Error in syncAllContacts: " . $e->getMessage());
            JsonResponse::error('Server error: ' . $e->getMessage(), 500)->send();
        }
    }
}
