<?php
// Runs periodically via your host's own cron/task scheduler — nothing here can stay resident in
// memory like the Node backend's setInterval did, so this polls instead. Two jobs:
//   1. rest_timers due to fire (see push-rest-timer.php)
//   2. the "workout planned today" day-reminder, once per user per local day
// Schedule this to run every minute for both to feel prompt — see docs/DATABASE_BACKEND.md for
// exactly how to add it in IONOS's control panel. It is safe to run concurrently with itself
// (rows are claimed with UPDATE ... LIMIT before being acted on) if a run ever overlaps the next.
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

// Callable both as a real HTTP-triggered cron hit and from the CLI (`php api/cron.php`) for
// manual testing — most hosts' schedulers call a URL, some call a shell command instead.
if (php_sapi_name() !== 'cli') {
  // Optional shared secret so this URL can't be hit by randoms to spam pushes — set
  // cron_secret in config.php and pass ?key=... from the scheduler if you want this locked down.
  if (!empty($cfg['cron_secret']) && ($_GET['key'] ?? '') !== $cfg['cron_secret']) {
    json_error(403, 'forbidden');
  }
}

$now = (int)(microtime(true) * 1000);
$appName = $cfg['app_name'] ?? 'O Ximnasio do Pardelo';
$fired = ['rest_timers' => 0, 'day_reminders' => 0];

// ---- 1. due rest timers ----
$due = $pdo->prepare('SELECT user_id FROM ximnasio_pardelo_rest_timers WHERE fire_at <= ?');
$due->execute([$now]);
foreach ($due->fetchAll() as $row) {
  $pdo->prepare('DELETE FROM ximnasio_pardelo_rest_timers WHERE user_id = ? AND fire_at <= ?')->execute([$row['user_id'], $now]);
  send_push_to_user($pdo, $row['user_id'], ['title' => 'Rest over 💪', 'body' => 'Time for your next set.', 'tag' => 'rest-timer'], $appName);
  $fired['rest_timers']++;
}

// ---- 2. day reminders ----
function effective_routine_id(array $S, string $iso): ?string {
  $ov = $S['dayPlan'][$iso] ?? null;
  if ($ov === 'rest') return null;
  if ($ov && !empty($S['routines']) && in_array($ov, array_column($S['routines'], 'id'), true)) return $ov;
  $wd = (int)(new DateTime($iso . 'T12:00:00'))->format('w');
  return $S['week'][(string)$wd] ?? $S['week'][$wd] ?? null;
}
function user_now(string $tz): ?array {
  try {
    $dt = new DateTime('now', new DateTimeZone($tz));
    return ['date' => $dt->format('Y-m-d'), 'hhmm' => $dt->format('H:i')];
  } catch (Throwable $e) { return null; }
}

$stmt = $pdo->query(
  'SELECT DISTINCT u.id, u.last_reminder, s.data
   FROM ximnasio_pardelo_users u
   JOIN ximnasio_pardelo_push_subscriptions ps ON ps.user_id = u.id
   JOIN ximnasio_pardelo_state s ON s.user_id = u.id
   WHERE u.disabled = 0'
);
foreach ($stmt->fetchAll() as $row) {
  $S = json_decode($row['data'], true);
  if (!is_array($S) || empty($S['reminder']['on'])) continue;
  $tz = $S['reminder']['tz'] ?? 'UTC';
  $now = user_now($tz);
  if (!$now) continue;
  if (($S['reminder']['time'] ?? '') !== $now['hhmm']) continue;
  if ($row['last_reminder'] === $now['date']) continue;
  $loggedToday = false;
  foreach ($S['workouts'] ?? [] as $w) { if (($w['d'] ?? null) === $now['date']) { $loggedToday = true; break; } }
  if ($loggedToday) continue;
  $rid = effective_routine_id($S, $now['date']);
  if (!$rid) continue; // rest day — nothing planned

  $claim = $pdo->prepare('UPDATE ximnasio_pardelo_users SET last_reminder = ? WHERE id = ? AND (last_reminder IS NULL OR last_reminder <> ?)');
  $claim->execute([$now['date'], $row['id'], $now['date']]);
  if ($claim->rowCount() !== 1) continue; // another concurrent cron run already claimed this user/day

  $routine = null;
  foreach ($S['routines'] ?? [] as $r) { if (($r['id'] ?? null) === $rid) { $routine = $r; break; } }
  $title = $routine ? (($routine['emoji'] ?? '🏋️') . ' ' . $routine['name'] . ' today') : 'Workout planned today';
  send_push_to_user($pdo, $row['id'], ['title' => $title, 'body' => "It's on your plan — let's go 💪", 'tag' => 'day-reminder'], $appName);
  $fired['day_reminders']++;
}

if (php_sapi_name() === 'cli') {
  echo json_encode($fired, JSON_PRETTY_PRINT) . "\n";
} else {
  json_response(200, $fired);
}
