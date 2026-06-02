-- ================================================================
-- NETCRAFTER — MIGRATION COMPLÈTE v2026
-- ================================================================
--
-- Crée l'intégralité du schéma des trois bases de données.
-- Idempotent : peut être relancé sans erreur (IF NOT EXISTS).
--
-- Bases de données PRODUCTION (Hostinger) :
--   • u264396140_netcrafternige  — Site vitrine (devis, blog, portfolio)
--   • u264396140_shop            — Boutique (produits, commandes, clients, admin)
--   • u264396140_formation       — Plateforme de formations
--
-- Bases de données LOCAL (WAMP) :
--   • netcrafter                 — Vitrine + Shop partagés en local
--   • netcrafter_formation       — Formations en local
--
-- Ordre d'exécution recommandé :
--   phpMyAdmin → Importer → Choisir ce fichier → Exécuter
--
-- ================================================================


-- ================================================================
-- ██████████  BASE 1 : u264396140_netcrafternige  ██████████
-- ██████████    Site vitrine — devis, blog, portfolio   ██████████
-- ================================================================

CREATE DATABASE IF NOT EXISTS `u264396140_netcrafternige`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `u264396140_netcrafternige`;

-- ----------------------------------------------------------------
-- 1. settings — Paramètres globaux du site vitrine
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `site_name`         VARCHAR(100)  NOT NULL DEFAULT 'Netcrafter',
    `site_description`  TEXT          DEFAULT NULL,
    `contact_email`     VARCHAR(100)  DEFAULT NULL,
    `contact_phone`     VARCHAR(20)   DEFAULT NULL,
    `address`           TEXT          DEFAULT NULL,
    `facebook_url`      VARCHAR(255)  DEFAULT NULL,
    `twitter_url`       VARCHAR(255)  DEFAULT NULL,
    `instagram_url`     VARCHAR(255)  DEFAULT NULL,
    `linkedin_url`      VARCHAR(255)  DEFAULT NULL,
    `analytics_code`    VARCHAR(50)   DEFAULT NULL,
    `currency`          VARCHAR(10)   NOT NULL DEFAULT 'FCFA',
    `maintenance_mode`  TINYINT(1)    NOT NULL DEFAULT 0,
    `logo_path`         VARCHAR(255)  NOT NULL DEFAULT 'image/logo-n.png',
    `favicon_path`      VARCHAR(255)  NOT NULL DEFAULT 'image/favicon.ico',
    `updated_at`        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`id`, `site_name`, `contact_email`, `contact_phone`, `address`, `currency`)
VALUES (1, 'Netcrafter', 'contact@netcrafterniger.com', '+227 88 67 21 15', 'Niamey, Niger', 'FCFA');

