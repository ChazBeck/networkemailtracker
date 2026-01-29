<?php

/**
 * Migration: Create contact_sync table
 * 
 * Tracks synchronization between contacts and Monday.com Networking Contacts board
 * Separate from thread syncs - this is for unique contacts (grouped by email)
 */

return [
    'up' => "
        CREATE TABLE IF NOT EXISTS contact_sync (
            email VARCHAR(255) PRIMARY KEY,
            monday_item_id VARCHAR(64) NULL,
            last_synced_at DATETIME NULL,
            last_sync_status ENUM('ok','error') NULL,
            last_error TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_monday_item (monday_item_id),
            INDEX idx_last_synced (last_synced_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS contact_sync;
    "
];
