<?php
require_once __DIR__ . '/../_bootstrap.php';
require_method('GET');
require_admin($pdo, $cfg);

$id = (string)($_GET['id'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM ximnasio_pardelo_users WHERE id = ?');
$stmt->execute([$id]);
$u = $stmt->fetch();
if (!$u) json_error(404, 'no such user');

$stmt = $pdo->prepare('SELECT data, updated_at FROM ximnasio_pardelo_state WHERE user_id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
$S = $row && $row['data'] ? json_decode($row['data'], true) : [];

$routines = array_map(fn($r) => [
  'id' => $r['id'] ?? null, 'name' => $r['name'] ?? null, 'emoji' => $r['emoji'] ?? null,
  'count' => count($r['ex'] ?? []),
], $S['routines'] ?? []);

json_response(200, [
  'user' => [
    'id' => $u['id'], 'name' => $u['name'], 'created' => $u['created_at'],
    'disabled' => (bool)$u['disabled'], 'admin' => (bool)$u['admin'], 'invitedBy' => $u['invited_by'],
  ],
  'unit' => $S['unit'] ?? 'kg',
  'lastSync' => $row ? (int)$row['updated_at'] : null,
  'routines' => $routines,
  'bodyweight' => $S['bodyweight'] ?? [],
  'workouts' => array_reverse($S['workouts'] ?? []),
]);
