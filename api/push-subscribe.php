<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');
$user = require_auth($pdo, $cfg);
$body = read_json_body();
$sub = $body['subscription'] ?? null;
if (!is_array($sub) || empty($sub['endpoint']) || empty($sub['keys']['p256dh']) || empty($sub['keys']['auth'])) {
  json_error(400, 'invalid subscription');
}
$pdo->prepare('DELETE FROM ximnasio_pardelo_push_subscriptions WHERE endpoint = ?')->execute([$sub['endpoint']]);
$stmt = $pdo->prepare('INSERT INTO ximnasio_pardelo_push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)');
$stmt->execute([$user['id'], $sub['endpoint'], $sub['keys']['p256dh'], $sub['keys']['auth']]);
json_response(200, ['ok' => true]);
