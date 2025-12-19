<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/Db.php';

$config = require __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = Db::getConnection($config);
} catch (Throwable $e) {
    http_response_code(500);
    echo "Verbindung fehlgeschlagen: " . $e->getMessage();
    exit(1);
}

$definitions = [
    'users' => <<<SQL
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','editor','viewer') NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
    'categories' => <<<SQL
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `color` VARCHAR(16) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
    'events' => <<<SQL
CREATE TABLE IF NOT EXISTS `events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `location_text` VARCHAR(255) NULL,
  `location_url` VARCHAR(255) NULL,
  `start_at` DATETIME NOT NULL,
  `end_at` DATETIME NULL DEFAULT NULL,
  `all_day` TINYINT(1) NOT NULL DEFAULT 0,
  `visibility` ENUM('internal') NOT NULL DEFAULT 'internal',
  `source` ENUM('manual','openholidays') NOT NULL DEFAULT 'manual',
  `external_id` VARCHAR(120) NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `updated_by` INT UNSIGNED NULL DEFAULT NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_events_source_external` (`source`, `external_id`),
  KEY `idx_events_category` (`category_id`),
  KEY `idx_events_start` (`start_at`),
  KEY `idx_events_created_by` (`created_by`),
  KEY `idx_events_updated_by` (`updated_by`),
  CONSTRAINT `fk_events_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_events_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
    'event_series' => <<<SQL
CREATE TABLE IF NOT EXISTS `event_series` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_event_id` INT UNSIGNED NOT NULL,
  `rrule` VARCHAR(255) NOT NULL,
  `series_timezone` VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin',
  `skip_if_holiday` TINYINT(1) NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `updated_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_series_template_event` (`template_event_id`),
  KEY `idx_series_created_by` (`created_by`),
  KEY `idx_series_updated_by` (`updated_by`),
  CONSTRAINT `fk_series_template_event` FOREIGN KEY (`template_event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_series_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_series_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
    'event_overrides' => <<<SQL
CREATE TABLE IF NOT EXISTS `event_overrides` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `series_id` INT UNSIGNED NOT NULL,
  `occurrence_start` DATETIME NOT NULL,
  `override_type` ENUM('modified','cancelled') NOT NULL,
  `override_event_id` INT UNSIGNED NULL DEFAULT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_overrides_series_occurrence` (`series_id`, `occurrence_start`),
  KEY `idx_overrides_override_event` (`override_event_id`),
  KEY `idx_overrides_created_by` (`created_by`),
  CONSTRAINT `fk_overrides_series` FOREIGN KEY (`series_id`) REFERENCES `event_series` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_overrides_override_event` FOREIGN KEY (`override_event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_overrides_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
    'audit_log' => <<<SQL
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` ENUM('event','series','category','user','sync','import') NOT NULL,
  `entity_id` VARCHAR(64) NOT NULL,
  `action` ENUM('create','update','delete','login','sync','import') NOT NULL,
  `user_id` INT UNSIGNED NULL DEFAULT NULL,
  `before_json` JSON NULL,
  `after_json` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_user` (`user_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
];

try {
    foreach ($definitions as $name => $sql) {
        $pdo->exec($sql);
        echo "Tabelle sichergestellt: {$name}\n";
    }

    echo "Migration abgeschlossen.";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Migration fehlgeschlagen bei {$name}: " . $e->getMessage();
    exit(1);
}
