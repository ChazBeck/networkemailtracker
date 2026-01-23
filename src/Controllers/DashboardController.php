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
        
        // Get link tracking stats if available
        $linkStats = null;
        if ($this->linkTrackingRepo !== null) {
            $linkStats = $this->linkTrackingRepo->getLinkStats();
        }
        
        JsonResponse::success([
            'threads' => $threads,
            'emails' => $recentEmails,
            'enrichments' => $enrichments,
            'link_stats' => $linkStats,
            'stats' => [
                'total_threads' => count($threads),
                'total_emails' => array_sum(array_column($threads, 'email_count')),
                'enriched_contacts' => count($enrichments),
                'tracked_links' => $linkStats['total_links'] ?? 0,
                'total_clicks' => $linkStats['total_clicks'] ?? 0
            ]
        ])->send();
    }
}
