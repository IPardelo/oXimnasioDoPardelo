<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');
$user = require_auth($pdo, $cfg);
$pdo->prepare('DELETE FROM ximnasio_pardelo_rest_timers WHERE user_id = ?')->execute([$user['id']]);
json_response(200, ['ok' => true]);
