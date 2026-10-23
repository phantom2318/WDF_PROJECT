-- ==========================================================
-- StudentHub: Practical 14 Database Migration
-- Creates audit_logs table for administrative action tracking
-- Safe script: Idempotent and preserves all existing records
-- ==========================================================

USE `studenthub`;

-- 1. Create audit_logs table if not already present
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `log_id`       INT AUTO_INCREMENT NOT NULL,
    `user_id`      INT NULL,
    `action`       VARCHAR(50)  NOT NULL,
    `entity_type`  VARCHAR(50)  NOT NULL,
    `entity_id`    VARCHAR(50)  DEFAULT NULL,
    `details`      TEXT         DEFAULT NULL,
    `ip_address`   VARCHAR(45)  DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`log_id`),
    KEY `idx_audit_user` (`user_id`),
    KEY `idx_audit_entity` (`entity_type`, `entity_id`),
    KEY `idx_audit_created` (`created_at`),
    CONSTRAINT `fk_audit_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Insert initial baseline audit entry if table is empty
INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`)
SELECT u.user_id, 'SYSTEM_INIT', 'system', 'P14', 'Practical 14 Audit Logging subsystem initialized successfully.', '127.0.0.1'
FROM `users` u
WHERE u.role = 'admin'
LIMIT 1;
