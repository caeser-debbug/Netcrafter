-- ================================================================
-- NETCRAFTER — MIGRATION BOUTIQUE
-- Base : u264396140_shop
-- ================================================================
-- Sélectionner u264396140_shop dans phpMyAdmin avant import.
-- Tables : users, addresses, sessions, categories, suppliers,
--          products, product_images, product_reviews, stock_alerts,
--          promo_codes, orders, order_items, order_tracking
-- ================================================================

-- ----------------------------------------------------------------
-- 1. users
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
-- 2. addresses
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
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 3. sessions
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
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 4. categories
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `slug`        VARCHAR(120) NOT NULL,
    `description` TEXT         DEFAULT NULL,
    `image_url`   VARCHAR(500) DEFAULT NULL,
    `parent_id`   INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug`  (`slug`),
    KEY `idx_parent`      (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 5. suppliers
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
-- 6. products
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
    `specifications`    TEXT             DEFAULT NULL,
    `package_contents`  TEXT             DEFAULT NULL,
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
    KEY `idx_stock`        (`stock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 7. product_images
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_images` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`    INT UNSIGNED NOT NULL,
    `image_url`     VARCHAR(500) NOT NULL,
    `is_primary`    TINYINT(1)   NOT NULL DEFAULT 0,
    `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_product` (`product_id`),
    KEY `idx_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 8. product_reviews
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
    KEY `idx_user`    (`user_id`),
    KEY `idx_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 9. stock_alerts
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_alerts` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `email`      VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_alert`    (`product_id`, `email`),
    KEY `idx_product_id`     (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 10. promo_codes
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
-- 11. orders
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
    UNIQUE KEY `uq_order_number` (`order_number`),
    KEY `idx_user_id`            (`user_id`),
    KEY `idx_order_status`       (`order_status`),
    KEY `idx_payment_status`     (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 12. order_items
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`   INT UNSIGNED  NOT NULL,
    `product_id` INT UNSIGNED  NOT NULL,
    `quantity`   INT           NOT NULL DEFAULT 1,
    `price`      DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_order_id`   (`order_id`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- 13. order_tracking
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
    KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- FIN — 13 tables dans u264396140_shop
-- ================================================================
