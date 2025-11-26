-- Add inactive_count for PVZ availability tracking
ALTER TABLE `#__radicalmart_apiship_points`
ADD COLUMN `inactive_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Count of "no tariff" reports from different users' AFTER `meta`;

-- Index for filtering inactive points
ALTER TABLE `#__radicalmart_apiship_points` ADD INDEX `idx_inactive` (`inactive_count`);
