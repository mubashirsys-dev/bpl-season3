-- BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
-- Complete Database Schema & Initial Data
-- Organised by: BUDDILANE FRIENDS GROUP

CREATE DATABASE IF NOT EXISTS `buddilane_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `buddilane_db`;

-- Drop existing tables if needed (in reverse foreign key order)
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `webhook_events`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `players`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `admins`;

-- 1. Admins Table
CREATE TABLE `admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login` DATETIME NULL,
    INDEX `idx_admin_username` (`username`),
    INDEX `idx_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Settings Table
CREATE TABLE `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Players Table
CREATE TABLE `players` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `registration_id` VARCHAR(50) NOT NULL UNIQUE,
    `full_name` VARCHAR(150) NOT NULL,
    `photo_path` VARCHAR(255) NOT NULL,
    `playing_position` ENUM('Goalkeeper', 'Defence', 'Midfielder', 'Striker') NOT NULL,
    `club_name` VARCHAR(100) NOT NULL,
    `jersey_size` ENUM('S', 'M', 'L', 'XL', 'XXL') NOT NULL,
    `primary_contact` VARCHAR(20) NOT NULL,
    `alternative_contact` VARCHAR(20) NULL,
    `full_address` TEXT NOT NULL,
    `registration_fee` DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    `registration_status` ENUM('PENDING_PAYMENT', 'CONFIRMED', 'CANCELLED') NOT NULL DEFAULT 'PENDING_PAYMENT',
    `payment_status` ENUM('PENDING', 'PAID', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'PENDING',
    `razorpay_order_id` VARCHAR(100) NULL,
    `razorpay_payment_id` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_reg_id` (`registration_id`),
    INDEX `idx_reg_status` (`registration_status`),
    INDEX `idx_pay_status` (`payment_status`),
    INDEX `idx_primary_contact` (`primary_contact`),
    INDEX `idx_order_id` (`razorpay_order_id`),
    INDEX `idx_payment_id` (`razorpay_payment_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Payments Table
CREATE TABLE `payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `player_id` INT UNSIGNED NOT NULL,
    `registration_id` VARCHAR(50) NOT NULL,
    `razorpay_order_id` VARCHAR(100) NOT NULL,
    `razorpay_payment_id` VARCHAR(100) NULL,
    `razorpay_signature` VARCHAR(255) NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
    `status` ENUM('CREATED', 'PENDING', 'PAID', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'CREATED',
    `payment_method` VARCHAR(50) NULL,
    `error_code` VARCHAR(100) NULL,
    `error_description` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`player_id`) REFERENCES `players`(`id`) ON DELETE CASCADE,
    INDEX `idx_pmt_reg_id` (`registration_id`),
    INDEX `idx_pmt_order_id` (`razorpay_order_id`),
    INDEX `idx_pmt_pay_id` (`razorpay_payment_id`),
    INDEX `idx_pmt_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Webhook Events Table (for Idempotency & Audit)
CREATE TABLE `webhook_events` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_id` VARCHAR(100) NOT NULL UNIQUE,
    `event_type` VARCHAR(100) NOT NULL,
    `order_id` VARCHAR(100) NULL,
    `payment_id` VARCHAR(100) NULL,
    `payload` LONGTEXT NULL,
    `status` ENUM('PROCESSED', 'FAILED', 'IGNORED') NOT NULL DEFAULT 'PROCESSED',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_wh_event_id` (`event_id`),
    INDEX `idx_wh_order_id` (`order_id`),
    INDEX `idx_wh_payment_id` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Login Attempts Table (Brute Force Protection)
CREATE TABLE `login_attempts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default Admin
-- Username: admin
-- Password: Admin@BPL2026
INSERT INTO `admins` (`username`, `email`, `password_hash`, `name`) VALUES
('admin', 'admin@buddilane.org', '$2y$10$N44iL1NCDxWxMTpNxP/5f.29BTpAXOrcee4k3VNkRQzSChY9LPcFa', 'Tournament Director');

-- Seed Default Tournament Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('tournament_name', 'BUDDILANE PREMIER LEAGUE'),
('tournament_season', 'SEASON 3 (2026–27)'),
('organised_by', 'BUDDILANE FRIENDS GROUP'),
('registration_fee', '100'),
('registration_open', '1'),
('registration_deadline', '2027-01-31'),
('contact_phone', '+91 98450 12345'),
('contact_email', 'contact@buddilanepremierleague.com'),
('tournament_venue', 'Buddilane Football Ground, Ground Zero'),
('razorpay_mode', 'test'),
('razorpay_key_id', 'rzp_test_51a2b3c4d5e6f7'),
('razorpay_key_secret', 'sk_test_1a2b3c4d5e6f7g8h9i0j'),
('razorpay_webhook_secret', 'whsec_test_secret_9988776655');

