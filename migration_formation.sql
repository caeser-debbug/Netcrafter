-- ================================================================
-- NETCRAFTER — MIGRATION FORMATIONS
-- Base : u264396140_formation
-- ================================================================
-- Sélectionner u264396140_formation dans phpMyAdmin avant import.
-- ================================================================

-- ----------------------------------------------------------------
-- 1. users
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `firstname`   VARCHAR(80)  NOT NULL,
    `lastname`    VARCHAR(80)  NOT NULL,
    `email`       VARCHAR(150) NOT NULL,
    `password`    VARCHAR(255) NOT NULL,
    `phone`       VARCHAR(20)  DEFAULT NULL,
    `mac_address` VARCHAR(17)  DEFAULT NULL,
    `is_admin`    TINYINT(1)   NOT NULL DEFAULT 0,
    `status`      ENUM('active','banned') NOT NULL DEFAULT 'active',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login`  TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 2. formation_categories
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `formation_categories` (
    `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`   VARCHAR(100) NOT NULL,
    `icon`   VARCHAR(60)  NOT NULL DEFAULT 'fa-graduation-cap',
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `formation_categories` (`id`, `name`, `icon`) VALUES
(1, 'Développement Web', 'fa-code'),
(2, 'Design Graphique',  'fa-palette'),
(3, 'Marketing Digital', 'fa-bullhorn'),
(4, 'Cybersécurité',    'fa-shield-alt'),
(5, 'Base de Données',  'fa-database'),
(6, 'Réseaux & Infra',  'fa-network-wired');

-- ----------------------------------------------------------------
-- 3. formations
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `formations` (
    `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `category_id`       INT UNSIGNED  NOT NULL,
    `title`             VARCHAR(255)  NOT NULL,
    `slug`              VARCHAR(280)  DEFAULT NULL,
    `short_description` VARCHAR(500)  DEFAULT NULL,
    `description`       TEXT          DEFAULT NULL,
    `level`             ENUM('debutant','intermediaire','avance') NOT NULL DEFAULT 'debutant',
    `duration`          VARCHAR(50)   DEFAULT NULL,
    `price_per_month`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `cover_image`       VARCHAR(500)  DEFAULT NULL,
    `instructor_name`   VARCHAR(100)  NOT NULL DEFAULT 'Netcrafter',
    `requirements`      TEXT          DEFAULT NULL,
    `objectives`        TEXT          DEFAULT NULL,
    `is_featured`       TINYINT(1)    NOT NULL DEFAULT 0,
    `status`            ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `formations_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 4. formation_modules
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `formation_modules` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `formation_id` INT UNSIGNED NOT NULL,
    `title`        VARCHAR(255) NOT NULL,
    `order_number` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 5. formation_videos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `formation_videos` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `module_id`    INT UNSIGNED NOT NULL,
    `title`        VARCHAR(255) NOT NULL,
    `url`          VARCHAR(500) NOT NULL,
    `duration`     VARCHAR(20)  DEFAULT NULL,
    `order_number` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 6. formation_subscriptions
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `formation_subscriptions` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`             INT UNSIGNED  NOT NULL,
    `formation_id`        INT UNSIGNED  NOT NULL,
    `payment_method`      ENUM('nita','amana','zeyna','niya','gratuit') NOT NULL DEFAULT 'nita',
    `payment_proof`       VARCHAR(500)  DEFAULT NULL,
    `subscription_months` INT           NOT NULL DEFAULT 1,
    `amount_paid`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `start_date`          DATE          NOT NULL,
    `end_date`            DATE          NOT NULL,
    `status`              ENUM('pending','active','rejected','cancelled','expired') NOT NULL DEFAULT 'pending',
    `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 7. formation_favorites
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `formation_favorites` (
    `user_id`      INT UNSIGNED NOT NULL,
    `formation_id` INT UNSIGNED NOT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `formation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 8. video_progress
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `video_progress` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NOT NULL,
    `video_id`        INT UNSIGNED NOT NULL,
    `watched_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_completed`    TINYINT(1)   NOT NULL DEFAULT 0,
    `last_watched`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `vp_user_video_unique` (`user_id`, `video_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 9. video_notes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `video_notes` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `video_id`   INT UNSIGNED NOT NULL,
    `content`    TEXT         NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `vn_user_video_unique` (`user_id`, `video_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 10. formation_quizzes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `formation_quizzes` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `formation_id`  INT UNSIGNED NOT NULL,
    `title`         VARCHAR(255) NOT NULL,
    `description`   TEXT         DEFAULT NULL,
    `passing_score` INT          NOT NULL DEFAULT 70,
    `time_limit`    INT          DEFAULT NULL,
    `max_attempts`  INT          NOT NULL DEFAULT 3,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 11. quiz_questions
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quiz_questions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quiz_id`       INT UNSIGNED NOT NULL,
    `question_text` TEXT         NOT NULL,
    `question_type` ENUM('multiple_choice','true_false','short_answer') NOT NULL DEFAULT 'multiple_choice',
    `points`        INT          NOT NULL DEFAULT 1,
    `order_number`  INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 12. quiz_answers
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quiz_answers` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id`  INT UNSIGNED NOT NULL,
    `answer_text`  TEXT         NOT NULL,
    `is_correct`   TINYINT(1)   NOT NULL DEFAULT 0,
    `order_number` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 13. quiz_attempts
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quiz_attempts` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `quiz_id`      INT UNSIGNED  NOT NULL,
    `user_id`      INT UNSIGNED  NOT NULL,
    `score`        DECIMAL(5,2)  NOT NULL DEFAULT 0,
    `passed`       TINYINT(1)    NOT NULL DEFAULT 0,
    `time_taken`   INT UNSIGNED  DEFAULT NULL,
    `answers_data` TEXT          DEFAULT NULL,
    `completed_at` TIMESTAMP     NULL DEFAULT NULL,
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 14. forum_topics
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_topics` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `formation_id` INT UNSIGNED DEFAULT NULL,
    `title`        VARCHAR(255) NOT NULL,
    `content`      TEXT         NOT NULL,
    `is_pinned`    TINYINT(1)   NOT NULL DEFAULT 0,
    `is_locked`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 15. forum_replies
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_replies` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `topic_id`    INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NOT NULL,
    `content`     TEXT         NOT NULL,
    `is_solution` TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 16. certificates
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `certificates` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED NOT NULL,
    `formation_id`   INT UNSIGNED NOT NULL,
    `certificate_no` VARCHAR(50)  NOT NULL,
    `issued_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `cert_user_formation_unique` (`user_id`, `formation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 17. admin_logs
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`    INT UNSIGNED NOT NULL,
    `action`      VARCHAR(100) NOT NULL,
    `description` TEXT         DEFAULT NULL,
    `ip_address`  VARCHAR(45)  DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 18. user_settings
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_settings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED NOT NULL,
    `setting_key`   VARCHAR(100) NOT NULL,
    `setting_value` TEXT         DEFAULT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `us_user_key_unique` (`user_id`, `setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 19. admin_settings
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_settings` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `platform_name`    VARCHAR(100)  NOT NULL DEFAULT 'Netcrafter Formations',
    `contact_email`    VARCHAR(150)  DEFAULT NULL,
    `whatsapp_number`  VARCHAR(20)   DEFAULT '22788672115',
    `currency`         VARCHAR(10)   NOT NULL DEFAULT 'FCFA',
    `maintenance_mode` TINYINT(1)    NOT NULL DEFAULT 0,
    `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `admin_settings` (`id`, `platform_name`, `contact_email`, `whatsapp_number`, `currency`)
VALUES (1, 'Netcrafter Formations', 'contact@netcrafterniger.com', '22788672115', 'FCFA');

-- ================================================================
-- FIN — 19 tables dans u264396140_formation
-- ================================================================
