<?php

/**
 * Migration: Create sync_state table (Production Schema)
 * 
 * Stores synchronization state and configuration values
 */

return [
    'up' => "
        CREATE TABLE sync_state (
            name VARCHAR(128) PRIMARY KEY,
            value MEDIUMTEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS sync_state;
    "
];
