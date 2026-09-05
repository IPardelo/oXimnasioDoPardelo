<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');

$user = read_session($pdo, $cfg);
if (!$user) json_error(401, 'not signed in');
json_response(200, ['user' => user_public($user)]);
