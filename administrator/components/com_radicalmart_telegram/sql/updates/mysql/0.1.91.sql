-- 0.1.91: Restock subscriptions (notify when product back in stock)
CREATE TABLE IF NOT EXISTS `#__radicalmart_telegram_restock_subscriptions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `chat_id` BIGINT NOT NULL COMMENT 'Telegram chat_id',
    `product_id` INT UNSIGNED NOT NULL COMMENT 'RadicalMart product ID',
    `variant_id` INT UNSIGNED NULL COMMENT 'Product variant ID (if applicable)',
    `notified` TINYINT (1) NOT NULL DEFAULT 0 COMMENT '1=already notified',
    `notified_at` DATETIME NULL,
    `created` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_chat_product` (`chat_id`, `product_id`, `variant_id`),
    KEY `idx_product` (`product_id`),
    KEY `idx_notified` (`notified`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
