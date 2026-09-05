<?php
// Public config the login screen needs before anyone is signed in — which buttons to show.
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');

json_response(200, [
  'google_enabled' => !empty($cfg['google_client_id']),
  'google_client_id' => $cfg['google_client_id'] ?? '',
]);
