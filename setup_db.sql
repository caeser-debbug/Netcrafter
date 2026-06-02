-- ============================================================
-- Netcrafter Formation DB - Tables manquantes
-- Exécuter dans phpMyAdmin sur la base netcrafter_formation
-- ============================================================

USE netcrafter_formation;

-- ============================================================
-- Table video_progress (suivi de progression des vidéos)
-- ============================================================
CREATE TABLE IF NOT EXISTS `video_progress` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NOT NULL,
    `video_id`        INT UNSIGNED NOT NULL,
    `watched_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_completed`    TINYINT(1) NOT NULL DEFAULT 0,
    `last_watched`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_video` (`user_id`, `video_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_video_id` (`video_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table admin_logs (journal d'activité de l'admin)
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`    INT UNSIGNED NOT NULL,
    `action`      VARCHAR(100) NOT NULL,
    `description` TEXT,
    `ip_address`  VARCHAR(45) DEFAULT NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_id` (`admin_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table certificates (si elle n'existe pas encore)
-- ============================================================
CREATE TABLE IF NOT EXISTS `certificates` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED NOT NULL,
    `formation_id`   INT UNSIGNED NOT NULL,
    `certificate_no` VARCHAR(50) NOT NULL,
    `issued_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_formation` (`user_id`, `formation_id`),
    KEY `idx_certificate_no` (`certificate_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Ajout de la colonne slug dans formations si manquante
-- ============================================================
ALTER TABLE `formations`
    ADD COLUMN IF NOT EXISTS `slug` VARCHAR(255) DEFAULT NULL AFTER `title`;

-- ============================================================
-- Vérification et correction du charset des tables principales
-- ============================================================
ALTER TABLE `formations`          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `formation_modules`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `formation_videos`    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `formation_subscriptions` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