-- ----------------------------------------------------------------
-- 2. demandes_devis — Demandes de devis reçues
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `demandes_devis` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `devis_id`         VARCHAR(20)  NOT NULL,
    `nom`              VARCHAR(100) NOT NULL,
    `prenom`           VARCHAR(100) NOT NULL,
    `email`            VARCHAR(100) NOT NULL,
    `telephone`        VARCHAR(50)  NOT NULL,
    `entreprise`       VARCHAR(100) DEFAULT NULL,
    `services`         TEXT         DEFAULT NULL,
    `budget`           VARCHAR(20)  DEFAULT NULL,
    `delai`            VARCHAR(20)  DEFAULT NULL,
    `description`      TEXT         NOT NULL,
    `source`           VARCHAR(50)  DEFAULT NULL,
    `date_soumission`  DATETIME     NOT NULL,
    `statut`           VARCHAR(20)  NOT NULL DEFAULT 'nouveau',
    PRIMARY KEY (`id`),
    KEY `idx_statut`   (`statut`),
    KEY `idx_email`    (`email`),
    KEY `idx_date`     (`date_soumission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 3. blog_categories — Catégories du blog
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blog_categories` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `slug`       VARCHAR(120) NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 4. blog_posts — Articles du blog
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blog_posts` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED DEFAULT NULL,
    `title`       VARCHAR(255) NOT NULL,
    `slug`        VARCHAR(280) NOT NULL,
    `excerpt`     TEXT         DEFAULT NULL,
    `content`     LONGTEXT     DEFAULT NULL,
    `image`       VARCHAR(500) DEFAULT NULL,
    `author`      VARCHAR(100) NOT NULL DEFAULT 'Netcrafter',
    `status`      ENUM('draft','published') NOT NULL DEFAULT 'published',
    `views`       INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug`       (`slug`),
    KEY `idx_status`           (`status`),
    KEY `idx_category_id`      (`category_id`),
    KEY `idx_created`          (`created_at`),
    CONSTRAINT `fk_post_cat` FOREIGN KEY (`category_id`)
        REFERENCES `blog_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 5. blog_comments — Commentaires des articles
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blog_comments` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id`    INT UNSIGNED NOT NULL,
    `name`       VARCHAR(100) NOT NULL,
    `email`      VARCHAR(255) NOT NULL,
    `comment`    TEXT         NOT NULL,
    `status`     ENUM('pending','approved') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_post_id` (`post_id`),
    KEY `idx_status`  (`status`),
    CONSTRAINT `fk_comment_post` FOREIGN KEY (`post_id`)
        REFERENCES `blog_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 6. portfolio_projects — Projets du portfolio
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `portfolio_projects` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(255) NOT NULL,
    `slug`       VARCHAR(255) NOT NULL,
    `category`   ENUM('dev-web','webview','ia-chatbot','whatsapp','gestion','suivi','design','securite')
                 NOT NULL DEFAULT 'dev-web',
    `short_desc` VARCHAR(600) NOT NULL,
    `full_desc`  TEXT         DEFAULT NULL,
    `image`      VARCHAR(255) DEFAULT NULL,
    `gallery`    TEXT         DEFAULT NULL,
    `tags`       VARCHAR(500) DEFAULT NULL,
    `client`     VARCHAR(255) DEFAULT NULL,
    `year`       YEAR         DEFAULT NULL,
    `live_url`   VARCHAR(255) DEFAULT NULL,
    `featured`   TINYINT(1)   NOT NULL DEFAULT 0,
    `status`     ENUM('published','draft') NOT NULL DEFAULT 'published',
    `order_num`  INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug`   (`slug`),
    KEY `idx_category`     (`category`),
    KEY `idx_featured`     (`featured`),
    KEY `idx_status`       (`status`),
    KEY `idx_order`        (`order_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données de démonstration Portfolio (10 projets)
INSERT IGNORE INTO `portfolio_projects`
    (`title`, `slug`, `category`, `short_desc`, `full_desc`, `tags`, `client`, `year`, `featured`, `status`, `order_num`)
VALUES
('Système de Gestion Commerciale CRM', 'crm-gestion-commerciale', 'gestion',
 'CRM complet avec gestion clients, devis automatisés, suivi des ventes et tableaux de bord analytiques en temps réel.',
 'Développement d\'un système CRM sur mesure permettant la gestion complète du cycle commercial : prospection, qualification, devis, commandes, facturation et suivi post-vente.',
 'PHP,MySQL,Chart.js,Bootstrap,API REST', 'Client confidentiel', 2025, 1, 'published', 1),

('Chatbot WhatsApp Automatisé', 'chatbot-whatsapp-auto', 'whatsapp',
 'Bot WhatsApp intelligent qui gère les commandes, répond aux FAQs et notifie les clients automatiquement 24h/24.',
 'Intégration de l\'API WhatsApp Business pour automatiser la prise de commandes, les confirmations, les notifications de livraison et le support client. Réduction de 70% du temps de réponse.',
 'WhatsApp API,Node.js,Webhooks,MySQL', 'E-commerce Niger', 2025, 1, 'published', 2),

('Application de Suivi de Livraison', 'suivi-livraison-app', 'suivi',
 'Plateforme web et mobile de suivi en temps réel des colis avec notifications SMS/WhatsApp et tableau de bord livreur.',
 'Système complet de tracking : géolocalisation des livreurs, QR codes sur colis, historique des statuts, alertes automatiques aux clients et rapports de performance.',
 'PHP,Google Maps API,QR Code,WhatsApp,MySQL', 'Société Transport', 2025, 1, 'published', 3),

('Assistant IA pour Service Client', 'assistant-ia-service-client', 'ia-chatbot',
 'Intégration d\'un assistant IA contextuel capable de répondre aux questions clients, qualifier les leads et escalader vers un humain si nécessaire.',
 'Déploiement d\'un chatbot basé sur des modèles LLM, formé sur la documentation du client, intégré au site web et WhatsApp. Taux de résolution automatique de 65%.',
 'OpenAI API,PHP,JavaScript,Webhooks', 'Banque locale', 2024, 1, 'published', 4),

('WebView Application Mobile', 'webview-app-mobile', 'webview',
 'Transformation d\'un site web en application mobile native Android/iOS via WebView optimisée avec cache offline et notifications push.',
 'Création d\'une webview personnalisée avec splash screen, navigation native, mode hors-ligne, notifications push FCM et icône personnalisée. Publié sur Play Store.',
 'Android,WebView,FCM,JavaScript,PWA', 'Startup Tech', 2024, 0, 'published', 5),

('Système ERP Multi-modules', 'erp-multi-modules', 'gestion',
 'ERP complet couvrant la comptabilité, la gestion des stocks, RH, facturation et reporting pour une PME de 50 employés.',
 'Développement sur 6 mois d\'un ERP modulaire : stocks avec alertes, comptabilité, paie RH, CRM intégré et exports Excel/PDF.',
 'PHP,MySQL,Tailwind,Chart.js,FPDF', 'PME Niamey', 2025, 0, 'published', 6),

('Plateforme E-learning Interactive', 'elearning-interactif', 'dev-web',
 'Plateforme de formation en ligne avec gestion des cours vidéo, quiz, certificats automatiques et suivi de progression.',
 'LMS complet avec upload vidéo, lecture sécurisée, quiz interactifs, progression, certificats PDF et paiement en ligne.',
 'PHP,MySQL,Video.js,TCPDF,Mobile Money', 'Centre Formation', 2025, 0, 'published', 7),

('Système de Réservation en Ligne', 'reservation-en-ligne', 'dev-web',
 'Application web de réservation avec calendrier interactif, paiement en ligne, confirmations automatiques et gestion des disponibilités.',
 'Réservation temps réel : calendrier synchronisé, créneaux, paiement Mobile Money, confirmation WhatsApp et tableau de bord admin.',
 'PHP,FullCalendar,WhatsApp API,Mobile Money', 'Hôtel Niamey', 2024, 0, 'published', 8),

('Dashboard Analytics Temps Réel', 'dashboard-analytics', 'suivi',
 'Tableau de bord analytique avec métriques en direct, graphiques interactifs, alertes configurables et exports automatiques.',
 'Dashboard connecté à plusieurs sources de données : ventes, trafic, stocks, RH. Actualisation automatique toutes les 5 minutes.',
 'PHP,Chart.js,MySQL,AJAX,WhatsApp API', 'Groupe commercial', 2025, 0, 'published', 9),

('Intégration API WhatsApp Business', 'integration-whatsapp-business', 'whatsapp',
 'Mise en place complète de l\'API WhatsApp Business : messages templates, webhooks, catalogue produits et flux automatisés.',
 'Configuration et intégration de WhatsApp Business API : templates approuvés Meta, webhooks, catalogue produits, flux de commande automatisé.',
 'WhatsApp Business API,PHP,Webhooks,MySQL', 'Commerce de détail', 2024, 0, 'published', 10);


-- ----------------------------------------------------------------
-- 7. cahier_charges — Liens partagés et réponses clients
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cahier_charges` (
    `id`              INT          NOT NULL AUTO_INCREMENT,
    `token`           VARCHAR(64)  NOT NULL                 COMMENT 'Token unique du lien partagé (32 hex chars)',
    `label`           VARCHAR(255) DEFAULT NULL             COMMENT 'Étiquette interne admin',
    `client_name`     VARCHAR(255) DEFAULT NULL,
    `client_email`    VARCHAR(255) DEFAULT NULL,
    `client_phone`    VARCHAR(100) DEFAULT NULL,
    `client_company`  VARCHAR(255) DEFAULT NULL,
    `project_name`    VARCHAR(255) DEFAULT NULL,
    `project_type`    VARCHAR(100) DEFAULT NULL,
    `description`     TEXT         DEFAULT NULL,
    `objectives`      TEXT         DEFAULT NULL,
    `target_audience` TEXT         DEFAULT NULL,
    `features`        TEXT         DEFAULT NULL             COMMENT 'JSON array',
    `custom_features` TEXT         DEFAULT NULL,
    `design_style`    VARCHAR(100) DEFAULT NULL,
    `color_prefs`     TEXT         DEFAULT NULL,
    `has_brand`       TINYINT(1)   NOT NULL DEFAULT 0,
    `brand_details`   TEXT         DEFAULT NULL,
    `ref_urls`        TEXT         DEFAULT NULL,
    `budget`          VARCHAR(100) DEFAULT NULL,
    `deadline`        VARCHAR(100) DEFAULT NULL,
    `cms_pref`        VARCHAR(100) DEFAULT NULL,
    `hosting_pref`    VARCHAR(100) DEFAULT NULL,
    `notes`           TEXT         DEFAULT NULL,
    `status`          ENUM('pending','in_review','validated','archived') NOT NULL DEFAULT 'pending',
    `admin_notes`     TEXT         DEFAULT NULL,
    `submitted_at`    DATETIME     DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE  KEY `uq_token`       (`token`),
    KEY           `idx_status`   (`status`),
    KEY           `idx_email`    (`client_email`),
    KEY           `idx_submitted`(`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 8. cahier_files — Fichiers uploadés par les clients
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cahier_files` (
    `id`            INT          NOT NULL AUTO_INCREMENT,
    `cahier_id`     INT          NOT NULL,
    `original_name` VARCHAR(500) NOT NULL,
    `stored_name`   VARCHAR(500) NOT NULL,
    `file_type`     VARCHAR(100) DEFAULT NULL,
    `file_size`     INT          NOT NULL DEFAULT 0,
    `uploaded_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cahier_id` (`cahier_id`),
    CONSTRAINT `fk_cf_cahier` FOREIGN KEY (`cahier_id`)
        REFERENCES `cahier_charges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- ██████████  BASE 2 : u264396140_shop  ██████████████████
-- ██████████  Boutique — produits, commandes, clients, admin  █████
-- ================================================================

CREATE DATABASE IF NOT EXISTS `u264396140_shop`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `u264396140_shop`;

-- ----------------------------------------------------------------
-- 1. users — Comptes clients (boutique + accès admin)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `username`    VARCHAR(50)      NOT NULL,
    `email`       VARCHAR(100)     NOT NULL,
    `password`    VARCHAR(255)     NOT NULL,
    `full_name`   VARCHAR(100)     DEFAULT NULL,
    `phone`       VARCHAR(20)      DEFAULT NULL,
    `mac_address` VARCHAR(17)      DEFAULT NULL,
    `is_admin`    TINYINT(1)       NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login`  TIMESTAMP        NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email`    (`email`),
    UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 2. addresses — Adresses de livraison/facturation
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `addresses` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED DEFAULT NULL,
    `address_type`    ENUM('shipping','billing') NOT NULL DEFAULT 'shipping',
    `street_address`  VARCHAR(255) NOT NULL,
    `city`            VARCHAR(100) NOT NULL,
    `state`           VARCHAR(100) DEFAULT NULL,
    `postal_code`     VARCHAR(20)  NOT NULL,
    `country`         VARCHAR(100) NOT NULL DEFAULT 'Niger',
    `is_default`      TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    CONSTRAINT `fk_addr_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 3. sessions — Sessions utilisateur persistantes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sessions` (
    `id`          VARCHAR(255) NOT NULL,
    `user_id`     INT UNSIGNED DEFAULT NULL,
    `data`        TEXT,
    `ip_address`  VARCHAR(45)  DEFAULT NULL,
    `mac_address` VARCHAR(17)  DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    CONSTRAINT `fk_sess_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 4. categories — Catégories de produits
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `slug`        VARCHAR(120) NOT NULL,
    `description` TEXT         DEFAULT NULL,
    `image_url`   VARCHAR(500) DEFAULT NULL,
    `parent_id`   INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug` (`slug`),
    KEY `idx_parent` (`parent_id`),
    CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 5. suppliers — Fournisseurs de produits
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                  VARCHAR(100) NOT NULL,
    `contact_person`        VARCHAR(100) DEFAULT NULL,
    `email`                 VARCHAR(100) DEFAULT NULL,
    `phone`                 VARCHAR(20)  DEFAULT NULL,
    `website`               VARCHAR(255) DEFAULT NULL,
    `country`               VARCHAR(100) NOT NULL DEFAULT 'China',
    `shipping_method`       VARCHAR(100) DEFAULT NULL,
    `average_delivery_time` INT UNSIGNED DEFAULT NULL,
    `notes`                 TEXT         DEFAULT NULL,
    `status`                ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 6. products — Catalogue de produits
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`              VARCHAR(255)     NOT NULL,
    `sku`               VARCHAR(50)      DEFAULT NULL,
    `description`       TEXT             DEFAULT NULL,
    `short_description` TEXT             DEFAULT NULL,
    `price`             DECIMAL(10,2)    NOT NULL,
    `sale_price`        DECIMAL(10,2)    DEFAULT NULL,
    `cost_price`        DECIMAL(10,2)    DEFAULT NULL,
    `weight`            DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
    `dimensions`        VARCHAR(50)      DEFAULT NULL,
    `stock`             INT              NOT NULL DEFAULT 0,
    `category_id`       INT UNSIGNED     DEFAULT NULL,
    `specifications`    JSON             DEFAULT NULL,
    `package_contents`  JSON             DEFAULT NULL,
    `supplier_id`       INT UNSIGNED     DEFAULT NULL,
    `supplier_url`      VARCHAR(500)     DEFAULT NULL,
    `shipping_time`     VARCHAR(100)     DEFAULT NULL,
    `views`             INT UNSIGNED     NOT NULL DEFAULT 0,
    `status`            ENUM('active','inactive','out_of_stock') NOT NULL DEFAULT 'active',
    `created_at`        TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sku`    (`sku`),
    KEY `idx_category`     (`category_id`),
    KEY `idx_status`       (`status`),
    KEY `idx_price`        (`price`),
    KEY `idx_stock`        (`stock`),
    CONSTRAINT `fk_prod_cat`  FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_prod_supp` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 7. product_images — Images des produits
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_images` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`    INT UNSIGNED NOT NULL,
    `image_url`     VARCHAR(500) NOT NULL,
    `is_primary`    TINYINT(1)   NOT NULL DEFAULT 0,
    `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_product`  (`product_id`),
    KEY `idx_primary`  (`is_primary`),
    CONSTRAINT `fk_img_prod` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 8. product_reviews — Avis clients sur les produits
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_reviews` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED DEFAULT NULL,
    `name`       VARCHAR(100) NOT NULL,
    `title`      VARCHAR(200) DEFAULT NULL,
    `rating`     TINYINT      NOT NULL DEFAULT 5,
    `review`     TEXT         NOT NULL,
    `status`     ENUM('pending','approved') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product` (`product_id`),
    KEY `idx_status`  (`status`),
    CONSTRAINT `fk_rev_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rev_user` FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 9. stock_alerts — Alertes retour en stock
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_alerts` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `email`      VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_alert` (`product_id`, `email`),
    CONSTRAINT `fk_alert_prod` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 10. promo_codes — Codes promotionnels
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `promo_codes` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `code`             VARCHAR(50)   NOT NULL,
    `discount_percent` DECIMAL(5,2)  NOT NULL DEFAULT 0,
    `discount_fixed`   DECIMAL(10,2) NOT NULL DEFAULT 0,
    `min_order`        DECIMAL(10,2) NOT NULL DEFAULT 0,
    `max_uses`         INT           NOT NULL DEFAULT 0,
    `uses_count`       INT           NOT NULL DEFAULT 0,
    `expires_at`       DATETIME      DEFAULT NULL,
    `active`           TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code`  (`code`),
    KEY `idx_active`      (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 11. orders — Commandes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`             INT UNSIGNED  DEFAULT NULL,
    `order_number`        VARCHAR(50)   NOT NULL,
    `total_amount`        DECIMAL(10,2) NOT NULL,
    `shipping_cost`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `shipping_address_id` INT UNSIGNED  DEFAULT NULL,
    `billing_address_id`  INT UNSIGNED  DEFAULT NULL,
    `payment_method`      VARCHAR(50)   DEFAULT NULL,
    `payment_status`      ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    `order_status`        ENUM('pending','processing','shipped','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
    `notes`               TEXT          DEFAULT NULL,
    `mac_address`         VARCHAR(17)   DEFAULT NULL,
    `created_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_order_number`  (`order_number`),
    KEY `idx_user_id`             (`user_id`),
    KEY `idx_order_status`        (`order_status`),
    KEY `idx_payment_status`      (`payment_status`),
    CONSTRAINT `fk_ord_user`  FOREIGN KEY (`user_id`)             REFERENCES `users`     (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ord_ship`  FOREIGN KEY (`shipping_address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ord_bill`  FOREIGN KEY (`billing_address_id`)  REFERENCES `addresses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 12. order_items — Lignes de commande
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`   INT UNSIGNED  NOT NULL,
    `product_id` INT UNSIGNED  NOT NULL,
    `quantity`   INT           NOT NULL DEFAULT 1,
    `price`      DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_order_id`   (`order_id`),
    KEY `idx_product_id` (`product_id`),
    CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`)   REFERENCES `orders`   (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_oi_prod`  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 13. order_tracking — Historique de suivi des commandes
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_tracking` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`        INT UNSIGNED NOT NULL,
    `status`          ENUM('processing','shipped','in_transit','delivered','returned','cancelled') NOT NULL,
    `tracking_number` VARCHAR(100) DEFAULT NULL,
    `carrier`         VARCHAR(100) DEFAULT NULL,
    `location`        VARCHAR(255) DEFAULT NULL,
    `notes`           TEXT         DEFAULT NULL,
    `updated_by`      VARCHAR(100) DEFAULT NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    CONSTRAINT `fk_track_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ================================================================
-- ██████████  BASE 3 : u264396140_formation  ██████████████
-- ██████████    Plateforme de formations en ligne    ██████████████
-- ================================================================

CREATE DATABASE IF NOT EXISTS `u264396140_formation`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `u264396140_formation`;

-- ----------------------------------------------------------------
-- 1. users — Comptes apprenants et formateurs
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
    UNIQUE KEY `uq_email` (`email`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 2. formation_categories — Catégories de formations
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
-- 3. formations — Formations disponibles
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
    UNIQUE KEY `uq_slug`       (`slug`),
    KEY `idx_category_id`      (`category_id`),
    KEY `idx_status`           (`status`),
    KEY `idx_featured`         (`is_featured`),
    CONSTRAINT `fk_form_cat` FOREIGN KEY (`category_id`)
        REFERENCES `formation_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 4. formation_modules — Modules d'une formation
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `formation_modules` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `formation_id` INT UNSIGNED NOT NULL,
    `title`        VARCHAR(255) NOT NULL,
    `order_number` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_formation_id` (`formation_id`),
    KEY `idx_order`        (`order_number`),
    CONSTRAINT `fk_mod_form` FOREIGN KEY (`formation_id`)
        REFERENCES `formations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 5. formation_videos — Vidéos d'un module
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
    PRIMARY KEY (`id`),
    KEY `idx_module_id` (`module_id`),
    KEY `idx_order`     (`order_number`),
    CONSTRAINT `fk_vid_mod` FOREIGN KEY (`module_id`)
        REFERENCES `formation_modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 6. formation_subscriptions — Abonnements aux formations
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
    PRIMARY KEY (`id`),
    KEY `idx_user_id`      (`user_id`),
    KEY `idx_formation_id` (`formation_id`),
    KEY `idx_status`       (`status`),
    KEY `idx_end_date`     (`end_date`),
    CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`)      REFERENCES `users`      (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sub_form` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 7. formation_favorites — Formations favorites
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `formation_favorites` (
    `user_id`      INT UNSIGNED NOT NULL,
    `formation_id` INT UNSIGNED NOT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `formation_id`),
    CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`)      REFERENCES `users`      (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fav_form` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 8. video_progress — Progression de visionnage
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
    UNIQUE KEY `uq_user_video` (`user_id`, `video_id`),
    KEY `idx_user_id`  (`user_id`),
    KEY `idx_video_id` (`video_id`),
    CONSTRAINT `fk_vp_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`            (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vp_video` FOREIGN KEY (`video_id`) REFERENCES `formation_videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 9. video_notes — Notes prises pendant les vidéos
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `video_notes` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `video_id`   INT UNSIGNED NOT NULL,
    `content`    TEXT         NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_video` (`user_id`, `video_id`),
    CONSTRAINT `fk_note_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`            (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_note_video` FOREIGN KEY (`video_id`) REFERENCES `formation_videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 10. formation_quizzes — Quiz d'une formation
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
    PRIMARY KEY (`id`),
    KEY `idx_formation_id` (`formation_id`),
    CONSTRAINT `fk_quiz_form` FOREIGN KEY (`formation_id`)
        REFERENCES `formations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 11. quiz_questions — Questions d'un quiz
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quiz_questions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quiz_id`       INT UNSIGNED NOT NULL,
    `question_text` TEXT         NOT NULL,
    `question_type` ENUM('multiple_choice','true_false','short_answer') NOT NULL DEFAULT 'multiple_choice',
    `points`        INT          NOT NULL DEFAULT 1,
    `order_number`  INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quiz_id` (`quiz_id`),
    CONSTRAINT `fk_qq_quiz` FOREIGN KEY (`quiz_id`)
        REFERENCES `formation_quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 12. quiz_answers — Réponses possibles à une question
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quiz_answers` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id`  INT UNSIGNED NOT NULL,
    `answer_text`  TEXT         NOT NULL,
    `is_correct`   TINYINT(1)   NOT NULL DEFAULT 0,
    `order_number` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_question_id` (`question_id`),
    CONSTRAINT `fk_qa_question` FOREIGN KEY (`question_id`)
        REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 13. quiz_attempts — Tentatives de quiz par apprenant
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quiz_attempts` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `quiz_id`      INT UNSIGNED  NOT NULL,
    `user_id`      INT UNSIGNED  NOT NULL,
    `score`        DECIMAL(5,2)  NOT NULL DEFAULT 0,
    `passed`       TINYINT(1)    NOT NULL DEFAULT 0,
    `time_taken`   INT UNSIGNED  DEFAULT NULL,
    `answers_data` JSON          DEFAULT NULL,
    `completed_at` TIMESTAMP     NULL DEFAULT NULL,
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quiz_id` (`quiz_id`),
    KEY `idx_user_id` (`user_id`),
    CONSTRAINT `fk_att_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `formation_quizzes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_att_user` FOREIGN KEY (`user_id`) REFERENCES `users`             (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 14. forum_topics — Sujets du forum de la communauté
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
    PRIMARY KEY (`id`),
    KEY `idx_user_id`      (`user_id`),
    KEY `idx_formation_id` (`formation_id`),
    KEY `idx_pinned`       (`is_pinned`),
    CONSTRAINT `fk_topic_user` FOREIGN KEY (`user_id`)      REFERENCES `users`      (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_topic_form` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 15. forum_replies — Réponses aux sujets du forum
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `forum_replies` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `topic_id`    INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED NOT NULL,
    `content`     TEXT         NOT NULL,
    `is_solution` TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_topic_id` (`topic_id`),
    KEY `idx_user_id`  (`user_id`),
    CONSTRAINT `fk_reply_topic` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reply_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`        (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 16. certificates — Certificats de fin de formation
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `certificates` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED NOT NULL,
    `formation_id`   INT UNSIGNED NOT NULL,
    `certificate_no` VARCHAR(50)  NOT NULL,
    `issued_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_formation` (`user_id`, `formation_id`),
    KEY `idx_certificate_no`       (`certificate_no`),
    CONSTRAINT `fk_cert_user` FOREIGN KEY (`user_id`)      REFERENCES `users`      (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cert_form` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 17. admin_logs — Journal d'activité des administrateurs
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`    INT UNSIGNED NOT NULL,
    `action`      VARCHAR(100) NOT NULL,
    `description` TEXT         DEFAULT NULL,
    `ip_address`  VARCHAR(45)  DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_id`   (`admin_id`),
    KEY `idx_action`     (`action`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 18. user_settings — Préférences utilisateur
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_settings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED NOT NULL,
    `setting_key`   VARCHAR(100) NOT NULL,
    `setting_value` TEXT         DEFAULT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_setting` (`user_id`, `setting_key`),
    CONSTRAINT `fk_uset_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 19. admin_settings — Paramètres globaux de la plateforme
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
-- FIN DE LA MIGRATION
-- ================================================================
--
-- Résumé :
--   u264396140_netcrafternige  — 8 tables  (vitrine : devis, blog, portfolio, cahier des charges)
--   u264396140_shop            — 13 tables (boutique : produits, commandes, clients)
--   u264396140_formation       — 19 tables (formations, quiz, forum, certificats)
--   Total                      — 40 tables
--
-- Données de démo insérées :
--   • portfolio_projects       — 10 projets
--   • formation_categories     — 6 catégories
--   • settings (vitrine)       — 1 ligne de config
--   • admin_settings           — 1 ligne de config
--
-- IMPORTANT — Pour utiliser en local (WAMP) :
--   Remplacer les 3 noms de bases par :
--     u264396140_netcrafternige → netcrafter
--     u264396140_shop           → netcrafter  (même base en local)
--     u264396140_formation      → netcrafter_formation
--
-- ================================================================
