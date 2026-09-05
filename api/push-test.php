<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');
$user = require_auth($pdo, $cfg);
send_push_to_user($pdo, $user['id'], ['title' => $cfg['app_name'] ?? 'O Ximnasio do Pardelo', 'body' => 'Test notification ✅ — this is what alerts look like.', 'tag' => 'test'], $cfg['app_name'] ?? 'O Ximnasio do Pardelo');
json_response(200, ['ok' => true]);
