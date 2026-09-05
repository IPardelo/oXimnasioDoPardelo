-- O Ximnasio do Pardelo — MariaDB schema (replaces the JSON-file storage of the Node backend).
-- Import this once via your hosting's phpMyAdmin / database panel before deploying api/.
-- Charset/collation: utf8mb4 throughout, so names, notes and JSON state survive any language.
-- Every table is prefixed ximnasio_pardelo_ so this can share a database with other apps (WordPress, etc.)
-- without any name clashes.

CREATE TABLE IF NOT EXISTS ximnasio_pardelo_users (
  id            VARCHAR(32)   NOT NULL PRIMARY KEY,   -- random id, same shape as the old passkey uid
  name          VARCHAR(60)   NOT NULL,
  auth_method   ENUM('password','google') NOT NULL,
  username      VARCHAR(40)   NULL,                   -- set when auth_method = 'password'
  password_hash VARCHAR(255)  NULL,                   -- PHP password_hash() — bcrypt/argon2
  google_sub    VARCHAR(255)  NULL,                   -- Google's stable "sub" claim, when auth_method = 'google'
  email         VARCHAR(255)  NULL,                   -- from Google, for display + admin_emails matching only
  admin         TINYINT(1)    NOT NULL DEFAULT 0,
  disabled      TINYINT(1)    NOT NULL DEFAULT 0,
  session_ver   INT UNSIGNED  NOT NULL DEFAULT 0,      -- bumped by "sign out everywhere"
  invited_by    VARCHAR(16)   NULL,                    -- invite code used at signup, for display only
  last_reminder DATE          NULL,                    -- last date the day-reminder cron fired for this user
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_username   (username),
  UNIQUE KEY uniq_google_sub (google_sub)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The whole per-user app state (plan, workouts, body weight, settings) as one JSON blob —
-- same shape the frontend already reads/writes as a unit, just in a column instead of a file.
CREATE TABLE IF NOT EXISTS ximnasio_pardelo_state (
  user_id     VARCHAR(32)  NOT NULL PRIMARY KEY,
  data        LONGTEXT     NOT NULL,
  updated_at  BIGINT UNSIGNED NOT NULL,   -- mirrors the JSON's own _ts (ms since epoch), indexed for admin's "last sync"
  FOREIGN KEY (user_id) REFERENCES ximnasio_pardelo_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ximnasio_pardelo_push_subscriptions (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id     VARCHAR(32)  NOT NULL,
  endpoint    VARCHAR(512) NOT NULL,
  p256dh      VARCHAR(255) NOT NULL,
  auth        VARCHAR(255) NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_endpoint (endpoint(255)),
  FOREIGN KEY (user_id) REFERENCES ximnasio_pardelo_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- "Who's training right now" for the admin dashboard — a heartbeat row per user, upserted on
-- every /api/activity.php ping and read back with a short TTL (see admin/users.php).
CREATE TABLE IF NOT EXISTS ximnasio_pardelo_presence (
  user_id     VARCHAR(32)  NOT NULL PRIMARY KEY,
  name        VARCHAR(60)  NOT NULL,
  ex_idx      INT          NOT NULL DEFAULT 0,
  ex_total    INT          NOT NULL DEFAULT 0,
  sets_done   INT          NOT NULL DEFAULT 0,
  sets_total  INT          NOT NULL DEFAULT 0,
  started_at  BIGINT UNSIGNED NOT NULL,
  updated_at  BIGINT UNSIGNED NOT NULL,
  FOREIGN KEY (user_id) REFERENCES ximnasio_pardelo_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rest-timer "fire a push at this time" — cron.php (see docs/DATABASE_BACKEND.md) polls this
-- instead of the Node backend's in-memory setTimeout, which can't survive on shared hosting.
CREATE TABLE IF NOT EXISTS ximnasio_pardelo_rest_timers (
  user_id    VARCHAR(32) NOT NULL PRIMARY KEY,
  fire_at    BIGINT UNSIGNED NOT NULL,   -- ms since epoch
  FOREIGN KEY (user_id) REFERENCES ximnasio_pardelo_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Internal, system-generated state — currently just the VAPID keypair used to sign push
-- messages, created once on first use. Not meant to be hand-edited; see ximnasio_pardelo_configuracion
-- below for the settings you actually configure.
CREATE TABLE IF NOT EXISTS ximnasio_pardelo_settings (
  name   VARCHAR(40)  NOT NULL PRIMARY KEY,
  value  TEXT         NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Deployment configuration — the parameters you set for your own instance (site URL, Google
-- Sign-In client ID, admin lists, ...), editable here via phpMyAdmin instead of by editing
-- config.php on the server. config.php still holds the database credentials and the two signing
-- secrets (session_secret, cron_secret) — those have to stay in a file, since the app needs them
-- just to reach this table in the first place.
CREATE TABLE IF NOT EXISTS ximnasio_pardelo_configuracion (
  config_key   VARCHAR(64)  NOT NULL PRIMARY KEY,
  config_value TEXT         NULL,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed rows — fill in origin and google_client_id after importing (or leave google_client_id
-- blank to hide the Google button and only offer username/password sign-in). invite_code is the
-- single shared code required to create an account (both sign-in methods) — change the default
-- below to your own value before your first sign-up. Rotate it anytime from the admin dashboard
-- or by editing this row: past sign-ups keep working, since the code is only ever checked at
-- registration, never again afterwards — rotating it only stops NEW sign-ups with the old code.
INSERT INTO ximnasio_pardelo_configuracion (config_key, config_value) VALUES
  ('origin', 'https://your-domain.example'),
  ('session_days', '90'),
  ('google_client_id', ''),
  ('app_name', 'O Ximnasio do Pardelo'),
  ('admin_usernames', ''),   -- comma-separated, matches password accounts' username (case-insensitive)
  ('admin_emails', ''),      -- comma-separated, matches Google accounts' email (case-insensitive)
  ('invite_code', 'CHANGE-ME')
ON DUPLICATE KEY UPDATE config_key = config_key;
