<?php

/**
 * Migration: Create emails table (Production Schema)
 * 
 * Emails represent individual messages with full metadata from Microsoft Graph
 */

return [
    'up' => "
        CREATE TABLE emails (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            thread_id INT UNSIGNED NOT NULL,
            direction ENUM('outbound','inbound','unknown') NOT NULL DEFAULT 'unknown',
            graph_message_id VARCHAR(255) NULL,
            internet_message_id VARCHAR(255) NULL,
            subject VARCHAR(512) NULL,
            from_email VARCHAR(255) NULL,
            to_json JSON NULL,
            cc_json JSON NULL,
            bcc_json JSON NULL,
            sent_at DATETIME NULL,
            received_at DATETIME NULL,
            body_preview TEXT NULL,
            body_text MEDIUMTEXT NULL,
            raw_payload JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_emails_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_internet_message_id (internet_message_id),
            UNIQUE KEY uniq_graph_message_id (graph_message_id),
            KEY idx_thread_id (thread_id),
            KEY idx_received_at (received_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS emails;
    "
];
