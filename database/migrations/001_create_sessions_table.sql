-- ═══════════════════════════════════════════════════════
--  FluxPHP — Sessions Table Migration
--  File: database/migrations/001_create_sessions_table.sql
--
--  Run this when SESSION_DRIVER=database in .env
--  Compatible with MySQL 5.7+ and MariaDB 10.3+
-- ═══════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `sessions` (
    -- Session ID (PHP session_id(), 128 chars max with sha256 handler)
    `id`          VARCHAR(128)     NOT NULL,

    -- Linked user (NULL for unauthenticated/guest sessions)
    `user_id`     INT UNSIGNED     NULL DEFAULT NULL,

    -- Client metadata (for audit trail and anomaly detection)
    `ip_address`  VARCHAR(45)      NULL DEFAULT NULL,
    `user_agent`  VARCHAR(255)     NULL DEFAULT NULL,

    -- Serialized session data (base64-encoded PHP session payload)
    -- LONGBLOB handles large sessions safely
    `payload`     LONGBLOB         NOT NULL,

    -- Unix timestamp of last activity (used for GC and timeout checks)
    `last_active` INT UNSIGNED     NOT NULL,

    PRIMARY KEY (`id`),

    -- Look up all sessions for a user (force-logout, active sessions list)
    INDEX `idx_user_id`     (`user_id`),

    -- GC query: DELETE WHERE last_active < (now - lifetime)
    INDEX `idx_last_active` (`last_active`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Force-logout a specific user (run from admin panel or artisan) ──
-- DELETE FROM sessions WHERE user_id = 42;

-- ── See all active sessions ──────────────────────────────────────────
-- SELECT id, user_id, ip_address, user_agent,
--        FROM_UNIXTIME(last_active) AS last_seen
-- FROM sessions
-- WHERE last_active >= UNIX_TIMESTAMP(NOW() - INTERVAL 120 MINUTE)
-- ORDER BY last_active DESC;

-- ── Clean up expired sessions manually ──────────────────────────────
-- DELETE FROM sessions WHERE last_active < UNIX_TIMESTAMP(NOW() - INTERVAL 120 MINUTE);
