<?php

/**
 * Migration: Create Email Tracking Tables
 * 
 * Creates tables for email open tracking using disguised image beacon.
 * - email_tracking: Stores beacon information and aggregated open counts
 * - open_events: Detailed log of each open event with bot detection
 */

return [
    'up' => "
        CREATE TABLE IF NOT EXISTS email_tracking (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            beacon_id VARCHAR(64) NOT NULL UNIQUE COMMENT '32-char hex tracking beacon identifier',
            email_id BIGINT UNSIGNED NULL COMMENT 'Links to emails table when email is sent',
            status ENUM('draft', 'active') DEFAULT 'draft' COMMENT 'draft=created but not sent, active=email sent and tracking live',
            activated_at DATETIME NULL COMMENT 'Timestamp when email was confirmed sent via webhook',
            total_opens INT DEFAULT 0 COMMENT 'All opens including BCC and bots',
            recipient_opens INT DEFAULT 0 COMMENT 'Meaningful opens (total_opens - 1 - bots)',
            first_opened_at DATETIME NULL COMMENT 'Timestamp of very first open',
            last_opened_at DATETIME NULL COMMENT 'Timestamp of most recent open',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            CONSTRAINT fk_tracking_email FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE SET NULL,
            INDEX idx_beacon_id (beacon_id),
            INDEX idx_email_id (email_id),
            INDEX idx_status (status),
            INDEX idx_activated_at (activated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS open_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            beacon_id VARCHAR(64) NOT NULL COMMENT 'References email_tracking.beacon_id',
            opened_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'When this open occurred',
            seconds_since_activation INT NULL COMMENT 'Seconds between email send and this open',
            user_agent TEXT NULL COMMENT 'Browser/client user agent string',
            ip_address VARCHAR(45) NULL COMMENT 'IP address of opener (IPv4 or IPv6)',
            is_bot BOOLEAN DEFAULT FALSE COMMENT 'TRUE if identified as bot/scanner via timing or user-agent',
            counted_as_recipient_open BOOLEAN DEFAULT FALSE COMMENT 'TRUE if counted toward recipient_opens',
            
            INDEX idx_beacon_id (beacon_id),
            INDEX idx_opened_at (opened_at),
            INDEX idx_is_bot (is_bot),
            CONSTRAINT fk_events_tracking FOREIGN KEY (beacon_id) REFERENCES email_tracking(beacon_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS open_events;
        DROP TABLE IF EXISTS email_tracking;
    "
];
