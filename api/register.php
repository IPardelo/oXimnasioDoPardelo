<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');

$body = read_json_body();
$name = trim((string)($body['name'] ?? ''));
$username = trim((string)($body['username'] ?? ''));
$password = (string)($body['password'] ?? '');
$code = strtoupper(trim((string)($body['code'] ?? '')));

if ($name === '' || mb_strlen($name) > 40) json_error(400, 'name required');
if (!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/', $username)) json_error(400, 'username must be 3-40 characters (letters, numbers, _ . -)');
if (strlen($password) < 8) json_error(400, 'password must be at least 8 characters');
if ($code === '') json_error(403, 'an invite code is required');

// Checked against the single shared code in ximnasio_pardelo_configuracion (invite_code), not a table of
// one-time codes — the same code can register any number of accounts until an admin rotates it.
// An empty configured code means registration is closed (no config value can match it).
if ($cfg['invite_code'] === '' || !hash_equals(strtoupper($cfg['invite_code']), $code)) json_error(403, 'invite code is invalid');

$id = random_id();
$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo->beginTransaction();
try {
  $ins = $pdo->prepare('INSERT INTO ximnasio_pardelo_users (id, name, auth_method, username, password_hash, invited_by) VALUES (?, ?, ?, ?, ?, ?)');
  $ins->execute([$id, $name, 'password', $username, $hash, $code]);

  $ins2 = $pdo->prepare('INSERT INTO ximnasio_pardelo_state (user_id, data, updated_at) VALUES (?, ?, ?)');
  $ins2->execute([$id, json_encode(new stdClass()), (int)(microtime(true) * 1000)]);

  $pdo->commit();
} catch (PDOException $e) {
  $pdo->rollBack();
  if ($e->getCode() === '23000') json_error(409, 'that username is already taken');
  throw $e;
}

$stmt = $pdo->prepare('SELECT * FROM ximnasio_pardelo_users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
$user = maybe_promote_admin($pdo, $user, $cfg);

json_response(200, ['user' => user_public($user)], ['Set-Cookie' => make_session_cookie($user, $cfg)]);
