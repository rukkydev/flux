<?php

function migration_create_activity_logs_table_up(): void
{
    db_unprepared("
        CREATE TABLE IF NOT EXISTS `activity_logs` (
            `id`            BIGINT UNSIGNED                     NOT NULL AUTO_INCREMENT,
            `user_id`       BIGINT UNSIGNED                     NULL,
            `actor_type`    ENUM('guest','user','admin')        NOT NULL DEFAULT 'guest',
            `event`         VARCHAR(100)                        NOT NULL,
            `description`   VARCHAR(500)                        NULL,
            `subject_type`  VARCHAR(100)                        NULL,
            `subject_id`    BIGINT UNSIGNED                     NULL,
            `context`       JSON                                NULL,
            `ip_address`    VARCHAR(45)                         NULL,
            `user_agent`    TEXT                                NULL,
            `browser`       VARCHAR(100)                        NULL,
            `os`            VARCHAR(100)                        NULL,
            `device`        VARCHAR(50)                         NULL,
            `url`           VARCHAR(500)                        NULL,
            `method`        VARCHAR(10)                         NULL,
            `created_at`    DATETIME                            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `activity_logs_user_id_index`    (`user_id`),
            KEY `activity_logs_actor_type_index` (`actor_type`),
            KEY `activity_logs_event_index`      (`event`),
            KEY `activity_logs_subject_index`    (`subject_type`, `subject_id`),
            KEY `activity_logs_created_at_index` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function migration_create_activity_logs_table_down(): void
{
    db_schema_drop('activity_logs');
}
