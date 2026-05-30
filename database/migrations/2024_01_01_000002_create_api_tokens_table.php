<?php

function migration_create_api_tokens_table_up(): void
{
    db_unprepared("
        CREATE TABLE IF NOT EXISTS `api_tokens` (
            `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id`    BIGINT UNSIGNED NOT NULL,
            `name`       VARCHAR(255)    NOT NULL,
            `token`      VARCHAR(64)     NOT NULL,
            `last_used`  DATETIME        NULL,
            `expires_at` DATETIME        NULL,
            `revoked_at` DATETIME        NULL,
            `created_at` DATETIME        NOT NULL,
            `updated_at` DATETIME        NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `api_tokens_token_unique` (`token`),
            KEY `api_tokens_user_id_index` (`user_id`),
            KEY `api_tokens_revoked_at_index` (`revoked_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function migration_create_api_tokens_table_down(): void
{
    db_schema_drop('api_tokens');
}
