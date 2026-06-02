-- ================================================================
-- NETCRAFTER — MIGRATION CAHIER DES CHARGES
-- ================================================================
--
-- Tables ajoutées : cahier_charges, cahier_files
--
-- Base PRODUCTION (Hostinger) :
--   u264396140_netcrafternige  ← sélectionner cette base
--
-- Base LOCAL (WAMP) :
--   netcrafter                 ← sélectionner cette base
--
-- Comment l'utiliser :
--   phpMyAdmin → Sélectionner la bonne base → Importer ce fichier
--   OU exécuter directement dans le terminal MySQL :
--     mysql -u root netcrafter < migration_cahier.sql            (local)
--     mysql -h localhost -u u264396140_netcrefternige \
--           -p u264396140_netcrafternige < migration_cahier.sql  (prod)
--
-- Idempotent : peut être relancé sans erreur (IF NOT EXISTS).
-- ================================================================


-- ----------------------------------------------------------------
-- 1. cahier_charges — Liens partagés et réponses clients
-- ----------------------------------------------------------------
-- Un enregistrement est créé par l'admin à la génération du lien.
-- Le client complète ensuite tous les champs via le formulaire.
-- submitted_at est NULL tant que le client n'a pas soumis.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cahier_charges` (
    `id`              INT          NOT NULL AUTO_INCREMENT,
    `token`           VARCHAR(64)  NOT NULL                 COMMENT 'Token unique du lien partagé (32 hex chars)',
    `label`           VARCHAR(255) DEFAULT NULL             COMMENT 'Étiquette interne admin (nom projet / client)',

    -- Informations client
    `client_name`     VARCHAR(255) DEFAULT NULL,
    `client_email`    VARCHAR(255) DEFAULT NULL,
    `client_phone`    VARCHAR(100) DEFAULT NULL,
    `client_company`  VARCHAR(255) DEFAULT NULL,

    -- Informations projet
    `project_name`    VARCHAR(255) DEFAULT NULL,
    `project_type`    VARCHAR(100) DEFAULT NULL             COMMENT 'site_vitrine | ecommerce | application_web | application_mobile | refonte | blog | portail | autre',
    `description`     TEXT         DEFAULT NULL,
    `objectives`      TEXT         DEFAULT NULL,
    `target_audience` TEXT         DEFAULT NULL,

    -- Fonctionnalités & design
    `features`        TEXT         DEFAULT NULL             COMMENT 'JSON array des valeurs cochées',
    `custom_features` TEXT         DEFAULT NULL,
    `design_style`    VARCHAR(100) DEFAULT NULL             COMMENT 'moderne | minimaliste | corporate | creatif | tech | classique',
    `color_prefs`     TEXT         DEFAULT NULL,
    `has_brand`       TINYINT(1)   NOT NULL DEFAULT 0       COMMENT '1 = charte graphique existante',
    `brand_details`   TEXT         DEFAULT NULL,
    `ref_urls`        TEXT         DEFAULT NULL,

    -- Contraintes
    `budget`          VARCHAR(100) DEFAULT NULL             COMMENT '<200k | 200-500k | 500k-1m | 1m-3m | >3m | a_definir',
    `deadline`        VARCHAR(100) DEFAULT NULL,
    `cms_pref`        VARCHAR(100) DEFAULT NULL             COMMENT 'wordpress | custom | shopify | prestashop | autre',
    `hosting_pref`    VARCHAR(100) DEFAULT NULL             COMMENT 'netcrafter | existant | local',
    `notes`           TEXT         DEFAULT NULL,

    -- Gestion admin
    `status`          ENUM('pending','in_review','validated','archived')
                                   NOT NULL DEFAULT 'pending',
    `admin_notes`     TEXT         DEFAULT NULL             COMMENT 'Notes internes visibles uniquement côté admin',

    -- Dates
    `submitted_at`    DATETIME     DEFAULT NULL             COMMENT 'NULL = formulaire non encore soumis',
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE  KEY `uq_token`       (`token`),
    KEY           `idx_status`   (`status`),
    KEY           `idx_email`    (`client_email`),
    KEY           `idx_submitted`(`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cahiers des charges clients — liens partagés + réponses';


-- ----------------------------------------------------------------
-- 2. cahier_files — Fichiers uploadés par les clients
-- ----------------------------------------------------------------
-- Stockés dans uploads/cahier/{cahier_id}/
-- Le téléchargement passe par admin/cahier.php?action=download
-- pour garantir que seul un admin authentifié y a accès.
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cahier_files` (
    `id`            INT          NOT NULL AUTO_INCREMENT,
    `cahier_id`     INT          NOT NULL,
    `original_name` VARCHAR(500) NOT NULL                   COMMENT 'Nom original du fichier (affiché / téléchargé)',
    `stored_name`   VARCHAR(500) NOT NULL                   COMMENT 'Nom stocké sur le disque (uniqid)',
    `file_type`     VARCHAR(100) DEFAULT NULL               COMMENT 'MIME type',
    `file_size`     INT          NOT NULL DEFAULT 0         COMMENT 'Taille en octets',
    `uploaded_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_cahier_id` (`cahier_id`),
    CONSTRAINT `fk_cf_cahier` FOREIGN KEY (`cahier_id`)
        REFERENCES `cahier_charges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fichiers attachés à un cahier des charges (charte, logo, docs…)';


-- ================================================================
-- FIN — 2 tables créées
-- ================================================================
--
-- Résumé du module :
--   cahier_charges   — 1 ligne / lien partagé, complétée par le client
--   cahier_files     — N lignes / fichiers uploadés par le client
--
-- Répertoire d'upload (créé automatiquement par PHP) :
--   uploads/cahier/{id}/   (chemin relatif à la racine du projet)
--
-- Pages associées :
--   /cahier/index.php?token=XXXX   ← formulaire client (lien partageable)
--   /admin/cahier.php              ← gestion admin
--
-- ================================================================
