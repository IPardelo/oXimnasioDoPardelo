<?php
require_once __DIR__ . '/../_bootstrap.php';
require_method('POST');
require_admin($pdo, $cfg);

$body = read_json_body();
$id = (string)($body['id'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM ximnasio_pardelo_users WHERE id = ?');
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) json_error(404, 'no such user');
if ($u['admin']) json_error(400, 'cannot disable an admin');

$disabled = !empty($body['disabled']) ? 1 : 0;
$pdo->prepare('UPDATE ximnasio_pardelo_users SET disabled = ? WHERE id = ?')->execute([$disabled, $id]);
if ($disabled) $pdo->prepare('DELETE FROM ximnasio_pardelo_presence WHERE user_id = ?')->execute([$id]);
json_response(200, ['ok' => true, 'id' => $id, 'disabled' => (bool)$disabled]);
