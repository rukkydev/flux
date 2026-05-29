<?php

// ─────────────────────────────────────────
//  Migration: add_created_at_to_password_rest_table
//  Created: 2026_05_27_222952
// ─────────────────────────────────────────

function migration_add_created_at_to_password_rest_table_up(): void
{
    db_execute("
       ALTER TABLE `password_resets`
       ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ");
}

function down(): void
{
    db_execute("DROP TABLE IF EXISTS `example`");
}