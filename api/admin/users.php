<?php
require_once __DIR__ . '/../_bootstrap.php';
require_method('GET');
require_admin($pdo, $cfg);

$rows = $pdo->query(
  'SELECT u.id, u.name, u.created_at, u.disabled, u.admin, u.invited_by, s.data, s.updated_at,
     EXISTS(SELECT 1 FROM ximnasio_pardelo_push_subscriptions ps WHERE ps.user_id = u.id) AS has_push,
     p.name AS live_name, p.ex_idx, p.ex_total, p.sets_done, p.sets_total, p.started_at, p.updated_at AS live_updated_at
   FROM ximnasio_pardelo_users u
   LEFT JOIN ximnasio_pardelo_state s ON s.user_id = u.id
   LEFT JOIN ximnasio_pardelo_presence p ON p.user_id = u.id'
)->fetchAll();

$now = (int)(microtime(true) * 1000);
$PRESENCE_TTL = 70000;
$users = array_map(function ($u) use ($now, $PRESENCE_TTL) {
  $S = $u['data'] ? json_decode($u['data'], true) : [];
  $workouts = $S['workouts'] ?? [];
  $last = end($workouts) ?: null;
  $live = null;
  if ($u['live_updated_at'] !== null && ($now - (int)$u['live_updated_at']) <= $PRESENCE_TTL) {
    $live = [
      'name' => $u['live_name'], 'exIdx' => (int)$u['ex_idx'], 'exTotal' => (int)$u['ex_total'],
      'setsDone' => (int)$u['sets_done'], 'setsTotal' => (int)$u['sets_total'], 'startedAt' => (int)$u['started_at'],
    ];
  }
  return [
    'id' => $u['id'], 'name' => $u['name'], 'created' => $u['created_at'],
    'disabled' => (bool)$u['disabled'], 'admin' => (bool)$u['admin'], 'invitedBy' => $u['invited_by'],
    'workouts' => count($workouts),
    'lastWorkout' => $last['d'] ?? null,
    'lastSync' => $u['updated_at'] !== null ? (int)$u['updated_at'] : null,
    'hasPush' => (bool)$u['has_push'],
    'live' => $live,
  ];
}, $rows);

json_response(200, ['users' => $users, 'now' => $now]);
