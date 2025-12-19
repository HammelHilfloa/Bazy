-- Initiale Daten für Kategorien und Benutzer
SET NAMES utf8mb4;

INSERT INTO `categories` (`name`, `color`, `sort_order`, `is_active`, `created_at`) VALUES
  ('Mitgliederversammlung', '#1E88E5', 10, 1, NOW()),
  ('Training', '#43A047', 20, 1, NOW()),
  ('Spiel/Match', '#E53935', 30, 1, NOW()),
  ('Turnier', '#8E24AA', 40, 1, NOW()),
  ('Feier/Events', '#FB8C00', 50, 1, NOW())
ON DUPLICATE KEY UPDATE
  `color` = VALUES(`color`),
  `sort_order` = VALUES(`sort_order`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `users` (`username`, `password_hash`, `role`, `is_active`, `created_at`) VALUES
  ('admin', '$2y$12$o91mXpAg1.B3tRf2nAXxXuifSxGSbmvp5MLMJbCSsB0eRg0QFoGv.', 'admin', 1, NOW()),
  ('editor', '$2y$12$cEaG2q3938.cgWxvlZS3EOteEVq1U0pn7F3LAQS2qsAvjPqVPk7C2', 'editor', 1, NOW())
ON DUPLICATE KEY UPDATE
  `role` = VALUES(`role`),
  `is_active` = VALUES(`is_active`),
  `password_hash` = `password_hash`;

-- Default-Passwörter:
--   admin / AdminPass123!
--   editor / EditorPass123!
