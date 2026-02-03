<?php

/**
 * Migration: Create LinkedIn threads and messages tables
 * 
 * Tracks LinkedIn conversations and messages similar to email threads
 * Aggregates messages by external LinkedIn URL with single owner
 */

return [
    'up' => "
        CREATE TABLE IF NOT EXISTS linkedin_threads (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            external_linkedin_url VARCHAR(512) NOT NULL,
            owner_email VARCHAR(255) NOT NULL,
            status ENUM('Sent','Responded','Closed') NOT NULL DEFAULT 'Sent',
            last_activity_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_linkedin_url (external_linkedin_url),
            INDEX idx_owner (owner_email),
            INDEX idx_status (status),
            INDEX idx_last_activity (last_activity_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS linkedin_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            thread_id INT UNSIGNED NOT NULL,
            sender_email VARCHAR(255) NOT NULL,
            direction ENUM('outbound','inbound') NOT NULL,
            message_text TEXT NOT NULL,
            sent_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_linkedin_messages_thread FOREIGN KEY (thread_id) 
                REFERENCES linkedin_threads(id) ON DELETE CASCADE,
            INDEX idx_thread (thread_id),
            INDEX idx_sender (sender_email),
            INDEX idx_sent_at (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS monday_sync_linkedin (
            thread_id INT UNSIGNED PRIMARY KEY,
            board_id VARCHAR(64) NULL,
            item_id VARCHAR(64) NULL,
            last_pushed_at DATETIME NULL,
            last_push_status ENUM('ok','error') NULL,
            last_error TEXT NULL,
            CONSTRAINT fk_monday_linkedin_thread FOREIGN KEY (thread_id) 
                REFERENCES linkedin_threads(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS monday_sync_linkedin;
        DROP TABLE IF EXISTS linkedin_messages;
        DROP TABLE IF EXISTS linkedin_threads;
    "
];
