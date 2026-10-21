-- Schemat bazy danych aplikacji „Harmonogram Pracy".
-- Dla NOWEJ instalacji: zaimportuj ten plik (phpMyAdmin -> Importuj albo `mysql -u root < database/schema.sql`).
-- Dla ISTNIEJĄCEJ bazy nic nie trzeba robić – aplikacja sama dodaje brakującą kolumnę `passwordChanged`
-- i w razie potrzeby konwertuje tabele grafików na utf8mb4 (potrzebne dla kodu „UŻ").

CREATE DATABASE IF NOT EXISTS `hematologia`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hematologia`;

CREATE TABLE IF NOT EXISTS `admins` (
    `adminId`  INT AUTO_INCREMENT PRIMARY KEY,
    `login`    VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employees` (
    `employeeId`      INT AUTO_INCREMENT PRIMARY KEY,
    `name`            VARCHAR(60)  NOT NULL,
    `surname`         VARCHAR(60)  NOT NULL,
    `login`           VARCHAR(130) NOT NULL UNIQUE,
    `password`        VARCHAR(255) NOT NULL,
    `passwordChanged` TINYINT(1)   NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indywidualne normy pracowników są zapisane osobno dla każdego grafiku.
CREATE TABLE IF NOT EXISTS `schedule_norms` (
    `scheduleTable` VARCHAR(100) NOT NULL,
    `employeeId` INT NOT NULL,
    `workDays` TINYINT UNSIGNED NOT NULL,
    `shiftHours` TINYINT UNSIGNED NOT NULL DEFAULT 12,
    `normMinutes` SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (`scheduleTable`, `employeeId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Własne dyżury wyrównawcze administrator definiuje oddzielnie dla każdego miesiąca.
CREATE TABLE IF NOT EXISTS `schedule_custom_shifts` (
    `scheduleTable` VARCHAR(100) NOT NULL,
    `code` VARCHAR(15) NOT NULL,
    `minutes` SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (`scheduleTable`, `code`),
    UNIQUE KEY `unique_schedule_minutes` (`scheduleTable`, `minutes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabele grafików (np. `2026_Październik`) tworzy sama aplikacja (Nowy grafik):
--    employeeId INT PRIMARY KEY, `1` … `31` VARCHAR(15)
-- Kody zmian: D, N, D/N, R, UW, UŻ, L4.
-- 1. Pierwszego administratora utworzysz poleceniem:
--    php tools/create_admin.php <login> <hasło>

-- 2. Administratora możesz utworzyć również za pomocą phpmyadmin:
--    Powershell: php -r "echo password_hash('TWOJE_HASLO', PASSWORD_DEFAULT) . PHP_EOL;" (generujesz hash dla hasla administratora)

--    Następnie wykonaj poniższe zapytanie: 
--    USE `hematologia`;
--    INSERT INTO `admins` (`login`, `password`)
--    VALUES ('admin', 'WKLEJ_TUTAJ_HASH')
--    ON DUPLICATE KEY UPDATE
--        `password` = VALUES(`password`);

-- Zmiana wymiarów pracy możliwa jest w pliku includes/schedule.php
