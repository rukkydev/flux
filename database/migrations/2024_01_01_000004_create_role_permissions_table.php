<?php

// ─────────────────────────────────────────
//  Migration: 2024_01_01_000004_create_role_permissions_table.php
//  Functions: migration_create_role_permissions_table_up() / migration_create_role_permissions_table_down()
// ─────────────────────────────────────────

function migration_create_role_permissions_table_up(): void
{

    db_unprepared("
        CREATE TABLE IF NOT EXISTS `role_permissions` (
            `id`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `role`    VARCHAR(50)     NOT NULL,
            `ability` VARCHAR(100)    NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `role_permissions_role_ability_unique` (`role`, `ability`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function migration_create_role_permissions_table_down(): void
{
    db_schema_drop('role_permissions');
}
