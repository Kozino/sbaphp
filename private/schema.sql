-- Savio Bosco Alphas 2012 — MySQL / MariaDB schema (cPanel)
-- Applied automatically by the app on first visit (and whenever
-- SCHEMA_VERSION in private/bootstrap.php goes up). Idempotent.

CREATE TABLE IF NOT EXISTS app_meta (
  meta_key   VARCHAR(64) NOT NULL PRIMARY KEY,
  meta_value VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(64) NOT NULL UNIQUE,
  name       VARCHAR(191) NOT NULL DEFAULT '',
  password   VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS members (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  unique_id           VARCHAR(16) NOT NULL UNIQUE,
  first_name          VARCHAR(120) NOT NULL,
  middle_name         VARCHAR(120) NOT NULL DEFAULT '',
  last_name           VARCHAR(120) NOT NULL,
  email               VARCHAR(191) NOT NULL UNIQUE,
  phone               VARCHAR(40) NOT NULL DEFAULT '',
  hometown            VARCHAR(191) NOT NULL DEFAULT '',
  hometown_parish     VARCHAR(191) NOT NULL DEFAULT '',
  residential_address TEXT NOT NULL,
  permanent_address   TEXT NOT NULL,
  status              ENUM('pending','active','rejected') NOT NULL DEFAULT 'pending',
  registered_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_members_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  member_id  INT NOT NULL,
  type       VARCHAR(40) NOT NULL,
  year       INT NOT NULL,
  expected   DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid       DECIMAL(14,2) NOT NULL DEFAULT 0,
  note       VARCHAR(500) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payment (member_id, type, year),
  CONSTRAINT fk_payment_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(255) NOT NULL,
  body       MEDIUMTEXT NOT NULL,
  image      VARCHAR(255) NOT NULL DEFAULT '',
  date       DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_news_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blogs (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(255) NOT NULL,
  author     VARCHAR(191) NOT NULL DEFAULT '',
  excerpt    VARCHAR(500) NOT NULL DEFAULT '',
  body       MEDIUMTEXT NOT NULL,
  image      VARCHAR(255) NOT NULL DEFAULT '',
  date       DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_blogs_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(255) NOT NULL DEFAULT '',
  caption    VARCHAR(500) NOT NULL DEFAULT '',
  image      VARCHAR(255) NOT NULL,
  event_year INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_gallery_year (event_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  type        VARCHAR(40) NOT NULL,
  year        INT NULL,
  title       VARCHAR(255) NOT NULL DEFAULT '',
  filename    VARCHAR(255) NOT NULL DEFAULT '',
  text        MEDIUMTEXT NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_documents_type_year (type, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(191) NOT NULL,
  email      VARCHAR(191) NOT NULL DEFAULT '',
  phone      VARCHAR(40) NOT NULL DEFAULT '',
  subject    VARCHAR(255) NOT NULL DEFAULT '',
  message    TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  `key`   VARCHAR(64) NOT NULL PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Past Executives: tenures (start/end year). executives.tenure_id NULL = current.
CREATE TABLE IF NOT EXISTS executive_tenures (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  start_year  INT NOT NULL,
  end_year    INT NOT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS executives (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(191) NOT NULL,
  position    VARCHAR(191) NOT NULL,
  image       VARCHAR(255) NOT NULL DEFAULT '',
  sort_order  INT NOT NULL DEFAULT 0,
  tenure_id   INT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_exec_tenure (tenure_id),
  CONSTRAINT fk_exec_tenure FOREIGN KEY (tenure_id) REFERENCES executive_tenures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Account: income & expenditure per year
CREATE TABLE IF NOT EXISTS account_years (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  year                INT NOT NULL UNIQUE,
  opening_balance     DECIMAL(14,2) NOT NULL DEFAULT 0,
  financial_secretary VARCHAR(191) NOT NULL DEFAULT '',
  report_note         TEXT NOT NULL,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_entries (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  year        INT NOT NULL,
  kind        ENUM('income','expense') NOT NULL,
  name        VARCHAR(255) NOT NULL,
  amount      DECIMAL(14,2) NOT NULL,
  entry_date  DATE NOT NULL,
  note        VARCHAR(500) NOT NULL DEFAULT '',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_account_year (year, kind, entry_date),
  CONSTRAINT fk_entry_year FOREIGN KEY (year) REFERENCES account_years(year) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
