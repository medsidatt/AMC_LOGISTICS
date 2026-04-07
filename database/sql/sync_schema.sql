-- =============================================================================
-- AMC Logistics - Schema Sync Script
-- Run this on Infomaniak AFTER importing the old logistics.sql dump
--
-- Purpose: Add missing tables/columns so the app works with current codebase
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1. DROP OBSOLETE TABLES (old HR/salary leftovers)
-- =========================================================
DROP TABLE IF EXISTS `calculette_salaires`;
DROP TABLE IF EXISTS `employee_components`;
DROP TABLE IF EXISTS `recaps`;
DROP TABLE IF EXISTS `rubriques`;
DROP TABLE IF EXISTS `salary_component`;
DROP TABLE IF EXISTS `tracks`;

-- =========================================================
-- 2. ADD MISSING COLUMN: trigger_km to maintenances
-- =========================================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'maintenances' AND COLUMN_NAME = 'trigger_km');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `maintenances` ADD COLUMN `trigger_km` DECIMAL(15,2) NULL DEFAULT NULL AFTER `kilometers_at_maintenance`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 3. ADD MISSING COLUMN: truck_maintenance_profile_id FK
--    (may already exist from older migration)
-- =========================================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'maintenances' AND COLUMN_NAME = 'truck_maintenance_profile_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `maintenances` ADD COLUMN `truck_maintenance_profile_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `truck_id`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 3b. CREATE truck_maintenance_profiles if missing
-- =========================================================
CREATE TABLE IF NOT EXISTS `truck_maintenance_profiles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `truck_id` BIGINT UNSIGNED NOT NULL,
    `maintenance_type` VARCHAR(50) NOT NULL,
    `interval_km` DECIMAL(10,2) NOT NULL DEFAULT 10000.00,
    `warning_threshold_km` DECIMAL(10,2) NULL DEFAULT NULL,
    `last_maintenance_km` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `next_maintenance_km` DECIMAL(15,2) NOT NULL DEFAULT 10000.00,
    `status` VARCHAR(20) NOT NULL DEFAULT 'green',
    `last_calculated_at` TIMESTAMP NULL DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `deactivated_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `tmp_active_profile_idx` (`truck_id`, `maintenance_type`, `is_active`),
    KEY `truck_maintenance_profile_lookup_idx` (`truck_id`, `status`, `next_maintenance_km`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 3c. CREATE kilometer_trackings if missing
-- =========================================================
CREATE TABLE IF NOT EXISTS `kilometer_trackings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `truck_id` BIGINT UNSIGNED NOT NULL,
    `kilometers` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `date` DATE NOT NULL,
    `notes` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `kilometer_trackings_truck_id_foreign` (`truck_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 3d. CREATE daily_checklists if missing
-- =========================================================
CREATE TABLE IF NOT EXISTS `daily_checklists` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `truck_id` BIGINT UNSIGNED NOT NULL,
    `driver_id` BIGINT UNSIGNED NOT NULL,
    `transport_tracking_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `checklist_date` DATE NOT NULL,
    `start_km` DECIMAL(15,2) NULL DEFAULT NULL,
    `end_km` DECIMAL(15,2) NULL DEFAULT NULL,
    `tire_condition` TEXT NULL DEFAULT NULL,
    `fuel_level` TEXT NULL DEFAULT NULL,
    `fuel_refill` TINYINT(1) NOT NULL DEFAULT 0,
    `fuel_filled` DECIMAL(8,2) NULL DEFAULT NULL,
    `oil_level` TEXT NULL DEFAULT NULL,
    `brakes` TEXT NULL DEFAULT NULL,
    `lights` TEXT NULL DEFAULT NULL,
    `general_condition_notes` TEXT NULL DEFAULT NULL,
    `notes` TEXT NULL DEFAULT NULL,
    `sharepoint_item_id` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `daily_checklists_truck_date_unique` (`truck_id`, `checklist_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 3e. CREATE daily_checklist_issues if missing
-- =========================================================
CREATE TABLE IF NOT EXISTS `daily_checklist_issues` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `daily_checklist_id` BIGINT UNSIGNED NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `flagged` TINYINT(1) NOT NULL DEFAULT 1,
    `issue_notes` TEXT NULL DEFAULT NULL,
    `resolution_notes` TEXT NULL DEFAULT NULL,
    `resolved_at` TIMESTAMP NULL DEFAULT NULL,
    `resolved_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `daily_checklist_issues_category_idx` (`daily_checklist_id`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 3f. CREATE logistics_alerts if missing
-- =========================================================
CREATE TABLE IF NOT EXISTS `logistics_alerts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type` VARCHAR(50) NOT NULL,
    `truck_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `driver_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `checklist_date` DATE NULL DEFAULT NULL,
    `message` TEXT NOT NULL,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `resolved_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `logistics_alerts_type_truck_date_unique` (`type`, `truck_id`, `checklist_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 4. ADD immutability columns to truck_maintenance_profiles
--    (created_by + deactivated_at if missing)
-- =========================================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'truck_maintenance_profiles' AND COLUMN_NAME = 'created_by');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `truck_maintenance_profiles` ADD COLUMN `created_by` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `is_active`, ADD COLUMN `deactivated_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_by`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop the unique constraint if it exists (allows multiple rules per truck+type)
-- Safe: only drops if it exists
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'truck_maintenance_profiles' AND INDEX_NAME = 'truck_maintenance_profile_unique');
SET @sql = IF(@idx_exists > 0, 'ALTER TABLE `truck_maintenance_profiles` DROP INDEX `truck_maintenance_profile_unique`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add active profile index if missing
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'truck_maintenance_profiles' AND INDEX_NAME = 'tmp_active_profile_idx');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `truck_maintenance_profiles` ADD INDEX `tmp_active_profile_idx` (`truck_id`, `maintenance_type`, `is_active`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 5. ADD rotation fields to daily_checklists (if missing)
-- =========================================================
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_checklists' AND COLUMN_NAME = 'start_km');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `daily_checklists` ADD COLUMN `start_km` DECIMAL(15,2) NULL AFTER `checklist_date`, ADD COLUMN `end_km` DECIMAL(15,2) NULL AFTER `start_km`, ADD COLUMN `fuel_filled` DECIMAL(8,2) NULL AFTER `fuel_refill`, ADD COLUMN `transport_tracking_id` BIGINT UNSIGNED NULL AFTER `driver_id`', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================
-- 6. CLEAN migrations table
-- =========================================================

-- Remove corrupted entries
DELETE FROM `migrations` WHERE `migration` LIKE '%php artisan%';
DELETE FROM `migrations` WHERE `migration` LIKE 'App\\%';

-- Remove old migration records for deleted tables
DELETE FROM `migrations` WHERE `migration` LIKE '%employees%';
DELETE FROM `migrations` WHERE `migration` LIKE '%contracts%';
DELETE FROM `migrations` WHERE `migration` LIKE '%contract_types%';
DELETE FROM `migrations` WHERE `migration` LIKE '%explanation_request%';
DELETE FROM `migrations` WHERE `migration` LIKE '%job_title%';
DELETE FROM `migrations` WHERE `migration` LIKE '%specialties%';
DELETE FROM `migrations` WHERE `migration` LIKE '%salary_grid%';
DELETE FROM `migrations` WHERE `migration` LIKE '%components%';
DELETE FROM `migrations` WHERE `migration` LIKE '%payroll%';
DELETE FROM `migrations` WHERE `migration` LIKE '%check_in%';
DELETE FROM `migrations` WHERE `migration` LIKE '%checkin%';
DELETE FROM `migrations` WHERE `migration` LIKE '%employee_component%';
DELETE FROM `migrations` WHERE `migration` LIKE '%adjustments%';
DELETE FROM `migrations` WHERE `migration` LIKE '%brands%';
DELETE FROM `migrations` WHERE `migration` LIKE '%categories_table%';
DELETE FROM `migrations` WHERE `migration` LIKE '%products%';
DELETE FROM `migrations` WHERE `migration` LIKE '%product_variables%';
DELETE FROM `migrations` WHERE `migration` LIKE '%salaries%';
DELETE FROM `migrations` WHERE `migration` LIKE '%salary_component%';
DELETE FROM `migrations` WHERE `migration` LIKE '%services_table%';
DELETE FROM `migrations` WHERE `migration` LIKE '%departments%';
DELETE FROM `migrations` WHERE `migration` LIKE '%rubrique%';
DELETE FROM `migrations` WHERE `migration` LIKE '%leaves%';
DELETE FROM `migrations` WHERE `migration` LIKE '%configuration_rules%';
DELETE FROM `migrations` WHERE `migration` LIKE '%pay_split%';
DELETE FROM `migrations` WHERE `migration` LIKE '%project_component%';
DELETE FROM `migrations` WHERE `migration` LIKE '%perks%';
DELETE FROM `migrations` WHERE `migration` LIKE '%blogs%';
DELETE FROM `migrations` WHERE `migration` LIKE '%posts%';
DELETE FROM `migrations` WHERE `migration` LIKE '%calculette%';
DELETE FROM `migrations` WHERE `migration` LIKE '%recaps%';
DELETE FROM `migrations` WHERE `migration` LIKE '%banks%';
DELETE FROM `migrations` WHERE `migration` LIKE '%donors%';
DELETE FROM `migrations` WHERE `migration` LIKE '%beneficiaries%';
DELETE FROM `migrations` WHERE `migration` LIKE '%bank_accounts%';
DELETE FROM `migrations` WHERE `migration` LIKE '%currencies%';
DELETE FROM `migrations` WHERE `migration` LIKE '%forms_table%';
DELETE FROM `migrations` WHERE `migration` LIKE '%tracks_table%';
DELETE FROM `migrations` WHERE `migration` LIKE '%archive_non_logistics%';
DELETE FROM `migrations` WHERE `migration` LIKE '%is_expatriate%';
DELETE FROM `migrations` WHERE `migration` LIKE '%paid_leave_method%';
DELETE FROM `migrations` WHERE `migration` LIKE '%show_leave_balance%';
DELETE FROM `migrations` WHERE `migration` LIKE '%edit_email_column_in%';
DELETE FROM `migrations` WHERE `migration` LIKE '%frequancy%';
DELETE FROM `migrations` WHERE `migration` LIKE '%salary_statement%';
DELETE FROM `migrations` WHERE `migration` LIKE '%brut_paid%';
DELETE FROM `migrations` WHERE `migration` LIKE '%brut_amount%';
DELETE FROM `migrations` WHERE `migration` LIKE '%change_first_name%';
DELETE FROM `migrations` WHERE `migration` LIKE '%badge_number%';
DELETE FROM `migrations` WHERE `migration` LIKE '%edit_columns_employees%';
DELETE FROM `migrations` WHERE `migration` LIKE '%nullabel_to_employees%';
DELETE FROM `migrations` WHERE `migration` LIKE '%payment_type_to_employees%';
DELETE FROM `migrations` WHERE `migration` LIKE '%base_calculation_to_employees%';
DELETE FROM `migrations` WHERE `migration` LIKE '%contract_base_to_employees%';
DELETE FROM `migrations` WHERE `migration` LIKE '%from_base_salary_column%';
DELETE FROM `migrations` WHERE `migration` LIKE '%add_column_to_employees%';
DELETE FROM `migrations` WHERE `migration` LIKE '%nature_column_to_pay%';
DELETE FROM `migrations` WHERE `migration` LIKE '%net_pay_column%';
DELETE FROM `migrations` WHERE `migration` LIKE '%columns_to_payroll%';
DELETE FROM `migrations` WHERE `migration` LIKE '%remove_culumns%';
DELETE FROM `migrations` WHERE `migration` LIKE '%pay_split_type%';
DELETE FROM `migrations` WHERE `migration` LIKE '%base_nature_column%';
DELETE FROM `migrations` WHERE `migration` LIKE '%add_rc_to_projects%';

-- Record missing migration
INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES
    ('2026_04_07_040000_add_profile_id_to_maintenances_table', 100);

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- DONE. Now run: php artisan migrate
-- This will pick up any remaining migrations not yet recorded.
-- =========================================================
