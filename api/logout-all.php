<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');

$user = require_auth($pdo, $cfg);
$pdo->prepare('UPDATE ximnasio_pardelo_users SET session_ver = session_ver + 1 WHERE id = ?')->execute([$user['id']]);
json_response(200, ['ok' => true], ['Set-Cookie' => clear_session_cookie($cfg)]);
