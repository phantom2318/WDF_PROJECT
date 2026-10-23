-- ==========================================================
-- StudentHub: Practical 12 Database Migration
-- Adds event_time, poster, status, and updated_at to events table
-- Safe script: Preserves all existing records and relationships
-- ==========================================================

USE `studenthub`;

-- 1. Add event_time column if not present
SET @col_time_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = 'studenthub'
      AND table_name = 'events'
      AND column_name = 'event_time'
);

SET @sql_time = IF(@col_time_exists = 0,
    'ALTER TABLE `events` ADD COLUMN `event_time` TIME NULL DEFAULT \'10:00:00\' AFTER `event_date`',
    'SELECT 1'
);
PREPARE stmt_time FROM @sql_time;
EXECUTE stmt_time;
DEALLOCATE PREPARE stmt_time;

-- 2. Add poster column if not present
SET @col_poster_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = 'studenthub'
      AND table_name = 'events'
      AND column_name = 'poster'
);

SET @sql_poster = IF(@col_poster_exists = 0,
    'ALTER TABLE `events` ADD COLUMN `poster` VARCHAR(255) NULL DEFAULT NULL AFTER `venue`',
    'SELECT 1'
);
PREPARE stmt_poster FROM @sql_poster;
EXECUTE stmt_poster;
DEALLOCATE PREPARE stmt_poster;

-- 3. Add status column if not present
SET @col_status_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = 'studenthub'
      AND table_name = 'events'
      AND column_name = 'status'
);

SET @sql_status = IF(@col_status_exists = 0,
    'ALTER TABLE `events` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT \'Open\' AFTER `poster`',
    'SELECT 1'
);
PREPARE stmt_status FROM @sql_status;
EXECUTE stmt_status;
DEALLOCATE PREPARE stmt_status;

-- 4. Add updated_at column if not present
SET @col_updated_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = 'studenthub'
      AND table_name = 'events'
      AND column_name = 'updated_at'
);

SET @sql_updated = IF(@col_updated_exists = 0,
    'ALTER TABLE `events` ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`',
    'SELECT 1'
);
PREPARE stmt_updated FROM @sql_updated;
EXECUTE stmt_updated;
DEALLOCATE PREPARE stmt_updated;

-- 5. Update existing sample events to ensure valid sample data
UPDATE `events`
SET `event_time` = '09:30:00', `status` = 'Open'
WHERE `event_id` = 1 AND (`event_time` IS NULL OR `status` IS NULL OR `status` = '');

UPDATE `events`
SET `event_time` = '14:00:00', `status` = 'Open'
WHERE `event_id` = 2 AND (`event_time` IS NULL OR `status` IS NULL OR `status` = '');

UPDATE `events`
SET `event_time` = '17:00:00', `status` = 'Closed'
WHERE `event_id` = 3 AND (`event_time` IS NULL OR `status` IS NULL OR `status` = '');

-- Add optional additional sample events for rich testing
INSERT IGNORE INTO `events` (`event_id`, `event_name`, `category`, `event_date`, `event_time`, `venue`, `description`, `poster`, `status`) VALUES
(4, 'AI & Machine Learning Symposium', 'Technical', '2026-11-28', '11:00:00', 'Seminar Hall 2', 'Keynote addresses and paper presentations on cutting-edge deep learning techniques.', NULL, 'Open'),
(5, 'Annual Inter-College Cricket Cup', 'Sports', '2026-12-10', '08:30:00', 'University Sports Complex', 'Inter-department cricket championship with 16 participating teams.', NULL, 'Open'),
(6, 'National Youth Literary Conclave', 'Literary', '2026-10-18', '10:00:00', 'Library Audio-Visual Room', 'Debate competitions, poetry slams, and creative writing workshops.', NULL, 'Closed');
