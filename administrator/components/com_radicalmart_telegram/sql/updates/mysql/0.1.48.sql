-- Add pvz_type column to radicalmart_apiship_points
ALTER TABLE `#__radicalmart_apiship_points`
  ADD COLUMN `pvz_type` VARCHAR(32) NOT NULL DEFAULT '' AFTER `operation`;
