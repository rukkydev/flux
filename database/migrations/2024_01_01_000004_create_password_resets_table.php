<?php

function migration_create_password_resets_table_up(): void
{
    db_unprepared("
        CREATE TABLE IF NOT EXISTS `password_resets` (
            `email`      VARCHAR(255) NOT NULL,
            `token`      VARCHAR(64)  NOT NULL,
            `expires_at` DATETIME     NOT NULL,
            `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`email`),
            KEY `password_resets_token_index` (`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function migration_create_password_resets_table_down(): void
{
    db_schema_drop('password_resets');
}
