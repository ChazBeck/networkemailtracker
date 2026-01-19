<?php

/**
 * Migration: Create emails table
 * 
 * Emails represent individual email messages within threads
 * provider_message_id: unique identifier from email provider (Power Automate)
 * internet_message_id: standard email Message-ID header
 */

return [
    'up' => "
        CREATE TABLE IF NOT EXISTS emails (
            id INT AUTO_INCREMENT PRIMARY KEY,
            thread_id INT NOT NULL,
            provider_message_id VARCHAR(255) NOT NULL UNIQUE,
            internet_message_id VARCHAR(255),
            raw_json TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
            INDEX idx_provider_message_id (provider_message_id),
            INDEX idx_internet_message_id (internet_message_id),
            INDEX idx_thread_id (thread_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS emails;
    "
];
