<?php

// ─────────────────────────────────────────
//  Migration: create_admins_table
//  Admins are completely separate from users.
//  Created via: php flux make:admin
//  Always superuser — no granular permissions.
// ─────────────────────────────────────────

function migration_create_admins_table_up(): void
{
    db_unprepared("
        CREATE TABLE IF NOT EXISTS `admins` (
            `id`                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `name`              VARCHAR(255)     NOT NULL,
            `email`             VARCHAR(255)     NOT NULL,
            `password`          VARCHAR(255)     NOT NULL,
            `last_login_at`     DATETIME         NULL,
            `last_login_ip`     VARCHAR(45)      NULL,
            `is_active`         TINYINT(1)       NOT NULL DEFAULT 1,
            `created_by`        BIGINT UNSIGNED  NULL,
            `created_at`        DATETIME         NOT NULL,
            `updated_at`        DATETIME         NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `admins_email_unique` (`email`),
            KEY `admins_is_active_index` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function migration_create_admins_table_down(): void
{
    db_schema_drop('admins');
}
