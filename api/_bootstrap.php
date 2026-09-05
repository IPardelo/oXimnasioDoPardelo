<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');   // never leak stack traces to the client — see error_log()

require_once __DIR__ . '/lib/util.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/session.php';
require_once __DIR__ . '/lib/push.php';

set_exception_handler(function (Throwable $e) {
  error_log('O Ximnasio do Pardelo api error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: application/json');
  }
  echo json_encode(['error' => 'server error']);
  exit;
});

$cfg = load_config();
$pdo = get_pdo();
$cfg = load_runtime_config($pdo, $cfg);
