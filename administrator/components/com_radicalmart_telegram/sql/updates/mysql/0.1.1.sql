-- Schema update 0.1.1
ALTER TABLE `#__radicalmart_telegram_users`
  ADD COLUMN `tg_user_id` BIGINT NULL AFTER `chat_id`,
  ADD KEY `idx_tg_user_id` (`tg_user_id`);

CREATE TABLE IF NOT EXISTS `#__radicalmart_telegram_nonces` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chat_id` BIGINT NOT NULL,
  `scope` VARCHAR(32) NOT NULL,
  `nonce` VARCHAR(64) NOT NULL,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_chat_scope_nonce` (`chat_id`, `scope`, `nonce`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `#__radicalmart_telegram_ratelimits` (
  `scope` VARCHAR(64) NOT NULL,
  `rkey` VARCHAR(64) NOT NULL,
  `window_start` DATETIME NOT NULL,
  `count` INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY `uniq_scope_key_window` (`scope`, `rkey`, `window_start`),
  KEY `idx_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

