<?php

namespace App\Services;

use App\Repositories\MondaySyncRepository;
use App\Repositories\ThreadRepository;
use App\Repositories\EnrichmentRepository;
use App\Repositories\EmailRepository;
use Psr\Log\LoggerInterface;

class MondayService
{
    private MondaySyncRepository $syncRepo;
    private ThreadRepository $threadRepo;
    private EnrichmentRepository $enrichmentRepo;
    private EmailRepository $emailRepo;
    private LoggerInterface $logger;
    private string $apiKey;
    private string $boardId;
    private array $columnIds;
    
    public function __construct(
        MondaySyncRepository $syncRepo,
        ThreadRepository $threadRepo,
        EnrichmentRepository $enrichmentRepo,
        EmailRepository $emailRepo,
        LoggerInterface $logger
    ) {
        $this->syncRepo = $syncRepo;
        $this->threadRepo = $threadRepo;
        $this->enrichmentRepo = $enrichmentRepo;
        $this->emailRepo = $emailRepo;
        $this->logger = $logger;
        
        // Load Monday.com configuration from environment
        $this->apiKey = $_ENV['MONDAY_API_KEY'] ?? '';
        $this->boardId = $_ENV['MONDAY_BOARD_ID'] ?? '';
        $this->columnIds = [
            'subject' => $_ENV['MONDAY_COLUMN_SUBJECT'] ?? '',
            'email' => $_ENV['MONDAY_COLUMN_EMAIL'] ?? '',
            'body' => $_ENV['MONDAY_COLUMN_BODY'] ?? '',
            'first_email' => $_ENV['MONDAY_COLUMN_FIRST_EMAIL'] ?? '',
            'status' => $_ENV['MONDAY_COLUMN_STATUS'] ?? '',
            'date' => $_ENV['MONDAY_COLUMN_DATE'] ?? '',
            'first_name' => $_ENV['MONDAY_COLUMN_FIRST_NAME'] ?? '',
            'last_name' => $_ENV['MONDAY_COLUMN_LAST_NAME'] ?? '',
            'company' => $_ENV['MONDAY_COLUMN_COMPANY'] ?? '',
            'job_title' => $_ENV['MONDAY_COLUMN_JOB_TITLE'] ?? '',
            'message_id' => $_ENV['MONDAY_COLUMN_MESSAGE_ID'] ?? '',
            'conversation_id' => $_ENV['MONDAY_COLUMN_CONVERSATION_ID'] ?? '',
        ];
    }
    
