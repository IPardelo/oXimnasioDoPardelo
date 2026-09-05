<?php
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');

$body = read_json_body();
$username = trim((string)($body['username'] ?? ''));
$password = (string)($body['password'] ?? '');
if ($username === '' || $password === '') json_error(400, 'username and password required');

$stmt = $pdo->prepare("SELECT * FROM ximnasio_pardelo_users WHERE auth_method = 'password' AND username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

// Same error either way — don't tell a caller whether the username exists.
if (!$user || !password_verify($password, $user['password_hash'])) json_error(401, 'wrong username or password');
if ($user['disabled']) json_error(403, 'this account has been disabled');

$user = maybe_promote_admin($pdo, $user, $cfg);
json_response(200, ['user' => user_public($user)], ['Set-Cookie' => make_session_cookie($user, $cfg)]);
