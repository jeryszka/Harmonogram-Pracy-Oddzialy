-- Migracja istniejącej bazy `hematologia`.
-- Dodaje kolumnę flagi hasła oraz tabele norm i własnych dyżurów.
USE `hematologia`;

SET @add_password_changed = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'employees'
          AND COLUMN_NAME = 'passwordChanged'
    ),
    'SELECT 1',
    'ALTER TABLE `employees` ADD COLUMN `passwordChanged` TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE add_password_changed_stmt FROM @add_password_changed;
EXECUTE add_password_changed_stmt;
DEALLOCATE PREPARE add_password_changed_stmt;

CREATE TABLE IF NOT EXISTS `schedule_norms` (
    `scheduleTable` VARCHAR(100) NOT NULL,
    `employeeId` INT NOT NULL,
    `workDays` TINYINT UNSIGNED NOT NULL,
    `shiftHours` TINYINT UNSIGNED NOT NULL DEFAULT 12,
    `normMinutes` SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (`scheduleTable`, `employeeId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @add_shift_hours = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'schedule_norms'
          AND COLUMN_NAME = 'shiftHours'
    ),
    'SELECT 1',
    'ALTER TABLE `schedule_norms` ADD COLUMN `shiftHours` TINYINT UNSIGNED NOT NULL DEFAULT 12 AFTER `workDays`'
);
PREPARE add_shift_hours_stmt FROM @add_shift_hours;
EXECUTE add_shift_hours_stmt;
DEALLOCATE PREPARE add_shift_hours_stmt;

CREATE TABLE IF NOT EXISTS `schedule_custom_shifts` (
    `scheduleTable` VARCHAR(100) NOT NULL,
    `code` VARCHAR(15) NOT NULL,
    `minutes` SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (`scheduleTable`, `code`),
    UNIQUE KEY `unique_schedule_minutes` (`scheduleTable`, `minutes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
