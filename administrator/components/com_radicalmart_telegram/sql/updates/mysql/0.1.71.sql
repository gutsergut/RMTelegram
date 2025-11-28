-- Add referral_code field to store referral code from Telegram start parameter
-- Using IF NOT EXISTS for safety (in case field already exists)
ALTER TABLE `#__radicalmart_telegram_users`
ADD COLUMN IF NOT EXISTS `referral_code` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Referral code from /start ref_XXX';

-- Index for referral code lookup (ignore if exists)
CREATE INDEX IF NOT EXISTS `idx_referral_code` ON `#__radicalmart_telegram_users` (`referral_code`);
