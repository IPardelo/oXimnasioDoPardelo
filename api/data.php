<?php
require_once __DIR__ . '/_bootstrap.php';
$user = require_auth($pdo, $cfg);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $stmt = $pdo->prepare('SELECT data FROM ximnasio_pardelo_state WHERE user_id = ?');
  $stmt->execute([$user['id']]);
  $data = $stmt->fetchColumn();
  // Embed the stored JSON verbatim rather than decode-then-re-encode: json_decode(...,true) turns
  // an empty `{}` into a PHP [] that json_encode then emits as `[]`, not `{}` — a harmless quirk
  // for the frontend's Object.assign, but pointless work and a needless behavior gap either way.
  http_response_code(200);
  header('Content-Type: application/json');
  header('Cache-Control: no-store');
  echo '{"state":' . ($data !== false ? $data : 'null') . '}';
  exit;
}

if ($method === 'PUT') {
  $body = read_json_body();
  if (!isset($body['state']) || !is_array($body['state'])) json_error(400, 'state required');
  $state = $body['state'];
  unset($state['active']); // in-progress workouts stay device-local, same as the Node backend

  $ts = isset($state['_ts']) ? (int)$state['_ts'] : (int)(microtime(true) * 1000);
  $json = json_encode($state, JSON_UNESCAPED_UNICODE);
  $stmt = $pdo->prepare(
    'INSERT INTO ximnasio_pardelo_state (user_id, data, updated_at) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE data = VALUES(data), updated_at = VALUES(updated_at)'
  );
  $stmt->execute([$user['id'], $json, $ts]);
  json_response(200, ['ok' => true, 'ts' => $ts ?: null]);
}

json_error(405, 'method not allowed');
