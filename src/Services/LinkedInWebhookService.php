<?php

namespace App\Services;

use App\Repositories\LinkedInThreadRepository;
use App\Repositories\LinkedInMessageRepository;
use App\Services\LinkedInUrlNormalizer;
use Psr\Log\LoggerInterface;

class LinkedInWebhookService
{
    private LinkedInThreadRepository $threadRepo;
    private LinkedInMessageRepository $messageRepo;
    private LinkedInUrlNormalizer $urlNormalizer;
    private LoggerInterface $logger;
    
    public function __construct(
        LinkedInThreadRepository $threadRepo,
        LinkedInMessageRepository $messageRepo,
        LinkedInUrlNormalizer $urlNormalizer,
        LoggerInterface $logger
    ) {
        $this->threadRepo = $threadRepo;
        $this->messageRepo = $messageRepo;
        $this->urlNormalizer = $urlNormalizer;
        $this->logger = $logger;
    }
    
    /**
     * Process LinkedIn message submission
     * 
     * @param array $payload Submission data
     * @return array ['success' => bool, 'message_id' => int, 'thread_id' => int, 'new_thread' => bool]
     */
    public function processLinkedInSubmission(array $payload): array
    {
        // 1. Validate required fields
        if (empty($payload['linkedin_url'])) {
            throw new \InvalidArgumentException('LinkedIn URL is required');
        }
        
        if (empty($payload['message_text'])) {
            throw new \InvalidArgumentException('Message text is required');
        }
        
        if (empty($payload['sender_email'])) {
            throw new \InvalidArgumentException('Sender email is required');
        }
        
        if (empty($payload['direction']) || !in_array($payload['direction'], ['outbound', 'inbound'])) {
            throw new \InvalidArgumentException('Direction must be "outbound" or "inbound"');
        }
        
        // 2. Normalize LinkedIn URL
        $normalizedUrl = $this->urlNormalizer->normalize($payload['linkedin_url']);
        
        if (!$this->urlNormalizer->isValid($normalizedUrl)) {
            throw new \InvalidArgumentException('Invalid LinkedIn URL format: ' . $payload['linkedin_url']);
        }
        
        $this->logger->info('Processing LinkedIn message submission', [
            'original_url' => $payload['linkedin_url'],
            'normalized_url' => $normalizedUrl,
            'sender' => $payload['sender_email'],
            'direction' => $payload['direction']
        ]);
        
        // 3. Find or create thread
        $thread = $this->threadRepo->findByLinkedInUrl($normalizedUrl);
        $newThread = false;
        
        if (!$thread) {
            // Create new thread with this sender as owner
            $threadId = $this->threadRepo->create([
                'external_linkedin_url' => $normalizedUrl,
                'owner_email' => $payload['sender_email'],
                'status' => 'Sent',
                'last_activity_at' => $payload['sent_at'] ?? date('Y-m-d H:i:s')
            ]);
            
            $newThread = true;
            
            $this->logger->info('New LinkedIn thread created', [
                'thread_id' => $threadId,
                'linkedin_url' => $normalizedUrl,
                'owner_email' => $payload['sender_email']
            ]);
        } else {
            $threadId = $thread['id'];
            
            // Update thread last activity and status
            $updateData = [
                'last_activity_at' => $payload['sent_at'] ?? date('Y-m-d H:i:s')
            ];
            
            // If this is an inbound message, update status to 'Responded'
            if ($payload['direction'] === 'inbound') {
                $updateData['status'] = 'Responded';
            }
            
            $this->threadRepo->update($threadId, $updateData);
            
            $this->logger->info('LinkedIn thread updated', [
                'thread_id' => $threadId,
                'status' => $updateData['status'] ?? $thread['status']
            ]);
        }
        
        // 4. Create message record
        $messageId = $this->messageRepo->create([
            'thread_id' => $threadId,
            'sender_email' => $payload['sender_email'],
            'direction' => $payload['direction'],
            'message_text' => $payload['message_text'],
            'sent_at' => $payload['sent_at'] ?? date('Y-m-d H:i:s')
        ]);
        
        $this->logger->info('LinkedIn message created', [
            'message_id' => $messageId,
            'thread_id' => $threadId,
            'direction' => $payload['direction']
        ]);
        
        return [
            'success' => true,
            'message_id' => $messageId,
            'thread_id' => $threadId,
            'new_thread' => $newThread,
            'normalized_url' => $normalizedUrl
        ];
    }
    
    /**
     * Get thread with messages
     * 
     * @param int $threadId
     * @return array|null ['thread' => array, 'messages' => array]
     */
    public function getThreadWithMessages(int $threadId): ?array
    {
        $thread = $this->threadRepo->findById($threadId);
        
        if (!$thread) {
            return null;
        }
        
        $messages = $this->messageRepo->findByThreadId($threadId);
        
        return [
            'thread' => $thread,
            'messages' => $messages
        ];
    }
    
    /**
     * Get all LinkedIn threads with message counts
     * 
     * @return array
     */
    public function getAllThreads(): array
    {
        return $this->threadRepo->getAllWithMessageCount();
    }
}
