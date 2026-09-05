<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');
$count = (int)$pdo->query('SELECT COUNT(*) FROM ximnasio_pardelo_users')->fetchColumn();
json_response(200, ['ok' => true, 'users' => $count]);
