<?php

/**
 * Migration: Create monday_sync table
 * 
 * Tracks synchronization status between threads and Monday.com items
 */

return [
    'up' => "
        CREATE TABLE IF NOT EXISTS monday_sync (
            id INT AUTO_INCREMENT PRIMARY KEY,
            thread_id INT NOT NULL UNIQUE,
            monday_item_id VARCHAR(255),
            sync_status ENUM('pending', 'synced', 'failed') DEFAULT 'pending',
            synced_at TIMESTAMP NULL,
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
            INDEX idx_sync_status (sync_status),
            INDEX idx_thread_id (thread_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS monday_sync;
    "
];
