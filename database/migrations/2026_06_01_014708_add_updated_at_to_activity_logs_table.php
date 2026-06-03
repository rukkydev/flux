<?php

// ─────────────────────────────────────────
//  Migration: add_updated_at_to_activity_logs_table
//  Created: 2026_06_01_014708
// ─────────────────────────────────────────

function migration_add_updated_at_to_activity_logs_table_up(): void
{
    db_execute("
        ALTER TABLE `activity_logs`
        ADD COLUMN `updated_at` DATETIME NULL AFTER `created_at`
    ");
}

function down(): void
{
    db_execute("DROP TABLE IF EXISTS `example`");
}