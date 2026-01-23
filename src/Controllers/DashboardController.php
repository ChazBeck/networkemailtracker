<?php

namespace App\Controllers;

use App\Core\JsonResponse;
use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;
use App\Repositories\EnrichmentRepository;
use App\Tracking\Repositories\TrackingRepository;

class DashboardController
{
    private ThreadRepository $threadRepo;
    private EmailRepository $emailRepo;
    private EnrichmentRepository $enrichmentRepo;
    private TrackingRepository $trackingRepo;
    
    public function __construct(
        ThreadRepository $threadRepo,
        EmailRepository $emailRepo,
        EnrichmentRepository $enrichmentRepo,
        TrackingRepository $trackingRepo
    ) {
        $this->threadRepo = $threadRepo;
        $this->emailRepo = $emailRepo;
        $this->enrichmentRepo = $enrichmentRepo;
        $this->trackingRepo = $trackingRepo;
    }
    
    /**
     * Get dashboard data with enrichment and tracking
     * GET /api/dashboard
     */
    public function getData(): void
    {
        $threads = $this->threadRepo->getAllWithEmailCount();
        $recentEmails = $this->emailRepo->getRecent(50);
        
        // Get enrichment data for all threads
        $enrichments = [];
        foreach ($threads as $thread) {
            $enrichment = $this->enrichmentRepo->findByThreadId($thread['id']);
            if ($enrichment && $enrichment['enrichment_status'] === 'complete') {
                $enrichments[$thread['id']] = $enrichment;
            }
        }
        
        // Add tracking data to emails
        $emailsWithTracking = [];
        foreach ($recentEmails as $email) {
            $tracking = $this->trackingRepo->findByEmailId($email['id']);
            
            $emailData = $email;
            $emailData['tracking'] = null;
            
            if ($tracking && $tracking['status'] === 'active') {
                $emailData['tracking'] = [
                    'opened' => (int)$tracking['recipient_opens'] > 0,
                    'recipient_opens' => (int)$tracking['recipient_opens'],
                    'total_opens' => (int)$tracking['total_opens'],
                    'first_opened_at' => $tracking['first_opened_at'],
                    'last_opened_at' => $tracking['last_opened_at']
                ];
            }
            
            $emailsWithTracking[] = $emailData;
        }
        
        // Get tracking statistics
        $trackingStats = $this->trackingRepo->getTrackingStats();
        
        JsonResponse::success([
            'threads' => $threads,
            'emails' => $emailsWithTracking,
            'enrichments' => $enrichments,
            'stats' => [
                'total_threads' => count($threads),
                'total_emails' => array_sum(array_column($threads, 'email_count')),
                'enriched_contacts' => count($enrichments),
                'emails_tracked' => $trackingStats['total_tracked'],
                'emails_opened' => $trackingStats['emails_opened'],
                'open_rate' => $trackingStats['open_rate']
            ]
        ])->send();
    }
}
