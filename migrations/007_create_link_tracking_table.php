<?php

/**
 * Migration: Create Link Tracking Table
 * 
 * Stores shortened URLs and click tracking for email links
 */

return [
    'up' => "
        CREATE TABLE IF NOT EXISTS link_tracking (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email_id BIGINT UNSIGNED NULL COMMENT 'Links to emails table',
            original_url VARCHAR(2048) NOT NULL COMMENT 'Original full URL',
            short_url VARCHAR(255) NOT NULL COMMENT 'YOURLS shortened URL',
            yourls_keyword VARCHAR(255) NOT NULL COMMENT 'YOURLS keyword/slug',
            url_type ENUM('veerless', 'external') DEFAULT 'external' COMMENT 'Type of URL shortened',
            tracking_code VARCHAR(32) NULL COMMENT 'Random tracking suffix for veerless URLs',
            clicks INT DEFAULT 0 COMMENT 'Click count (synced from YOURLS)',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            CONSTRAINT fk_link_email FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE SET NULL,
            INDEX idx_email_id (email_id),
            INDEX idx_yourls_keyword (yourls_keyword),
            INDEX idx_short_url (short_url)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS link_tracking;
    "
];
