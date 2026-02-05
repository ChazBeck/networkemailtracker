<?php

namespace App\Controllers;

use App\Core\JsonResponse;
use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;
use App\Repositories\EnrichmentRepository;
use App\Repositories\LinkTrackingRepository;
use App\Repositories\LinkedInThreadRepository;
use App\Repositories\LinkedInMessageRepository;
use App\Services\YourlsClient;

class DashboardController
{
    private ThreadRepository $threadRepo;
    private EmailRepository $emailRepo;
    private EnrichmentRepository $enrichmentRepo;
    private ?LinkTrackingRepository $linkTrackingRepo;
    private ?LinkedInThreadRepository $linkedInThreadRepo;
    private ?LinkedInMessageRepository $linkedInMessageRepo;
    private ?YourlsClient $yourlsClient;
    
    public function __construct(
        ThreadRepository $threadRepo,
        EmailRepository $emailRepo,
        EnrichmentRepository $enrichmentRepo,
        ?LinkTrackingRepository $linkTrackingRepo = null,
        ?YourlsClient $yourlsClient = null,
        ?LinkedInThreadRepository $linkedInThreadRepo = null,
        ?LinkedInMessageRepository $linkedInMessageRepo = null
    ) {
        $this->threadRepo = $threadRepo;
        $this->emailRepo = $emailRepo;
        $this->enrichmentRepo = $enrichmentRepo;
        $this->linkTrackingRepo = $linkTrackingRepo;
        $this->yourlsClient = $yourlsClient;
        $this->linkedInThreadRepo = $linkedInThreadRepo;
        $this->linkedInMessageRepo = $linkedInMessageRepo;
    }
    
