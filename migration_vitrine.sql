-- ================================================================
-- NETCRAFTER — MIGRATION VITRINE
-- Base : u264396140_netcrafternige
-- ================================================================
-- Sélectionner u264396140_netcrafternige dans phpMyAdmin avant import.
-- Tables : settings, demandes_devis, blog_categories,
--          blog_posts, blog_comments, portfolio_projects
-- ================================================================

-- ----------------------------------------------------------------
-- 1. settings
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
-- 2. demandes_devis
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
    KEY `idx_statut`  (`statut`),
    KEY `idx_email`   (`email`),
    KEY `idx_date`    (`date_soumission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 3. blog_categories
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
-- 4. blog_posts
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
    UNIQUE KEY `uq_slug`      (`slug`),
    KEY `idx_status`          (`status`),
    KEY `idx_category_id`     (`category_id`),
    KEY `idx_created`         (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 5. blog_comments
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
    KEY `idx_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 6. portfolio_projects
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
    UNIQUE KEY `uq_slug`  (`slug`),
    KEY `idx_category`    (`category`),
    KEY `idx_featured`    (`featured`),
    KEY `idx_status`      (`status`),
    KEY `idx_order`       (`order_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- ================================================================
-- FIN — 6 tables dans u264396140_netcrafternige
-- ================================================================
