-- ==========================================================
-- StudentHub: MySQL Database Schema & ER Model
-- Database: studenthub
-- ==========================================================

-- 1. Create database if it does not already exist
CREATE DATABASE IF NOT EXISTS `studenthub`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `studenthub`;

-- Disable foreign key checks during schema creation/import
SET FOREIGN_KEY_CHECKS = 0;

-- 2. Drop existing tables to allow clean re-import
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `registrations`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------
-- Table 0: users
-- Stores registered user accounts with secure password hashes
-- ----------------------------------------------------------
CREATE TABLE `users` (
    `user_id`       INT AUTO_INCREMENT NOT NULL,
    `full_name`     VARCHAR(100)       NOT NULL,
    `email`         VARCHAR(100)       NOT NULL,
    `password_hash` VARCHAR(255)       NOT NULL,
    `role`          VARCHAR(20)        NOT NULL DEFAULT 'user',
    `created_at`    TIMESTAMP          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `uk_user_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table 0b: remember_tokens
-- Stores persistent login tokens (SHA-256 hashes)
-- ----------------------------------------------------------
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


-- ----------------------------------------------------------
-- Table 1: students
-- Stores basic student academic and profile records
-- ----------------------------------------------------------
CREATE TABLE `students` (
    `student_id`   VARCHAR(20)  NOT NULL,
    `full_name`    VARCHAR(100) NOT NULL,
    `email`        VARCHAR(100) NOT NULL,
    `mobile`       VARCHAR(15)  NOT NULL,
    `department`   VARCHAR(50)  NOT NULL DEFAULT 'Information Technology',
    `course`       VARCHAR(50)  NOT NULL DEFAULT 'B.Tech',
    `semester`     INT          NOT NULL DEFAULT 3,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`student_id`),
    UNIQUE KEY `uk_student_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table 2: events
-- Stores campus events, hackathons, and club workshops
-- ----------------------------------------------------------
CREATE TABLE `events` (
    `event_id`     INT AUTO_INCREMENT NOT NULL,
    `event_name`   VARCHAR(150) NOT NULL,
    `category`     VARCHAR(50)  NOT NULL DEFAULT 'Technical',
    `event_date`   DATE         NOT NULL,
    `venue`        VARCHAR(100) NOT NULL DEFAULT 'Seminar Hall',
    `description`  TEXT         DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table 3: registrations
-- Associative table representing student event registrations (M:N)
-- Foreign Keys: student_id -> students, event_id -> events
-- ----------------------------------------------------------
CREATE TABLE `registrations` (
    `reg_id`         INT AUTO_INCREMENT NOT NULL,
    `student_id`     VARCHAR(20) NOT NULL,
    `event_id`       INT         NOT NULL,
    `status`         VARCHAR(20) NOT NULL DEFAULT 'Confirmed',
    `registered_at`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`reg_id`),
    UNIQUE KEY `uk_student_event` (`student_id`, `event_id`),
    CONSTRAINT `fk_reg_student`
        FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT `fk_reg_event`
        FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table 4: audit_logs
-- Stores administrative audit trail of state-changing operations
-- ----------------------------------------------------------
CREATE TABLE `audit_logs` (
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

-- ----------------------------------------------------------
-- Seed Data for Students
-- ----------------------------------------------------------
INSERT INTO `students` (`student_id`, `full_name`, `email`, `mobile`, `department`, `course`, `semester`) VALUES
('25DCS095', 'Om Patel', '25dcs095@charusat.edu.in', '9876543210', 'Information Technology', 'B.Tech', 3),
('25DCS101', 'Aarav Sharma', 'aarav.sharma@charusat.edu.in', '9823456789', 'Computer Science', 'B.Tech', 3),
('25DCS102', 'Priya Patel', 'priya.patel@charusat.edu.in', '9712345678', 'Information Technology', 'B.Tech', 3),
('25BCA045', 'Rohan Mehta', 'rohan.mehta@charusat.edu.in', '9638527410', 'Computer Applications', 'BCA', 2),
('25DCS103', 'Ananya Desai', 'ananya.desai@charusat.edu.in', '9811223344', 'Computer Engineering', 'B.Tech', 3),
('25DCS104', 'Devanshi Shah', 'devanshi.shah@charusat.edu.in', '9822334455', 'Information Technology', 'B.Tech', 3),
('25DCS105', 'Harshil Joshi', 'harshil.joshi@charusat.edu.in', '9833445566', 'Computer Science', 'B.Tech', 3),
('25BCA046', 'Diya Solanki', 'diya.solanki@charusat.edu.in', '9844556677', 'Computer Applications', 'BCA', 2),
('25BCA047', 'Manan Bhatt', 'manan.bhatt@charusat.edu.in', '9855667788', 'Computer Applications', 'BCA', 4),
('25MCA012', 'Krunal Dave', 'krunal.dave@charusat.edu.in', '9866778899', 'Computer Applications', 'MCA', 1),
('25MCA013', 'Riya Trivedi', 'riya.trivedi@charusat.edu.in', '9877889900', 'Computer Applications', 'MCA', 2),
('25MCA014', 'Yash Vora', 'yash.vora@charusat.edu.in', '9888990011', 'Computer Applications', 'MCA', 3),
('25BSC021', 'Tanvi Pandya', 'tanvi.pandya@charusat.edu.in', '9899001122', 'Mathematical Sciences', 'B.Sc', 2),
('25BSC022', 'Meet Parikh', 'meet.parikh@charusat.edu.in', '9711223344', 'Physical Sciences', 'B.Sc', 4),
('25MTC005', 'Neha Kulkarni', 'neha.kulkarni@charusat.edu.in', '9722334455', 'Information Technology', 'M.Tech', 1),
('25MTC006', 'Siddharth Rao', 'siddharth.rao@charusat.edu.in', '9733445566', 'Computer Engineering', 'M.Tech', 2),
('25DCS106', 'Bhavik Panchal', 'bhavik.panchal@charusat.edu.in', '9744556677', 'Information Technology', 'B.Tech', 5),
('25DCS107', 'Ishita Gandhi', 'ishita.gandhi@charusat.edu.in', '9755667788', 'Computer Science', 'B.Tech', 5),
('25BCA048', 'Parth Prajapati', 'parth.prajapati@charusat.edu.in', '9766778899', 'Computer Applications', 'BCA', 1),
('25DCS108', 'Shreya Nair', 'shreya.nair@charusat.edu.in', '9777889900', 'Artificial Intelligence', 'B.Tech', 3),
('25DCS109', 'Vivek Chauhan', 'vivek.chauhan@charusat.edu.in', '9788990011', 'Information Technology', 'B.Tech', 1);

-- ----------------------------------------------------------
-- Seed Data for Events
-- ----------------------------------------------------------
INSERT INTO `events` (`event_id`, `event_name`, `category`, `event_date`, `venue`, `description`) VALUES
(1, 'CHARUSAT Hackathon 2026', 'Technical', '2026-11-15', 'Central Auditorium', 'Annual 24-hour inter-college software hackathon.'),
(2, 'Web Frameworks Hands-on Workshop', 'Technical', '2026-10-30', 'Lab 301, IT Department', 'Hands-on session on PHP PDO, MySQL & modern frontend integration.'),
(3, 'Spoural Cultural Fest', 'Cultural', '2026-12-05', 'University Ground', 'Annual sports and cultural festival.');

-- ----------------------------------------------------------
-- Seed Data for Registrations
-- ----------------------------------------------------------
INSERT INTO `registrations` (`student_id`, `event_id`, `status`) VALUES
('25DCS095', 1, 'Confirmed'),
('25DCS095', 2, 'Confirmed'),
('25DCS101', 2, 'Confirmed'),
('25DCS102', 1, 'Confirmed'),
('25BCA045', 3, 'Pending');

-- ----------------------------------------------------------
-- Seed Data for Users
-- Admin: admin@charusat.edu.in / Admin@2026
-- User:  rohan.verma@charusat.edu.in / SuperSecretPassword@2026
-- ----------------------------------------------------------
INSERT INTO `users` (`full_name`, `email`, `password_hash`, `role`) VALUES
('System Administrator', 'admin@charusat.edu.in', '$2y$10$zvq7fL.QqvNENHvhX1iWB.LBOyo6Rtte/7jlBJoqjU/c5zpm8VhFS', 'admin'),
('Rohan Verma', 'rohan.verma@charusat.edu.in', '$2y$10$1IafF.AqKeaxGGF/2AeEH.L2m.RHgJmPixHnZkbVSO4BnJF2l0RLS', 'user')
ON DUPLICATE KEY UPDATE `role` = VALUES(`role`);

