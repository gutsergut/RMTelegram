-- Update for consent fields (v5.0.1)
-- Adds proper consent tracking for GDPR/ФЗ-152 compliance

-- Rename old consent fields if they exist
ALTER TABLE `#__radicalmart_telegram_users`
  CHANGE COLUMN `consent_notifications` `consent_marketing` TINYINT(1) NOT NULL DEFAULT 0,
  CHANGE COLUMN `consent_personal` `consent_personal_data` TINYINT(1) NOT NULL DEFAULT 0;

-- Add timestamp fields for consent tracking
ALTER TABLE `#__radicalmart_telegram_users`
  ADD COLUMN `consent_personal_data_at` DATETIME NULL AFTER `consent_personal_data`,
  ADD COLUMN `consent_marketing_at` DATETIME NULL AFTER `consent_marketing`,
  ADD COLUMN `consent_terms` TINYINT(1) NOT NULL DEFAULT 0 AFTER `consent_marketing_at`,
  ADD COLUMN `consent_terms_at` DATETIME NULL AFTER `consent_terms`;

-- For existing users with consent=1, set timestamp to created date
UPDATE `#__radicalmart_telegram_users`
SET `consent_personal_data_at` = `created`
WHERE `consent_personal_data` = 1 AND `consent_personal_data_at` IS NULL;

UPDATE `#__radicalmart_telegram_users`
SET `consent_marketing_at` = `created`
WHERE `consent_marketing` = 1 AND `consent_marketing_at` IS NULL;
