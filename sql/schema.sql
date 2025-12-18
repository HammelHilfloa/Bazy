-- Basistabellen aus der Demo (Gruppen + Events)
CREATE TABLE IF NOT EXISTS groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  location VARCHAR(200),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_events_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
  CONSTRAINT chk_date_range CHECK (end_at >= start_at)
);

INSERT INTO groups (name) VALUES
  ('U11'), ('U13'), ('U15'), ('U18'), ('U21'), ('Senioren'), ('JSL'), ('Jugend'), ('Große')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Tabelle für Feiertage & Schulferien (Cache)
CREATE TABLE IF NOT EXISTS holiday_entries (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  source VARCHAR(32) NOT NULL,
  kind ENUM('public_holiday','school_holiday') NOT NULL,
  name VARCHAR(255) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  region VARCHAR(16) NOT NULL DEFAULT 'DE-NW',
  year SMALLINT NOT NULL,
  checksum CHAR(40) NULL,
  fetched_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_region_year (region, year),
  INDEX idx_start_date (start_date),
  UNIQUE KEY uniq_holiday (region, year, kind, name, start_date, end_date)
);

CREATE TABLE IF NOT EXISTS holiday_sync (
  `key` VARCHAR(64) PRIMARY KEY,
  value TEXT
);
