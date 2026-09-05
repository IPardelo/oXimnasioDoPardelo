<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');
$user = require_auth($pdo, $cfg);
$body = read_json_body();
$endpoint = (string)($body['endpoint'] ?? '');
$pdo->prepare('DELETE FROM ximnasio_pardelo_push_subscriptions WHERE user_id = ? AND endpoint = ?')->execute([$user['id'], $endpoint]);
json_response(200, ['ok' => true]);
