-- com_radicalmart_telegram install SQL

CREATE TABLE IF NOT EXISTS `#__radicalmart_apiship_points` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` VARCHAR(32) NOT NULL,
  `ext_id` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) NULL,
  `address` VARCHAR(512) NULL,
  `lat` DOUBLE NOT NULL,
  `lon` DOUBLE NOT NULL,
  `operation` VARCHAR(16) NOT NULL DEFAULT 'giveout',
  `pvz_type` VARCHAR(32) NOT NULL DEFAULT '',
  `point` POINT NOT NULL /*!80003 SRID 4326 */,
  `meta` LONGTEXT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_provider_ext` (`provider`, `ext_id`),
  KEY `idx_provider_op` (`provider`, `operation`),
  SPATIAL KEY `sp_point` (`point`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `#__radicalmart_apiship_meta` (
  `provider` VARCHAR(32) NOT NULL,
  `last_fetch` DATETIME NULL,
  `last_total` INT NULL DEFAULT 0,
  PRIMARY KEY (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Telegram bot users mapping
CREATE TABLE IF NOT EXISTS `#__radicalmart_telegram_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chat_id` BIGINT NOT NULL,
  `tg_user_id` BIGINT NULL,
  `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `username` VARCHAR(255) NULL,
  `phone` VARCHAR(32) NULL,
  `locale` VARCHAR(8) NOT NULL DEFAULT 'ru',
  `consent_personal_data` TINYINT(1) NOT NULL DEFAULT 0,
  `consent_personal_data_at` DATETIME NULL,
  `consent_marketing` TINYINT(1) NOT NULL DEFAULT 0,
  `consent_marketing_at` DATETIME NULL,
  `consent_terms` TINYINT(1) NOT NULL DEFAULT 0,
  `consent_terms_at` DATETIME NULL,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_chat` (`chat_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_tg_user_id` (`tg_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Telegram bot sessions (idempotency + state)
CREATE TABLE IF NOT EXISTS `#__radicalmart_telegram_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `chat_id` BIGINT NOT NULL,
  `state` VARCHAR(64) NOT NULL DEFAULT '',
  `payload` MEDIUMTEXT NULL,
  `cart_snapshot` MEDIUMTEXT NULL,
  `last_update_id` BIGINT NULL DEFAULT 0,
  `expires_at` DATETIME NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_chat` (`chat_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Telegram one-time links (bind chat to user)
CREATE TABLE IF NOT EXISTS `#__radicalmart_telegram_links` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `chat_id` BIGINT NULL,
  `user_id` INT UNSIGNED NULL,
  `created` DATETIME NOT NULL,
  `expires_at` DATETIME NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `used_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_code` (`code`),
  KEY `idx_chat` (`chat_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nonces for idempotency of mutations
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

-- Rate limit storage (per minute windows)
CREATE TABLE IF NOT EXISTS `#__radicalmart_telegram_ratelimits` (
  `scope` VARCHAR(64) NOT NULL,
  `rkey` VARCHAR(64) NOT NULL,
  `window_start` DATETIME NOT NULL,
  `count` INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY `uniq_scope_key_window` (`scope`, `rkey`, `window_start`),
  KEY `idx_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
