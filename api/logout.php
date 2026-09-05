<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');
json_response(200, ['ok' => true], ['Set-Cookie' => clear_session_cookie($cfg)]);
