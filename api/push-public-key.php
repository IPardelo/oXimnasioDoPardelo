<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');
$vapid = vapid_get_keys($pdo);
json_response(200, ['key' => $vapid['public_raw_b64u']]);
