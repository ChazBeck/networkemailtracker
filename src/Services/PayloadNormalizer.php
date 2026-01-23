<?php

namespace App\Services;

class PayloadNormalizer
{
    /**
     * Normalize Power Automate payload to internal format
     * 
     * @param array $payload Raw webhook payload
     * @return array Normalized payload
     */
    public static function normalize(array $payload): array
    {
        // Handle nested EmailDetails structure (current Power Automate format)
        if (isset($payload['EmailDetails'])) {
            $data = $payload['EmailDetails'];
        } else {
            $data = $payload;
        }
        
        return [
            'event_type' => 'email.received',
            'event_id' => uniqid('evt_', true),
            'timestamp' => date('c'),
            'data' => [
                'provider' => $payload['provider'] ?? 'm365',
                'mailbox' => $payload['mailbox'] ?? null,
                // Support both formats: MessageId / graphMessageId
                'graph_message_id' => $data['MessageId'] ?? $data['graphMessageId'] ?? $payload['graphMessageId'] ?? null,
                // Support both formats: InternetMessageId / internetMessageId
                'internet_message_id' => $data['InternetMessageId'] ?? $data['internetMessageId'] ?? $payload['internetMessageId'] ?? null,
                // Support both formats: ConversationId / conversationId
                'conversation_id' => $data['ConversationId'] ?? $data['conversationId'] ?? $payload['conversationId'] ?? null,
                // Support both formats: Subject / subject
                'subject' => $data['Subject'] ?? $data['subject'] ?? $payload['subject'] ?? null,
                // Support both formats: From / fromEmail
                'from_email' => self::extractSingleEmail($data['From'] ?? $data['fromEmail'] ?? $payload['fromEmail'] ?? null),
                // Support both formats: ToRecipients / toEmails
                'to' => self::extractEmailAddresses($data['ToRecipients'] ?? $data['toEmails'] ?? $payload['toEmails'] ?? []),
                // Support both formats: CcRecipients / ccEmails
                'cc' => self::extractEmailAddresses($data['CcRecipients'] ?? $data['ccEmails'] ?? $payload['ccEmails'] ?? []),
                'bcc' => [], // Not provided by Power Automate
                // Support both formats: ReceivedDateTime / receivedDateTime
                'sent_at' => $data['ReceivedDateTime'] ?? $data['sentDateTime'] ?? $payload['sentDateTime'] ?? null,
                'received_at' => $data['ReceivedDateTime'] ?? $data['receivedDateTime'] ?? $payload['receivedDateTime'] ?? null,
                // Support both formats: BodyPreview / bodyPreview
                'body_preview' => $data['BodyPreview'] ?? $data['bodyPreview'] ?? $payload['bodyPreview'] ?? null,
                // Extract body from nested raw JSON structure
                'body_text' => self::extractBody($payload),
                'web_link' => $data['webLink'] ?? $payload['webLink'] ?? null,
                // Support both formats: HasAttachments / hasAttachments
                'has_attachments' => $data['HasAttachments'] ?? $data['hasAttachments'] ?? $payload['hasAttachments'] ?? false,
                // Support both formats: Importance / importance
                'importance' => $data['Importance'] ?? $data['importance'] ?? $payload['importance'] ?? null,
                'raw_payload' => $payload
            ]
        ];
    }
    
    /**
     * Extract HTML body from nested payload structure
     * Power Automate sends body in: payload.raw.body (JSON stringified)
     * 
     * @param array $payload
     * @return string|null
     */
    private static function extractBody(array $payload): ?string
    {
        // Try direct Body field first
        if (!empty($payload['Body'])) {
            return $payload['Body'];
        }
        
        if (!empty($payload['EmailDetails']['Body'])) {
            return $payload['EmailDetails']['Body'];
        }
        
        // Try to extract from raw JSON structure
        if (!empty($payload['raw'])) {
            $raw = is_string($payload['raw']) ? json_decode($payload['raw'], true) : $payload['raw'];
            if (is_array($raw) && !empty($raw['body'])) {
                return $raw['body'];
            }
        }
        
        return null;
    }
    
    /**
     * Extract single email address from various formats
     * 
     * @param mixed $emailData
     * @return string|null
     */
    private static function extractSingleEmail($emailData): ?string
    {
        if (empty($emailData)) {
            return null;
        }
        
        // If it's already a simple email string
        if (is_string($emailData) && filter_var($emailData, FILTER_VALIDATE_EMAIL)) {
            return $emailData;
        }
        
        // If it's an array/object format
        if (is_array($emailData)) {
            if (isset($emailData['emailAddress']['address'])) {
                return $emailData['emailAddress']['address'];
            } elseif (isset($emailData['address'])) {
                return $emailData['address'];
            } elseif (isset($emailData['email'])) {
                return $emailData['email'];
            }
        }
        
        return null;
    }
    
    /**
     * Extract email addresses from Power Automate recipient format
     * Handles both array of objects and stringified JSON
     * 
     * @param mixed $recipients
     * @return array Simple array of email addresses
     */
    private static function extractEmailAddresses($recipients): array
    {
        if (empty($recipients)) {
            return [];
        }
        
        // If it's a string, try to decode it as JSON
        if (is_string($recipients)) {
            $decoded = json_decode($recipients, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $recipients = $decoded;
            } else {
                // If it's a plain email string, return it
                if (filter_var($recipients, FILTER_VALIDATE_EMAIL)) {
                    return [$recipients];
                }
                return [];
            }
        }
        
        // If not array at this point, give up
        if (!is_array($recipients)) {
            return [];
        }
        
        $emails = [];
        
        foreach ($recipients as $recipient) {
            // Handle Power Automate format: {"emailAddress": {"address": "...", "name": "..."}}
            if (is_array($recipient)) {
                if (isset($recipient['emailAddress']['address'])) {
                    $emails[] = $recipient['emailAddress']['address'];
                } elseif (isset($recipient['address'])) {
                    $emails[] = $recipient['address'];
                } elseif (isset($recipient['email'])) {
                    $emails[] = $recipient['email'];
                }
            } elseif (is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                // Handle simple string email
                $emails[] = $recipient;
            }
        }
        
        return array_values(array_unique(array_filter($emails)));
    }
}
