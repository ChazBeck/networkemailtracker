<?php

/**
 * Migration: Create threads table
 * 
 * Threads represent email conversations grouped by conversation_id
 */

return [
    'up' => "
        CREATE TABLE IF NOT EXISTS threads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id VARCHAR(255) NOT NULL UNIQUE,
            subject VARCHAR(500),
            first_email_date TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_conversation_id (conversation_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS threads;
    "
];
