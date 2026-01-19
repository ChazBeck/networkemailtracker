<?php

/**
 * Migration: Create ingest_events table
 * 
 * Stores raw webhook payloads from Power Automate before processing
 */

return [
    'up' => "
        CREATE TABLE IF NOT EXISTS ingest_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            raw_json TEXT NOT NULL,
            webhook_secret_valid BOOLEAN DEFAULT FALSE,
            processed BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_processed (processed),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS ingest_events;
    "
];
