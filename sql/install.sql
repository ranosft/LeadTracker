CREATE TABLE IF NOT EXISTS `PREFIX_leads` (
    `id_lead` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `mobile` VARCHAR(15) NOT NULL,
    `mobile_normalized` VARCHAR(10) NOT NULL,
    `source` VARCHAR(50) DEFAULT 'unknown' COMMENT 'url_param|customer|cookie|manual',
    `session_id` VARCHAR(100) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `gdpr_consent` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_lead`),
    UNIQUE KEY `mobile_normalized` (`mobile_normalized`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_lead_activity` (
    `id_activity` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_lead` INT(11) UNSIGNED NOT NULL,
    `event_type` VARCHAR(50) NOT NULL COMMENT 'pageview|product_view|add_to_cart|checkout|order',
    `page_url` VARCHAR(512) DEFAULT NULL,
    `controller` VARCHAR(100) DEFAULT NULL,
    `product_id` INT(11) UNSIGNED DEFAULT NULL,
    `product_name` VARCHAR(255) DEFAULT NULL,
    `cart_total` DECIMAL(10,2) DEFAULT NULL,
    `session_id` VARCHAR(100) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `extra_data` TEXT DEFAULT NULL COMMENT 'JSON extra payload',
    `telegram_sent` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_activity`),
    KEY `id_lead` (`id_lead`),
    KEY `event_type` (`event_type`),
    KEY `created_at` (`created_at`),
    CONSTRAINT `fk_lead_activity_lead`
        FOREIGN KEY (`id_lead`) REFERENCES `PREFIX_leads` (`id_lead`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
