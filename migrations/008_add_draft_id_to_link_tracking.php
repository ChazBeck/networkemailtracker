<?php

/**
 * Migration: Add draft_id to Link Tracking Table
 * 
 * Allows matching draft links to emails when they're sent via webhook
 */

return [
    'up' => "
        ALTER TABLE link_tracking 
        ADD COLUMN draft_id VARCHAR(255) NULL COMMENT 'Outlook draft ID for matching' AFTER email_id,
        ADD INDEX idx_draft_id (draft_id);
    ",
    
    'down' => "
        ALTER TABLE link_tracking 
        DROP INDEX idx_draft_id,
        DROP COLUMN draft_id;
    "
];
