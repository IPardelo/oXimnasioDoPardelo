<?php
// The single shared invite code required to create an account (ximnasio_pardelo_configuracion.invite_code).
// GET returns the current code; POST regenerates it. Rotating it only blocks NEW sign-ups with
// the old code — accounts already created keep working, since the code is never re-checked
// after registration.
require_once __DIR__ . '/../_bootstrap.php';
require_admin($pdo, $cfg);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
  json_response(200, ['code' => $cfg['invite_code']]);
}

require_method('POST');

// 10 hex chars = 40 bits, uppercased to match how the frontend types codes in. Plenty for a
// value that's meant to be shared with a handful of people and rotated periodically, not kept
// secret forever.
$code = strtoupper(bin2hex(random_bytes(5)));
$pdo->prepare(
  'INSERT INTO ximnasio_pardelo_configuracion (config_key, config_value) VALUES (\'invite_code\', ?)
   ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)'
)->execute([$code]);

json_response(200, ['code' => $code]);
