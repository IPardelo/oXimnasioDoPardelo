<?php
declare(strict_types=1);

function json_response(int $code, array $data, array $extraHeaders = []): void {
  http_response_code($code);
  header('Content-Type: application/json');
  header('Cache-Control: no-store');
  foreach ($extraHeaders as $name => $value) header("$name: $value");
  echo json_encode($data);
  exit;
}

function json_error(int $code, string $message): void {
  json_response($code, ['error' => $message]);
}

// Mirrors the Node backend's readBody: parses the JSON request body, 5 MB cap.
function read_json_body(): array {
  $raw = file_get_contents('php://input', false, null, 0, 5 * 1024 * 1024 + 1);
  if ($raw === false || $raw === '') return [];
  if (strlen($raw) > 5 * 1024 * 1024) json_error(413, 'body too large');
  $data = json_decode($raw, true);
  if (!is_array($data)) json_error(400, 'bad json');
  return $data;
}

function require_method(string $method): void {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) json_error(405, 'method not allowed');
}

function random_id(int $bytes = 12): string {
  return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}
