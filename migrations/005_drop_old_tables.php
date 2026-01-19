<?php

/**
 * Migration: Drop old table structure
 * 
 * Removing original schema to replace with production schema
 */

return [
    'up' => "
        SET FOREIGN_KEY_CHECKS = 0;
        DROP TABLE IF EXISTS monday_sync;
        DROP TABLE IF EXISTS emails;
        DROP TABLE IF EXISTS ingest_events;
        DROP TABLE IF EXISTS threads;
        SET FOREIGN_KEY_CHECKS = 1;
    ",
    
    'down' => "
        -- Cannot reverse this migration
    "
];
