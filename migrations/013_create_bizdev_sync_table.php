<?php

/**
 * Migration: Create bizdev_sync table
 */

return [
    "up" => "
        CREATE TABLE IF NOT EXISTS bizdev_sync (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            enrichment_id INT UNSIGNED NOT NULL,
            board_id VARCHAR(64) NULL,
            item_id VARCHAR(64) NULL,
            last_pushed_at DATETIME NULL,
            last_push_status ENUM('ok','error') NULL,
            last_error TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_bizdev_enrichment FOREIGN KEY (enrichment_id) REFERENCES contact_enrichment(id) ON DELETE CASCADE,
            UNIQUE KEY idx_enrichment (enrichment_id),
            INDEX idx_item (item_id),
            INDEX idx_last_pushed (last_pushed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    "down" => "DROP TABLE IF EXISTS bizdev_sync;"
];
