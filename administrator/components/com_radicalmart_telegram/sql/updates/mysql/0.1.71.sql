-- Add referral_code field to store referral code from Telegram start parameter
ALTER TABLE `#__radicalmart_telegram_users`
ADD COLUMN `referral_code` VARCHAR(64) NULL DEFAULT NULL COMMENT 'Referral code from /start ref_CODE' AFTER `consent_terms_at`;

-- Index for referral code lookup
ALTER TABLE `#__radicalmart_telegram_users` ADD INDEX `idx_referral_code` (`referral_code`);
