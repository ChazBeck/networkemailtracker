-- Migration: Create contact_enrichment table
-- Purpose: Store enriched contact data from Perplexity AI
-- Created: 2026-01-20

CREATE TABLE IF NOT EXISTS contact_enrichment (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id INT UNSIGNED NOT NULL,
    external_email VARCHAR(255) NOT NULL,
    
    -- Enriched contact data
    first_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) DEFAULT NULL,
    full_name VARCHAR(200) DEFAULT NULL,
    company_name VARCHAR(255) DEFAULT NULL,
    company_url VARCHAR(500) DEFAULT NULL,
    linkedin_url VARCHAR(500) DEFAULT NULL,
    job_title VARCHAR(255) DEFAULT NULL,
    
    -- Enrichment metadata
    enrichment_source ENUM('perplexity', 'signature', 'manual', 'hunter') NOT NULL,
    enrichment_status ENUM('pending', 'complete', 'failed') DEFAULT 'pending',
    confidence_score DECIMAL(3,2) DEFAULT NULL COMMENT 'AI confidence 0.00-1.00',
    
    -- Raw data for debugging
    raw_prompt TEXT DEFAULT NULL COMMENT 'Prompt sent to AI',
    raw_response TEXT DEFAULT NULL COMMENT 'Raw AI response',
    error_message TEXT DEFAULT NULL,
    
    -- Timestamps
    enriched_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    UNIQUE KEY unique_thread (thread_id),
    INDEX idx_external_email (external_email),
    INDEX idx_status (enrichment_status),
    INDEX idx_source (enrichment_source),
    
    -- Foreign key
    FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
