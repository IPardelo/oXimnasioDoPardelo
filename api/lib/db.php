<?php
declare(strict_types=1);

function load_config(): array {
  $path = __DIR__ . '/../config.php';
  if (!file_exists($path)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'config.php missing — copy config.example.php to config.php and fill it in']);
    exit;
  }
  return require $path;
}

function get_pdo(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $cfg = load_config();
  $db = $cfg['db'];
  // 'port' is optional — leave it out of config.php entirely to use MariaDB's default (3306),
  // which is what almost every host (IONOS included) uses even for its external hostname.
  $port = !empty($db['port']) ? ';port=' . (int)$db['port'] : '';
  $dsn = "mysql:host={$db['host']}{$port};dbname={$db['name']};charset=utf8mb4";
  try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  } catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'database connection failed']);
    error_log('O Ximnasio do Pardelo db connect failed: ' . $e->getMessage());
    exit;
  }
  return $pdo;
}

// Merges ximnasio_pardelo_configuracion (site URL, Google client ID, admin lists, invite code, ...) on
// top of config.php's array — those are edited via phpMyAdmin instead of by uploading a new
// config.php. Only the database credentials and the two signing secrets stay file-only, since
// they're needed just to reach this table in the first place. Missing rows fall back to sane
// defaults so a fresh import (or a table not yet migrated) doesn't break the whole app.
function load_runtime_config(PDO $pdo, array $cfg): array {
  $kv = [];
  try {
    foreach ($pdo->query('SELECT config_key, config_value FROM ximnasio_pardelo_configuracion') as $row) {
      $kv[$row['config_key']] = $row['config_value'];
    }
  } catch (PDOException $e) {
    error_log('O Ximnasio do Pardelo: could not read ximnasio_pardelo_configuracion — did you import the latest schema.sql? ' . $e->getMessage());
  }
  $split = fn($s) => array_values(array_filter(array_map('trim', explode(',', (string)$s)), fn($v) => $v !== ''));

  $cfg['origin']           = $kv['origin']           ?? ($cfg['origin'] ?? '');
  $cfg['session_days']     = isset($kv['session_days']) && $kv['session_days'] !== '' ? (int)$kv['session_days'] : ($cfg['session_days'] ?? 90);
  $cfg['google_client_id'] = $kv['google_client_id'] ?? '';
  $cfg['app_name']         = ($kv['app_name'] ?? '') !== '' ? $kv['app_name'] : 'O Ximnasio do Pardelo';
  $cfg['admin_usernames']  = $split($kv['admin_usernames'] ?? '');
  $cfg['admin_emails']     = $split($kv['admin_emails'] ?? '');
  $cfg['invite_code']      = trim((string)($kv['invite_code'] ?? ''));
  return $cfg;
}
