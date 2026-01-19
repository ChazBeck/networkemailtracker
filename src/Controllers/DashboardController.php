<?php

namespace App\Controllers;

use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;

class DashboardController
{
    private ThreadRepository $threadRepo;
    private EmailRepository $emailRepo;
    
    public function __construct(
        ThreadRepository $threadRepo,
        EmailRepository $emailRepo
    ) {
        $this->threadRepo = $threadRepo;
        $this->emailRepo = $emailRepo;
    }
    
    /**
     * Get dashboard data
     * GET /api/dashboard
     */
    public function getData(): void
    {
        header('Content-Type: application/json');
        
        $threads = $this->threadRepo->getAllWithEmailCount();
        $recentEmails = $this->emailRepo->getRecent(50);
        
        echo json_encode([
            'threads' => $threads,
            'emails' => $recentEmails,
            'stats' => [
                'total_threads' => count($threads),
                'total_emails' => array_sum(array_column($threads, 'email_count'))
            ]
        ]);
    }
}
