<?php

/**
 * Migration: Create monday_sync table (Production Schema)
 * 
 * Tracks synchronization between threads and Monday.com items
 */

return [
    'up' => "
        CREATE TABLE monday_sync (
            thread_id INT UNSIGNED PRIMARY KEY,
            board_id VARCHAR(64) NULL,
            item_id VARCHAR(64) NULL,
            last_pushed_at DATETIME NULL,
            last_push_status ENUM('ok','error') NULL,
            last_error TEXT NULL,
            CONSTRAINT fk_monday_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS monday_sync;
    "
];
