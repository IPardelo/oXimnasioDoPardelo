<?php
// Node could just setTimeout() in memory; shared PHP hosting has no persistent process to hold
// that. Instead this stores "fire at" and cron.php (run every minute by the host's own cron —
// see docs/DATABASE_BACKEND.md) sends the push once that time has passed. That trades the
// original's sub-second precision for up-to-~1-minute jitter, same as the day reminder already
// had at 10s granularity in Node — here it's whatever your host's cron scheduler allows.
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');
$user = require_auth($pdo, $cfg);
$body = read_json_body();
$sec = max(1, min(3600, (int)round((float)($body['seconds'] ?? 0))));
if (!$sec) json_error(400, 'seconds required');
$fireAt = (int)(microtime(true) * 1000) + $sec * 1000;
$stmt = $pdo->prepare(
  'INSERT INTO ximnasio_pardelo_rest_timers (user_id, fire_at) VALUES (?, ?)
   ON DUPLICATE KEY UPDATE fire_at = VALUES(fire_at)'
);
$stmt->execute([$user['id'], $fireAt]);
json_response(200, ['ok' => true]);
