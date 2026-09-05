<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');
$user = require_auth($pdo, $cfg);
$body = read_json_body();

if (!empty($body['active'])) {
  $stmt = $pdo->prepare(
    'INSERT INTO ximnasio_pardelo_presence (user_id, name, ex_idx, ex_total, sets_done, sets_total, started_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE name = VALUES(name), ex_idx = VALUES(ex_idx), ex_total = VALUES(ex_total),
       sets_done = VALUES(sets_done), sets_total = VALUES(sets_total), started_at = VALUES(started_at),
       updated_at = VALUES(updated_at)'
  );
  $now = (int)(microtime(true) * 1000);
  $stmt->execute([
    $user['id'], mb_substr((string)($body['name'] ?? ''), 0, 60),
    (int)($body['exIdx'] ?? 0), (int)($body['exTotal'] ?? 0),
    (int)($body['setsDone'] ?? 0), (int)($body['setsTotal'] ?? 0),
    (int)($body['startedAt'] ?? $now), $now,
  ]);
} else {
  $pdo->prepare('DELETE FROM ximnasio_pardelo_presence WHERE user_id = ?')->execute([$user['id']]);
}
json_response(200, ['ok' => true]);
