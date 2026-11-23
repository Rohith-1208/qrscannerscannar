-- ==============================================================================
-- College Equipment Maintenance Management System
-- Database Schema: college_maintenance_db
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `college_maintenance_db` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `college_maintenance_db`;

-- 1. ADMINS TABLE
CREATE TABLE IF NOT EXISTS `admins` (
    `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. AGENTS TABLE
CREATE TABLE IF NOT EXISTS `agents` (
    `agent_id` VARCHAR(50) PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_agent_email` (`email`),
    INDEX `idx_agent_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. SYSTEMS (EQUIPMENT) TABLE
CREATE TABLE IF NOT EXISTS `systems` (
    `system_id` VARCHAR(50) PRIMARY KEY,
    `equipment_name` VARCHAR(100) NOT NULL,
    `equipment_type` VARCHAR(50) NOT NULL DEFAULT 'Desktop Computer',
    `department` VARCHAR(100) NOT NULL,
    `building` VARCHAR(100) NOT NULL,
    `room_number` VARCHAR(50) NOT NULL,
    `latitude` DECIMAL(10, 8) NOT NULL,
    `longitude` DECIMAL(11, 8) NOT NULL,
    `allowed_radius` INT NOT NULL DEFAULT 10,
    `status` ENUM('working', 'issue_identified', 'under_maintenance', 'disabled') NOT NULL DEFAULT 'working',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_system_dept` (`department`),
    INDEX `idx_system_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. QR CODES TABLE
CREATE TABLE IF NOT EXISTS `qr_codes` (
    `qr_id` INT AUTO_INCREMENT PRIMARY KEY,
    `system_id` VARCHAR(50) NOT NULL,
    `qr_version` INT NOT NULL DEFAULT 1,
    `signature` VARCHAR(64) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`system_id`) REFERENCES `systems`(`system_id`) ON DELETE CASCADE,
    INDEX `idx_qr_system_active` (`system_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. CHECKLIST TEMPLATES TABLE
CREATE TABLE IF NOT EXISTS `checklist_templates` (
    `template_id` INT AUTO_INCREMENT PRIMARY KEY,
    `equipment_type` VARCHAR(50) NOT NULL UNIQUE,
    `template_name` VARCHAR(100) NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. CHECKLIST QUESTIONS TABLE
CREATE TABLE IF NOT EXISTS `checklist_questions` (
    `question_id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_id` INT NOT NULL,
    `question_text` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_required` TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (`template_id`) REFERENCES `checklist_templates`(`template_id`) ON DELETE CASCADE,
    INDEX `idx_question_template` (`template_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. MAINTENANCE REPORTS TABLE
CREATE TABLE IF NOT EXISTS `maintenance_reports` (
    `report_id` VARCHAR(60) PRIMARY KEY,
    `system_id` VARCHAR(50) NOT NULL,
    `agent_id` VARCHAR(50) NOT NULL,
    `maintenance_date` DATE NOT NULL,
    `maintenance_time` TIME NOT NULL,
    `overall_status` ENUM('No issues reported', 'Issues identified') NOT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`system_id`) REFERENCES `systems`(`system_id`) ON DELETE RESTRICT,
    FOREIGN KEY (`agent_id`) REFERENCES `agents`(`agent_id`) ON DELETE RESTRICT,
    INDEX `idx_report_agent` (`agent_id`),
    INDEX `idx_report_system` (`system_id`),
    INDEX `idx_report_date` (`maintenance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. LOCATION VERIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS `location_verifications` (
    `verification_id` INT AUTO_INCREMENT PRIMARY KEY,
    `report_id` VARCHAR(60) NOT NULL UNIQUE,
    `registered_latitude` DECIMAL(10, 8) NOT NULL,
    `registered_longitude` DECIMAL(11, 8) NOT NULL,
    `agent_latitude` DECIMAL(10, 8) NOT NULL,
    `agent_longitude` DECIMAL(11, 8) NOT NULL,
    `calculated_distance` DECIMAL(8, 2) NOT NULL,
    `gps_accuracy` DECIMAL(8, 2) NOT NULL,
    `verification_status` ENUM('verified', 'failed') NOT NULL,
    `verified_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`report_id`) REFERENCES `maintenance_reports`(`report_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. MAINTENANCE ANSWERS TABLE
CREATE TABLE IF NOT EXISTS `maintenance_answers` (
    `answer_id` INT AUTO_INCREMENT PRIMARY KEY,
    `report_id` VARCHAR(60) NOT NULL,
    `question_id` INT NOT NULL,
    `answer` ENUM('YES', 'NO') NOT NULL,
    `issue_description` TEXT NULL,
    FOREIGN KEY (`report_id`) REFERENCES `maintenance_reports`(`report_id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `checklist_questions`(`question_id`) ON DELETE RESTRICT,
    INDEX `idx_answer_report` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. AUTH TOKENS TABLE (For stateless REST API authentication)
CREATE TABLE IF NOT EXISTS `auth_tokens` (
    `token_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` VARCHAR(50) NOT NULL,
    `user_type` ENUM('agent', 'admin') NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_auth_token_lookup` (`token_hash`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- INITIAL SEED DATA
-- ==============================================================================

-- 1. Default Admin Account (Password: admin123)
-- Hash generated using password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO `admins` (`admin_id`, `name`, `email`, `password_hash`, `created_at`)
VALUES (1, 'College System Administrator', 'admin@college.edu', '$2y$10$w81V7jWkKgzpE2hRk0O2g.W1m82Fk4s/E3RzH2JzPjE9K1D4a1K2C', NOW())
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 2. Default Service Agent (ID: AGT-001, Password: agent123)
-- Hash generated using password_hash('agent123', PASSWORD_BCRYPT)
INSERT INTO `agents` (`agent_id`, `name`, `email`, `password_hash`, `status`, `created_at`)
VALUES ('AGT-001', 'John Doe (Lead Technician)', 'agent@college.edu', '$2y$10$0d7eGk0M7Z8Jk3Tz9Y1Yv.m8Gz7L2kQ1oP3kE4R5J6H7K8L9M0N1O', 'active', NOW())
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 3. Default Checklist Template for Desktop Computers
INSERT INTO `checklist_templates` (`template_id`, `equipment_type`, `template_name`, `status`)
VALUES (1, 'Desktop Computer', 'Standard Desktop PC Maintenance Checklist', 'active')
ON DUPLICATE KEY UPDATE `template_name`=VALUES(`template_name`);

-- 4. Initial 7 Checklist Questions specified in project requirements
INSERT INTO `checklist_questions` (`question_id`, `template_id`, `question_text`, `sort_order`, `is_required`) VALUES
(1, 1, 'Is the computer switching ON?', 1, 1),
(2, 1, 'Is the monitor working properly?', 2, 1),
(3, 1, 'Is the keyboard working?', 3, 1),
(4, 1, 'Is the mouse working?', 4, 1),
(5, 1, 'Is the internet connection working?', 5, 1),
(6, 1, 'Is the operating system functioning properly?', 6, 1),
(7, 1, 'Are all required software applications working?', 7, 1)
ON DUPLICATE KEY UPDATE `question_text`=VALUES(`question_text`);

-- 5. Seed 10 Computer Lab Systems (Computer Lab 1 - Department of CSE)
-- Coordinates calibrated around Example: Lat: 13.075100, Lon: 80.117000 (Permitted Radius 10m)
INSERT INTO `systems` (`system_id`, `equipment_name`, `equipment_type`, `department`, `building`, `room_number`, `latitude`, `longitude`, `allowed_radius`, `status`) VALUES
('SYS-001', 'Desktop Workstation 01', 'Desktop Computer', 'Computer Science & Engineering', 'Tech Block A', 'Lab 101', 13.07510000, 80.11700000, 10, 'working'),
('SYS-002', 'Desktop Workstation 02', 'Desktop Computer', 'Computer Science & Engineering', 'Tech Block A', 'Lab 101', 13.07510000, 80.11700000, 10, 'working'),
('SYS-003', 'Desktop Workstation 03', 'Desktop Computer', 'Computer Science & Engineering', 'Tech Block A', 'Lab 101', 13.07510000, 80.11700000, 10, 'working'),
('SYS-004', 'Desktop Workstation 04', 'Desktop Computer', 'Computer Science & Engineering', 'Tech Block A', 'Lab 101', 13.07510000, 80.11700000, 10, 'working'),
('SYS-005', 'Desktop Workstation 05', 'Desktop Computer', 'Computer Science & Engineering', 'Tech Block A', 'Lab 101', 13.07510000, 80.11700000, 10, 'working'),
('SYS-006', 'Desktop Workstation 06', 'Desktop Computer', 'Computer Science & Engineering', 'Tech Block A', 'Lab 101', 13.07510000, 80.11700000, 10, 'working'),
('SYS-007', 'Desktop Workstation 07', 'Desktop Computer', 'Computer Science & Engineering', 'Tech Block A', 'Lab 101', 13.07510000, 80.11700000, 10, 'working'),
('SYS-008', 'Desktop Workstation 08', 'Desktop Computer', 'Computer Science & Engineering', 'Tech Block A', 'Lab 101', 13.07510000, 80.11700000, 10, 'working'),
('SYS-009', 'Desktop Workstation 09', 'Desktop Computer', 'Computer Science & Engineering', 'Tech Block A', 'Lab 101', 13.07510000, 80.11700000, 10, 'working'),
('SYS-010', 'Desktop Workstation 10', 'Desktop Computer', 'Computer Science & Engineering', 'Tech Block A', 'Lab 101', 13.07510000, 80.11700000, 10, 'working')
ON DUPLICATE KEY UPDATE `equipment_name`=VALUES(`equipment_name`);
