<?php

// ─────────────────────────────────────────
//  Migration: 2024_01_01_000000_create_users_table.php
//  Functions: migration_create_users_table_up() / migration_create_users_table_down()
// ─────────────────────────────────────────

function migration_create_users_table_up(): void
{

    db_unprepared("
        CREATE TABLE IF NOT EXISTS `users` (
            `id`                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `name`              VARCHAR(255)     NOT NULL,
            `email`             VARCHAR(255)     NOT NULL,
            `password`          VARCHAR(255)     NOT NULL,
            `role`              VARCHAR(50)      NOT NULL DEFAULT 'user',
            `remember_token`    VARCHAR(100)     NULL,
            `email_verified_at` DATETIME         NULL,
            `last_login_at`     DATETIME         NULL,
            `deleted_at`        DATETIME         NULL,
            `created_at`        DATETIME         NOT NULL,
            `updated_at`        DATETIME         NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `users_email_unique` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function migration_create_users_table_down(): void
{
    db_schema_drop('users');
}
