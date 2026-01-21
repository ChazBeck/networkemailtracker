<?php

namespace App\Controllers;

use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;
use App\Repositories\EnrichmentRepository;

class DashboardController
{
    private ThreadRepository $threadRepo;
    private EmailRepository $emailRepo;
    private EnrichmentRepository $enrichmentRepo;
    
    public function __construct(
        ThreadRepository $threadRepo,
        EmailRepository $emailRepo,
        EnrichmentRepository $enrichmentRepo
    ) {
        $this->threadRepo = $threadRepo;
        $this->emailRepo = $emailRepo;
        $this->enrichmentRepo = $enrichmentRepo;
    }
    
    /**
     * Get dashboard data with enrichment
     * GET /api/dashboard
     */
    public function getData(): void
    {
        header('Content-Type: application/json');
        
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
        
        echo json_encode([
            'threads' => $threads,
            'emails' => $recentEmails,
            'enrichments' => $enrichments,
            'stats' => [
                'total_threads' => count($threads),
                'total_emails' => array_sum(array_column($threads, 'email_count')),
                'enriched_contacts' => count($enrichments)
            ]
        ]);
    }
}
