<?php

/**
 * Migration: Create contact_enrichment table
 * 
 * Stores enriched contact data from external sources (Perplexity, etc.)
 */

return [
    'up' => "
        CREATE TABLE contact_enrichment (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            thread_id INT UNSIGNED NOT NULL,
            external_email VARCHAR(255) NOT NULL,
            first_name VARCHAR(255) NULL,
            last_name VARCHAR(255) NULL,
            full_name VARCHAR(255) NULL,
            company_name VARCHAR(255) NULL,
            company_url VARCHAR(512) NULL,
            linkedin_url VARCHAR(512) NULL,
            job_title VARCHAR(255) NULL,
            enrichment_source VARCHAR(64) NOT NULL COMMENT 'perplexity, clearbit, manual, etc',
            enrichment_status ENUM('pending','complete','failed') NOT NULL DEFAULT 'pending',
            confidence_score DECIMAL(3,2) NULL COMMENT '0.00 to 1.00',
            raw_prompt TEXT NULL COMMENT 'Prompt sent to enrichment service',
            raw_response TEXT NULL COMMENT 'Raw response from enrichment service',
            error_message TEXT NULL COMMENT 'Error details if enrichment_status = failed',
            enriched_at DATETIME NULL COMMENT 'When enrichment was completed',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            CONSTRAINT fk_enrichment_thread FOREIGN KEY (thread_id) 
                REFERENCES threads(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_thread_enrichment (thread_id),
            INDEX idx_external_email (external_email),
            INDEX idx_enrichment_status (enrichment_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    
    'down' => "
        DROP TABLE IF EXISTS contact_enrichment
    "
];
