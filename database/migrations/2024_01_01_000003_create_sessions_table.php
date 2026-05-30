<?php

function migration_create_sessions_table_up(): void
{
    db_unprepared("
        CREATE TABLE IF NOT EXISTS `sessions` (
            `id`            VARCHAR(255)                        NOT NULL,
            `user_id`       BIGINT UNSIGNED                     NULL,
            `user_type`     ENUM('guest','user','admin')        NOT NULL DEFAULT 'guest',
            `ip_address`    VARCHAR(45)                         NULL,
            `user_agent`    TEXT                                NULL,
            `browser`       VARCHAR(100)                        NULL,
            `os`            VARCHAR(100)                        NULL,
            `device`        VARCHAR(50)                         NULL DEFAULT 'desktop',
            `country`       VARCHAR(100)                        NULL,
            `referrer`      VARCHAR(500)                        NULL,
            `payload`       LONGTEXT                            NOT NULL,
            `last_active`   INT UNSIGNED                        NOT NULL,
            `created_at`    DATETIME                            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `sessions_user_id_index`   (`user_id`),
            KEY `sessions_user_type_index` (`user_type`),
            KEY `sessions_last_active_idx` (`last_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function migration_create_sessions_table_down(): void
{
    db_schema_drop('sessions');
}
