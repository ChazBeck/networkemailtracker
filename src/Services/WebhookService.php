<?php

namespace App\Services;

use App\Repositories\ThreadRepository;
use App\Repositories\EmailRepository;
use Psr\Log\LoggerInterface;

class WebhookService
{
    private ThreadRepository $threadRepo;
    private EmailRepository $emailRepo;
    private LoggerInterface $logger;
    
    public function __construct(
        ThreadRepository $threadRepo,
        EmailRepository $emailRepo,
        LoggerInterface $logger
    ) {
        $this->threadRepo = $threadRepo;
        $this->emailRepo = $emailRepo;
        $this->logger = $logger;
    }
    
    /**
     * Process incoming webhook payload
     * 
     * @param array $payload Webhook data
     * @return array ['success' => bool, 'email_id' => int|null, 'thread_id' => int, 'duplicate' => bool]
     */
    public function processEmailWebhook(array $payload): array
    {
        $data = $payload['data'] ?? $payload;
        
        // 1. Check for duplicate using graph_message_id (primary) or internet_message_id (fallback)
        if (!empty($data['graph_message_id'])) {
            $existing = $this->emailRepo->findByGraphMessageId($data['graph_message_id']);
            if ($existing) {
                $this->logger->info('Duplicate email detected (graph_message_id)', [
                    'graph_message_id' => $data['graph_message_id'],
                    'existing_email_id' => $existing['id']
                ]);
                return [
                    'success' => true,
                    'email_id' => $existing['id'],
                    'thread_id' => $existing['thread_id'],
                    'duplicate' => true
                ];
            }
        }
        
        if (!empty($data['internet_message_id'])) {
            $existing = $this->emailRepo->findByInternetMessageId($data['internet_message_id']);
            if ($existing) {
                $this->logger->info('Duplicate email detected (internet_message_id)', [
                    'internet_message_id' => $data['internet_message_id'],
                    'existing_email_id' => $existing['id']
                ]);
                return [
                    'success' => true,
                    'email_id' => $existing['id'],
                    'thread_id' => $existing['thread_id'],
                    'duplicate' => true
                ];
            }
        }
        
        // 2. Determine direction
        $direction = $this->determineDirection($data['from_email'] ?? '');
        
        // 3. Extract participants
        $participants = $this->extractParticipants($data, $direction);
        
        // 4. Get or create thread
        $thread = $this->threadRepo->findByUniqueKey(
            $participants['external_email'],
            $participants['internal_email'],
            $this->normalizeSubject($data['subject'] ?? '')
        );
        
        if (!$thread) {
            $threadId = $this->threadRepo->create([
                'external_email' => $participants['external_email'],
                'internal_sender_email' => $participants['internal_email'],
                'subject_normalized' => $this->normalizeSubject($data['subject'] ?? ''),
                'status' => 'Sent',
                'last_activity_at' => $this->parseDateTime($data['received_at'] ?? $data['sent_at'] ?? null) ?? date('Y-m-d H:i:s')
            ]);
            
            $this->logger->info('New thread created', [
                'thread_id' => $threadId,
                'external_email' => $participants['external_email'],
                'internal_email' => $participants['internal_email']
            ]);
        } else {
            $threadId = $thread['id'];
            
            // Update thread last activity
            $this->threadRepo->update($threadId, [
                'last_activity_at' => $this->parseDateTime($data['received_at'] ?? $data['sent_at'] ?? null) ?? date('Y-m-d H:i:s')
            ]);
        }
        
        // 5. Create email record
        $emailId = $this->emailRepo->create([
            'thread_id' => $threadId,
            'direction' => $direction,
            'graph_message_id' => $data['graph_message_id'] ?? null,
            'internet_message_id' => $data['internet_message_id'] ?? null,
            'subject' => $data['subject'] ?? null,
            'from_email' => $data['from_email'] ?? null,
            'to' => $data['to'] ?? [],
            'cc' => $data['cc'] ?? [],
            'bcc' => $data['bcc'] ?? [],
            'sent_at' => $this->parseDateTime($data['sent_at'] ?? null),
            'received_at' => $this->parseDateTime($data['received_at'] ?? null),
            'body_preview' => $data['body_preview'] ?? null,
            'body_text' => $data['body_text'] ?? null,
            'raw_payload' => $data['raw_payload'] ?? $data
        ]);
        
        if ($emailId === null) {
            // Duplicate caught by database constraint
            $this->logger->warning('Email duplicate caught by database constraint', [
                'graph_message_id' => $data['graph_message_id'] ?? null,
                'internet_message_id' => $data['internet_message_id'] ?? null
            ]);
            return [
                'success' => true,
                'email_id' => null,
                'thread_id' => $threadId,
                'duplicate' => true
            ];
        }
        
        $this->logger->info('Email processed successfully', [
            'email_id' => $emailId,
            'thread_id' => $threadId,
            'direction' => $direction
        ]);
        
        return [
            'success' => true,
            'email_id' => $emailId,
            'thread_id' => $threadId,
            'duplicate' => false
        ];
    }
    
