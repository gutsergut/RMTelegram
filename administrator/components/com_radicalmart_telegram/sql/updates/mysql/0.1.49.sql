ALTER TABLE `#__radicalmart_apiship_points`
  ADD COLUMN IF NOT EXISTS `pvz_type` VARCHAR(32) NOT NULL DEFAULT '' AFTER `operation`;