    /**
     * Get dashboard data with enrichment
     * GET /api/dashboard
     */
    public function getData(): void
    {
        try {
            // Get email threads
            $emailThreads = $this->threadRepo->getAllWithEmailCount();
            $recentEmails = $this->emailRepo->getRecent(50);
            
            // Get LinkedIn threads if available
            $linkedInThreads = [];
            $linkedInMessages = [];
            if ($this->linkedInThreadRepo) {
                $linkedInThreads = $this->linkedInThreadRepo->getAllWithMessageCount();
                
                // Get recent LinkedIn messages for display
                if ($this->linkedInMessageRepo) {
                    foreach ($linkedInThreads as $thread) {
                        $messages = $this->linkedInMessageRepo->findByThreadId($thread['id']);
                        foreach ($messages as $message) {
                            $linkedInMessages[] = $message;
                        }
                    }
                    // Sort by sent_at desc, limit to 50
                    usort($linkedInMessages, fn($a, $b) => strtotime($b['sent_at']) - strtotime($a['sent_at']));
                    $linkedInMessages = array_slice($linkedInMessages, 0, 50);
                }
            }
            
            // Combine threads with channel indicator
            $unifiedThreads = [];
            
            // Add email threads
            foreach ($emailThreads as $thread) {
                $unifiedThreads[] = array_merge($thread, [
                    'channel' => 'email',
                    'contact_identifier' => $thread['external_email'],
                    'owner' => $thread['internal_sender_email']
                ]);
            }
            
            // Add LinkedIn threads
            foreach ($linkedInThreads as $thread) {
                $unifiedThreads[] = array_merge($thread, [
                    'channel' => 'linkedin',
                    'contact_identifier' => $thread['external_linkedin_url'],
                    'owner' => $thread['owner_email'],
                    'email_count' => $thread['message_count'] // Normalize field name
                ]);
            }
            
            // Sort unified threads by last_activity_at desc
            usort($unifiedThreads, fn($a, $b) => 
                strtotime($b['last_activity_at'] ?? $b['created_at']) - 
                strtotime($a['last_activity_at'] ?? $a['created_at'])
            );
            
            // Get enrichment data for all threads
            $enrichments = [];
            foreach ($emailThreads as $thread) {
                $enrichment = $this->enrichmentRepo->findByThreadId($thread['id']);
                if ($enrichment && $enrichment['enrichment_status'] === 'complete') {
                    $enrichments['email_' . $thread['id']] = $enrichment;
                }
            }
            foreach ($linkedInThreads as $thread) {
                $enrichment = $this->enrichmentRepo->findByLinkedInThreadId($thread['id']);
                if ($enrichment && $enrichment['enrichment_status'] === 'complete') {
                    $enrichments['linkedin_' . $thread['id']] = $enrichment;
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
                'threads' => $unifiedThreads,
                'emails' => $recentEmails,
                'linkedin_messages' => $linkedInMessages,
                'enrichments' => $enrichments,
                'links_by_email' => (object)$linksByEmail,
                'stats' => [
                    'total_threads' => count($unifiedThreads),
                    'email_threads' => count($emailThreads),
                    'linkedin_threads' => count($linkedInThreads),
                    'total_emails' => array_sum(array_column($emailThreads, 'email_count')),
                    'total_linkedin_messages' => array_sum(array_column($linkedInThreads, 'message_count')),
                    'enriched_contacts' => count($enrichments)
                ]
            ])->send();
        } catch (\PDOException $e) {
            error_log("Database error in getData: " . $e->getMessage());
            JsonResponse::error('Database error: ' . $e->getMessage(), 500)->send();
        } catch (\Exception $e) {
            error_log("Error in getData: " . $e->getMessage());
            JsonResponse::error('Server error: ' . $e->getMessage(), 500)->send();
        }
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
            // Get email contacts
            $emailContacts = $this->threadRepo->getContactsGroupedByEmail();
            
            // Get LinkedIn contacts if available
            $linkedInContacts = [];
            if ($this->linkedInThreadRepo) {
                $linkedInContacts = $this->linkedInThreadRepo->getContactsForDashboard();
            }
            
            // Merge contacts and remove duplicates (prefer email contacts if same person)
            $allContacts = array_merge($emailContacts, $linkedInContacts);
            
            // Sort by last_contact desc
            usort($allContacts, fn($a, $b) => strtotime($b['last_contact']) - strtotime($a['last_contact']));
            
            JsonResponse::success([
                'contacts' => $allContacts,
                'total' => count($allContacts)
            ])->send();
        } catch (\PDOException $e) {
            error_log("Database error in getContacts: " . $e->getMessage());
            JsonResponse::error('Database error: ' . $e->getMessage(), 500)->send();
        } catch (\Exception $e) {
            error_log("Error in getContacts: " . $e->getMessage());
            JsonResponse::error('Server error: ' . $e->getMessage(), 500)->send();
        }
    }
    
    /**
     * Update enrichment data
     * PUT /api/enrichment/{id}
     */
    public function updateEnrichment(int $enrichmentId): void
    {
        try {
            // Get JSON body
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            if (!$data) {
                JsonResponse::error('Invalid JSON data', 400)->send();
                return;
            }
            
            // Validate enrichment exists
            $enrichment = $this->enrichmentRepo->findById($enrichmentId);
            if (!$enrichment) {
                JsonResponse::error('Enrichment not found', 404)->send();
                return;
            }
            
            // Update enrichment
            $success = $this->enrichmentRepo->updateById($enrichmentId, $data);
            
            if ($success) {
                JsonResponse::success([
                    'message' => 'Enrichment updated successfully',
                    'enrichment_id' => $enrichmentId
                ])->send();
            } else {
                JsonResponse::error('Failed to update enrichment', 500)->send();
            }
        } catch (\PDOException $e) {
            error_log("Database error in updateEnrichment: " . $e->getMessage());
            JsonResponse::error('Database error: ' . $e->getMessage(), 500)->send();
        } catch (\Exception $e) {
            error_log("Error in updateEnrichment: " . $e->getMessage());
            JsonResponse::error('Server error: ' . $e->getMessage(), 500)->send();
        }
    }
}
