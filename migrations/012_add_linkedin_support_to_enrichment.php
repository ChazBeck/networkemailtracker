<?php

/**
 * Migration: Add LinkedIn support to contact_enrichment table
 * 
 * Allows enrichment to be linked to either email threads or LinkedIn threads
 * Enables enrichment reuse across both communication channels
 */

return [
    'up' => "
        -- Add LinkedIn thread foreign key
        ALTER TABLE contact_enrichment 
            ADD COLUMN linkedin_thread_id INT UNSIGNED NULL AFTER thread_id,
            ADD CONSTRAINT fk_enrichment_linkedin_thread 
                FOREIGN KEY (linkedin_thread_id) 
                REFERENCES linkedin_threads(id) ON DELETE CASCADE;
        
        -- Make thread_id nullable to support LinkedIn-only contacts
        ALTER TABLE contact_enrichment 
            MODIFY COLUMN thread_id INT UNSIGNED NULL;
        
        -- Make external_email nullable to support LinkedIn-only contacts
        ALTER TABLE contact_enrichment 
            MODIFY COLUMN external_email VARCHAR(255) NULL;
        
        -- Add external_linkedin_url for LinkedIn contact identification
        ALTER TABLE contact_enrichment 
            ADD COLUMN external_linkedin_url VARCHAR(512) NULL AFTER external_email,
            ADD INDEX idx_linkedin_url (external_linkedin_url);
        
        -- Add constraint: at least one of thread_id or linkedin_thread_id must be set
        ALTER TABLE contact_enrichment 
            ADD CONSTRAINT chk_has_thread 
                CHECK (thread_id IS NOT NULL OR linkedin_thread_id IS NOT NULL);
    ",
    
    'down' => "
        ALTER TABLE contact_enrichment 
            DROP CONSTRAINT chk_has_thread,
            DROP FOREIGN KEY fk_enrichment_linkedin_thread,
            DROP COLUMN linkedin_thread_id,
            DROP COLUMN external_linkedin_url,
            MODIFY COLUMN thread_id INT UNSIGNED NOT NULL,
            MODIFY COLUMN external_email VARCHAR(255) NOT NULL;
    "
];
