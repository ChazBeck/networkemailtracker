<?php

/**
 * Migration: Create threads table (Production Schema)
 * 
 * Threads represent email conversations between external and internal parties
 */

return [
    'up' => "
        CREATE TABLE threads (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            external_email VARCHAR(255) NOT NULL,
            internal_sender_email VARCHAR(255) NOT NULL,
            subject_normalized VARCHAR(512) NULL,
            status ENUM('Sent','Responded','Closed') NOT NULL DEFAULT 'Sent',
            last_activity_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_thread (external_email, internal_sender_email, subject_normalized)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS threads;
    "
];
