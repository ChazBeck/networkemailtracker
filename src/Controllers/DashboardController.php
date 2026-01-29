<?php

namespace App\Controllers;

use App\Core\JsonResponse;
use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;
use App\Repositories\EnrichmentRepository;
use App\Repositories\LinkTrackingRepository;
use App\Services\YourlsClient;

class DashboardController
{
    private ThreadRepository $threadRepo;
    private EmailRepository $emailRepo;
    private EnrichmentRepository $enrichmentRepo;
    private ?LinkTrackingRepository $linkTrackingRepo;
    private ?YourlsClient $yourlsClient;
    
    public function __construct(
        ThreadRepository $threadRepo,
        EmailRepository $emailRepo,
        EnrichmentRepository $enrichmentRepo,
        ?LinkTrackingRepository $linkTrackingRepo = null,
        ?YourlsClient $yourlsClient = null
    ) {
        $this->threadRepo = $threadRepo;
        $this->emailRepo = $emailRepo;
        $this->enrichmentRepo = $enrichmentRepo;
        $this->linkTrackingRepo = $linkTrackingRepo;
        $this->yourlsClient = $yourlsClient;
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
            // Sync clicks from YOURLS for all links before displaying
            $this->syncClicksFromYourls();
            
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
            'links_by_email' => (object)$linksByEmail, // Force object for empty array
            'stats' => [
                'total_threads' => count($threads),
                'total_emails' => array_sum(array_column($threads, 'email_count')),
                'enriched_contacts' => count($enrichments)
            ]
        ])->send();
    }
    
    /**
     * Sync click counts from YOURLS to database
     * Called on every dashboard load for real-time stats
     */
    private function syncClicksFromYourls(): void
    {
        if ($this->yourlsClient === null || $this->linkTrackingRepo === null) {
            return;
        }
        
        try {
            // Get all links that need syncing (updated more than 1 minute ago or never)
            $links = $this->linkTrackingRepo->getAllLinks();
            
            foreach ($links as $link) {
                try {
                    $stats = $this->yourlsClient->getStats($link['yourls_keyword']);
                    
                    if ($stats && isset($stats['clicks'])) {
                        $newClicks = (int)$stats['clicks'];
                        
                        // Only update if clicks changed
                        if ($newClicks != $link['clicks']) {
                            $this->linkTrackingRepo->updateClicks($link['yourls_keyword'], $newClicks);
                        }
                    }
                } catch (\Exception $e) {
                    // Silently skip errors for individual links
                    continue;
                }
            }
        } catch (\Exception $e) {
            // Don't break dashboard if YOURLS sync fails
        }
    }
    
    /**
     * Get full email details by ID
     * GET /api/emails/{id}
     */
    public function getEmail(int $emailId): void
    {
        $email = $this->emailRepo->findById($emailId);
        
        if (!$email) {
            JsonResponse::error('Email not found', 404)->send();
            return;
        }
        
        // Get link tracking data for this email
        $links = [];
        if ($this->linkTrackingRepo !== null) {
            $links = $this->linkTrackingRepo->getByEmailId($emailId);
        }
        
        JsonResponse::success([
            'email' => $email,
            'links' => $links
        ])->send();
    }
    
    /**
     * Get all contacts grouped by email address
     * GET /api/contacts
     */
    public function getContacts(): void
    {
        try {
            $contacts = $this->threadRepo->getContactsGroupedByEmail();
            
            JsonResponse::success([
                'contacts' => $contacts,
                'total' => count($contacts)
            ])->send();
        } catch (\PDOException $e) {
            error_log("Database error in getContacts: " . $e->getMessage());
            JsonResponse::error('Database error: ' . $e->getMessage(), 500)->send();
        } catch (\Exception $e) {
            error_log("Error in getContacts: " . $e->getMessage());
            JsonResponse::error('Server error: ' . $e->getMessage(), 500)->send();
        }
    }
}
