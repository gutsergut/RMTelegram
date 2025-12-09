-- Abandoned cart reminders table
CREATE TABLE IF NOT EXISTS `#__radicalmart_telegram_cart_reminders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chat_id` BIGINT NOT NULL COMMENT 'Telegram chat_id',
  `cart_hash` VARCHAR(64) NOT NULL COMMENT 'Hash of cart contents for change detection',
  `cart_total` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Cart total for reference',
  `cart_items_count` INT NOT NULL DEFAULT 0 COMMENT 'Number of items in cart',
  `last_activity` DATETIME NOT NULL COMMENT 'Last cart activity timestamp',
  `reminder_count` INT NOT NULL DEFAULT 0 COMMENT 'Number of reminders sent',
  `last_reminder_at` DATETIME NULL COMMENT 'When last reminder was sent',
  `next_reminder_at` DATETIME NULL COMMENT 'Scheduled time for next reminder',
  `completed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=converted to order, 2=dismissed, 3=expired',
  `completed_at` DATETIME NULL,
  `order_id` INT NULL COMMENT 'Order ID if converted',
  `created` DATETIME NOT NULL,











  ADD COLUMN `consent_cart_reminders_at` DATETIME NULL AFTER `consent_cart_reminders`;  ADD COLUMN `consent_cart_reminders` TINYINT(1) NOT NULL DEFAULT 0 AFTER `consent_marketing_at`,ALTER TABLE `#__radicalmart_telegram_users`-- Add cart reminder consent field to users table) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;  KEY `idx_last_activity` (`last_activity`)  KEY `idx_next_reminder` (`next_reminder_at`, `completed`),  UNIQUE KEY `uniq_chat` (`chat_id`),  PRIMARY KEY (`id`),
