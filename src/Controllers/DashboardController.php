<?php

namespace App\Controllers;

use App\Core\JsonResponse;
use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;
use App\Repositories\EnrichmentRepository;
use App\Repositories\LinkTrackingRepository;

class DashboardController
{
    private ThreadRepository $threadRepo;
    private EmailRepository $emailRepo;
    private EnrichmentRepository $enrichmentRepo;
    private ?LinkTrackingRepository $linkTrackingRepo;
    
    public function __construct(
        ThreadRepository $threadRepo,
        EmailRepository $emailRepo,
        EnrichmentRepository $enrichmentRepo,
        ?LinkTrackingRepository $linkTrackingRepo = null
    ) {
        $this->threadRepo = $threadRepo;
        $this->emailRepo = $emailRepo;
        $this->enrichmentRepo = $enrichmentRepo;
        $this->linkTrackingRepo = $linkTrackingRepo;
    }
    
    /**
     * Get dashboard data with enrichment
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
        
        // Get link tracking data per email if available
        $linksByEmail = [];
        if ($this->linkTrackingRepo !== null) {
            foreach ($recentEmails as $email) {
                $links = $this->linkTrackingRepo->getByEmailId($email['id']);
                if (!empty($links)) {
                    $totalClicks = array_sum(array_column($links, 'clicks'));
                    $linksByEmail[$email['id']] = [
                        'count' => count($links),
                        'clicks' => $totalClicks,
                        'links' => $links
                    ];
                }
            }
        }
        
        JsonResponse::success([
            'threads' => $threads,
            'emails' => $recentEmails,
            'enrichments' => $enrichments,
            'links_by_email' => $linksByEmail,
            'stats' => [
                'total_threads' => count($threads),
                'total_emails' => array_sum(array_column($threads, 'email_count')),
                'enriched_contacts' => count($enrichments)
            ]
        ])->send();
    }
}