    /**
     * Sync a thread to Monday.com
     * 
     * @param array $thread Thread data from database
     * @return array Result with success status and monday_item_id
     */
    public function syncThread(array $thread): array
    {
        try {
            // Check if already synced
            $existingSync = $this->syncRepo->findByThreadId($thread['id']);
            
            if ($existingSync && $existingSync['last_push_status'] === 'ok') {
                // Update existing item
                return $this->updateMondayItem($existingSync['item_id'], $thread);
            } else {
                // Create new item
                return $this->createMondayItem($thread);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync thread to Monday', [
                'thread_id' => $thread['id'],
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create new Monday.com item
     * 
     * @param array $thread
     * @return array
     */
    private function createMondayItem(array $thread): array
    {
        // Get enrichment data if available
        $enrichment = $this->getEnrichmentData($thread['id']);
        
        // Get first email for message ID, conversation ID, and body
        $firstEmail = $this->getFirstEmail($thread['id']);
        
        // Build item name: "Company - FirstName LastName"
        $company = $enrichment['company_name'] ?? 'Unknown Company';
        $fullName = $enrichment['full_name'] ?? $thread['external_email'];
        $itemName = "$company - $fullName";
        
        // Get first email date from thread
        $firstEmailDate = $thread['first_email_at'] ?? $thread['created_at'];
        
        // Build column values
        $columnValues = [];
        
        // Always add these core fields
        if (!empty($this->columnIds['subject'])) {
            $columnValues[$this->columnIds['subject']] = $thread['subject_normalized'] ?? '';
        }
        
        if (!empty($this->columnIds['email'])) {
            $columnValues[$this->columnIds['email']] = [
                'email' => $thread['external_email'] ?? '', 
                'text' => $thread['external_email'] ?? ''
            ];
        }
        
        if (!empty($this->columnIds['date'])) {
            $columnValues[$this->columnIds['date']] = [
                'date' => date('Y-m-d', strtotime($thread['last_activity_at']))
            ];
        }
        
        if (!empty($this->columnIds['first_email']) && $firstEmailDate) {
            $columnValues[$this->columnIds['first_email']] = [
                'date' => date('Y-m-d', strtotime($firstEmailDate))
            ];
        }
        
        // Add email body from first email
        if (!empty($this->columnIds['body']) && $firstEmail && !empty($firstEmail['body_preview'])) {
            $columnValues[$this->columnIds['body']] = $firstEmail['body_preview'];
        }
        
        // Add enrichment fields if available
        if ($enrichment) {
            if (!empty($this->columnIds['first_name']) && !empty($enrichment['first_name'])) {
                $columnValues[$this->columnIds['first_name']] = $enrichment['first_name'];
            }
            
            if (!empty($this->columnIds['last_name']) && !empty($enrichment['last_name'])) {
                $columnValues[$this->columnIds['last_name']] = $enrichment['last_name'];
            }
            
            if (!empty($this->columnIds['company']) && !empty($enrichment['company_name'])) {
                $columnValues[$this->columnIds['company']] = $enrichment['company_name'];
            }
            
            if (!empty($this->columnIds['job_title']) && !empty($enrichment['job_title'])) {
                $columnValues[$this->columnIds['job_title']] = $enrichment['job_title'];
            }
        }
        
        // Add message ID and conversation ID from first email
        if (!empty($this->columnIds['message_id']) && $firstEmail && !empty($firstEmail['internet_message_id'])) {
            $columnValues[$this->columnIds['message_id']] = $firstEmail['internet_message_id'];
        }
        
        if (!empty($this->columnIds['conversation_id']) && $firstEmail && !empty($firstEmail['graph_message_id'])) {
            // Use graph_message_id as conversation ID (it's consistent across thread)
            $columnValues[$this->columnIds['conversation_id']] = $firstEmail['graph_message_id'];
        }
        
        // Build GraphQL mutation
        $mutation = 'mutation {
          create_item (
            board_id: ' . $this->boardId . ',
            item_name: "' . $this->escapeGraphQL($itemName) . '",
            column_values: "' . $this->escapeGraphQL(json_encode($columnValues)) . '"
          ) {
            id
          }
        }';
        
        $this->logger->debug('Creating Monday item', [
            'mutation' => $mutation,
            'column_values' => $columnValues
        ]);
        
        $response = $this->callMondayAPI($mutation);
        
        if (isset($response['data']['create_item']['id'])) {
            $mondayItemId = $response['data']['create_item']['id'];
            
            // Save sync record
            $this->syncRepo->create([
                'thread_id' => $thread['id'],
                'monday_item_id' => $mondayItemId,
                'status' => 'synced',
                'last_synced_at' => date('Y-m-d H:i:s'),
                'sync_data' => json_encode(['action' => 'created'])
            ]);
            
            $this->logger->info('Created Monday item', [
                'thread_id' => $thread['id'],
                'monday_item_id' => $mondayItemId
            ]);
            
            return [
                'success' => true,
                'monday_item_id' => $mondayItemId,
                'action' => 'created'
            ];
        }
        
        throw new \Exception('Monday API did not return item ID: ' . json_encode($response));
    }
    
    /**
     * Update existing Monday.com item
     * 
     * @param string $mondayItemId
     * @param array $thread
     * @return array
     */
    private function updateMondayItem(string $mondayItemId, array $thread): array
    {
        // Build column values for update
        $columnValues = [
            $this->columnIds['date'] => ['date' => date('Y-m-d', strtotime($thread['last_activity_at']))],
        ];
        
        $mutation = 'mutation {
          change_multiple_column_values (
            item_id: ' . $mondayItemId . ',
            board_id: ' . $this->boardId . ',
            column_values: "' . $this->escapeGraphQL(json_encode($columnValues)) . '"
          ) {
            id
          }
        }';
        
        $response = $this->callMondayAPI($mutation);
        
        if (isset($response['data']['change_multiple_column_values']['id'])) {
            $this->syncRepo->update($mondayItemId, [
                'last_synced_at' => date('Y-m-d H:i:s'),
                'sync_data' => json_encode(['action' => 'updated'])
            ]);
            
            $this->logger->info('Updated Monday item', [
                'thread_id' => $thread['id'],
                'monday_item_id' => $mondayItemId
            ]);
            
            return [
                'success' => true,
                'monday_item_id' => $mondayItemId,
                'action' => 'updated'
            ];
        }
        
        throw new \Exception('Failed to update Monday item');
    }
    
    /**
     * Call Monday.com GraphQL API
     * 
     * @param string $query GraphQL query or mutation
     * @return array Response data
     */
    private function callMondayAPI(string $query): array
    {
        $ch = curl_init('https://api.monday.com/v2');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->apiKey,
                'Content-Type: application/json',
                'API-Version: 2024-10'
            ],
            CURLOPT_POSTFIELDS => json_encode(['query' => $query])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("Monday API error: HTTP $httpCode - $response");
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['errors'])) {
            throw new \Exception('Monday GraphQL error: ' . json_encode($data['errors']));
        }
        
        return $data;
    }
    
    /**
     * Escape string for GraphQL
     * 
     * @param string $str
     * @return string
     */
    private function escapeGraphQL(string $str): string
    {
        // Escape backslashes first, then quotes, then newlines
        $escaped = str_replace('\\', '\\\\', $str);
        $escaped = str_replace('"', '\\"', $escaped);
        $escaped = str_replace("\n", '\\n', $escaped);
        $escaped = str_replace("\r", '\\r', $escaped);
        return $escaped;
    }
    
    /**
     * Sync pending threads to Monday.com
     * 
     * @return array Sync results
     */
    public function syncPendingThreads(): array
    {
        $threads = $this->threadRepo->getAllWithEmailCount();
        $synced = 0;
        $failed = 0;
        
        foreach ($threads as $thread) {
            // Skip threads without external email (internal only)
            if (empty($thread['external_email'])) {
                continue;
            }
            
            $result = $this->syncThread($thread);
            
            if ($result['success'] ?? false) {
                $synced++;
            } else {
                $failed++;
            }
        }
        
        return [
            'synced' => $synced,
            'failed' => $failed,
            'message' => "Synced $synced threads, $failed failed"
        ];
    }
    
    /**
     * Get sync status for all threads
     * 
     * @return array
     */
    public function getSyncStatus(): array
    {
        return $this->syncRepo->getAllWithThreadInfo();
    }
    
    /**
     * Get enrichment data for a thread
     * 
     * @param int $threadId
     * @return array|null
     */
    private function getEnrichmentData(int $threadId): ?array
    {
        try {
            $enrichment = $this->enrichmentRepo->findByThreadId($threadId);
            
            // Only return if enrichment is complete
            if ($enrichment && $enrichment['enrichment_status'] === 'complete') {
                return $enrichment;
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->warning('Failed to fetch enrichment data', [
                'thread_id' => $threadId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get first email for a thread
     * 
     * @param int $threadId
     * @return array|null
     */
    private function getFirstEmail(int $threadId): ?array
    {
        try {
            return $this->emailRepo->getFirstByThreadId($threadId);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to fetch first email', [
                'thread_id' => $threadId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
