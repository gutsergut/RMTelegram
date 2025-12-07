-- Email verification columns for telegram_users
-- Version 0.1.89
ALTER TABLE `#__radicalmart_telegram_users`
ADD COLUMN IF NOT EXISTS `email` VARCHAR(255) DEFAULT NULL AFTER `phone`,
ADD COLUMN IF NOT EXISTS `email_verified` TINYINT (1) NOT NULL DEFAULT 0 AFTER `email`,
ADD COLUMN IF NOT EXISTS `email_verification_code` VARCHAR(10) DEFAULT NULL AFTER `email_verified`,
ADD COLUMN IF NOT EXISTS `email_verification_expires` DATETIME DEFAULT NULL AFTER `email_verification_code`,
ADD COLUMN IF NOT EXISTS `email_verification_attempts` INT NOT NULL DEFAULT 0 AFTER `email_verification_expires`,
ADD COLUMN IF NOT EXISTS `email_code_sent_at` DATETIME DEFAULT NULL AFTER `email_verification_attempts`,
ADD COLUMN IF NOT EXISTS `acymailing_subscribed` TINYINT (1) NOT NULL DEFAULT 0 AFTER `email_code_sent_at`;

-- Index for email lookups (check uniqueness)
CREATE INDEX IF NOT EXISTS `idx_email` ON `#__radicalmart_telegram_users` (`email`);
