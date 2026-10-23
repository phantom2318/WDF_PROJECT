USE `studenthub`;

-- 1. Add role column to users table if not already present
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = 'studenthub'
      AND table_name = 'users'
      AND column_name = 'role'
);

SET @sql_role = IF(@col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `role` VARCHAR(20) NOT NULL DEFAULT \'user\' AFTER `password_hash`',
    'SELECT 1'
);
PREPARE stmt_role FROM @sql_role;
EXECUTE stmt_role;
DEALLOCATE PREPARE stmt_role;

-- 2. Create remember_tokens table for secure persistent authentication
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `token_id`    INT AUTO_INCREMENT NOT NULL,
    `user_id`     INT NOT NULL,
    `token_hash`  VARCHAR(64) NOT NULL,
    `expires_at`  DATETIME NOT NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`token_id`),
    UNIQUE KEY `uk_token_hash` (`token_hash`),
    CONSTRAINT `fk_token_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed an initial administrator account for testing
-- Email: admin@charusat.edu.in | Password: Admin@2026 | Role: admin
INSERT INTO `users` (`full_name`, `email`, `password_hash`, `role`)
VALUES ('System Administrator', 'admin@charusat.edu.in', '$2y$10$zvq7fL.QqvNENHvhX1iWB.LBOyo6Rtte/7jlBJoqjU/c5zpm8VhFS', 'admin')
ON DUPLICATE KEY UPDATE `role` = 'admin';