    /**
     * Determine email direction based on from_email
     * 
     * @param string $fromEmail
     * @return string 'outbound', 'inbound', or 'unknown'
     */
    private function determineDirection(string $fromEmail): string
    {
        if (empty($fromEmail)) {
            return 'unknown';
        }
        
        // Check if from internal domain
        $internalDomain = '@' . ($_ENV['INTERNAL_DOMAIN'] ?? 'veerless.com');
        if (str_contains(strtolower($fromEmail), $internalDomain)) {
            return 'outbound';
        }
        
        return 'inbound';
    }
    
    /**
     * Extract external and internal email addresses from participants
     * 
     * @param array $data Email data
     * @param string $direction
     * @return array ['external_email' => string, 'internal_email' => string]
     */
    private function extractParticipants(array $data, string $direction): array
    {
        $fromEmail = $data['from_email'] ?? '';
        $toEmails = $data['to'] ?? [];
        $ccEmails = $data['cc'] ?? [];
        
        // Collect all participants (exclude BCC as it's the monitored mailbox)
        $allParticipants = array_merge([$fromEmail], $toEmails, $ccEmails);
        $allParticipants = array_filter($allParticipants); // Remove empties
        
        $internalEmail = '';
        $externalEmail = '';
        
        $internalDomain = '@' . ($_ENV['INTERNAL_DOMAIN'] ?? 'veerless.com');
        
        foreach ($allParticipants as $email) {
            $email = strtolower(trim($email));
            
            if (str_contains($email, $internalDomain)) {
                if (empty($internalEmail)) {
                    $internalEmail = $email;
                }
            } else {
                if (empty($externalEmail)) {
                    $externalEmail = $email;
                }
            }
            
            // Stop if we have both
            if ($internalEmail && $externalEmail) {
                break;
            }
        }
        
        return [
            'internal_email' => $internalEmail,
            'external_email' => $externalEmail
        ];
    }
    
    /**
     * Normalize subject for thread matching
     * Removes Re:, Fwd:, etc. and trims/lowercases
     * 
     * @param string $subject
     * @return string|null
     */
    private function normalizeSubject(string $subject): ?string
    {
        if (empty($subject)) {
            return null;
        }
        
        // Remove common prefixes
        $subject = preg_replace('/^(re|fwd|fw):\s*/i', '', $subject);
        
        // Trim and lowercase
        $subject = strtolower(trim($subject));
        
        return empty($subject) ? null : $subject;
    }
    
    /**
     * Parse datetime string to MySQL format
     * 
     * @param string|null $datetime
     * @return string|null
     */
    private function parseDateTime(?string $datetime): ?string
    {
        if (empty($datetime)) {
            return null;
        }
        
        try {
            $dt = new \DateTime($datetime);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            $this->logger->warning('Failed to parse datetime', [
                'datetime' => $datetime,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get thread by ID (for Monday sync)
     * 
     * @param int $threadId
     * @return array|null
     */
    public function getThreadById(int $threadId): ?array
    {
        return $this->threadRepo->findById($threadId);
    }
}
