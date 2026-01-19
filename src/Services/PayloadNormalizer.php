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
        return [
            'event_type' => 'email.received',
            'event_id' => uniqid('evt_', true),
            'timestamp' => date('c'),
            'data' => [
                'provider' => $payload['provider'] ?? 'm365',
                'mailbox' => $payload['mailbox'] ?? null,
                'graph_message_id' => $payload['graphMessageId'] ?? null,
                'internet_message_id' => $payload['internetMessageId'] ?? null,
                'conversation_id' => $payload['conversationId'] ?? null,
                'subject' => $payload['subject'] ?? null,
                'from_email' => $payload['fromEmail'] ?? null,
                'to' => self::extractEmailAddresses($payload['toEmails'] ?? []),
                'cc' => self::extractEmailAddresses($payload['ccEmails'] ?? []),
                'bcc' => [], // Not provided by Power Automate
                'sent_at' => $payload['sentDateTime'] ?? null,
                'received_at' => $payload['receivedDateTime'] ?? null,
                'body_preview' => $payload['bodyPreview'] ?? null,
                'body_text' => null, // Not provided in this payload
                'web_link' => $payload['webLink'] ?? null,
                'has_attachments' => $payload['hasAttachments'] ?? false,
                'importance' => $payload['importance'] ?? null,
                'raw_payload' => $payload['raw'] ?? $payload
            ]
        ];
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
