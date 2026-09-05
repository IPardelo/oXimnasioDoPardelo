<?php
// "Sign in with Google": verifies the ID token Google Identity Services hands the frontend by
// asking Google's own tokeninfo endpoint (no JWKS/JWT library needed — one HTTPS GET, and Google
// does the signature + expiry check for us). A brand-new Google account still needs a valid
// invite code, same as a username/password one — otherwise the invite gate would be pointless,
// since anyone could just use Google instead. An already-linked Google account signs in with no
// code needed.
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');

if (empty($cfg['google_client_id'])) json_error(400, 'Google sign-in is not configured');

$body = read_json_body();
$idToken = (string)($body['id_token'] ?? '');
if ($idToken === '') json_error(400, 'id_token required');

$ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$raw = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($raw === false || $status !== 200) json_error(401, 'could not verify Google sign-in');
$claims = json_decode($raw, true);
if (!is_array($claims)) json_error(401, 'could not verify Google sign-in');

if (($claims['aud'] ?? '') !== $cfg['google_client_id']) json_error(401, 'token was not issued for this site');
if (!in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) json_error(401, 'invalid issuer');
if (empty($claims['email_verified']) || $claims['email_verified'] === 'false') json_error(401, 'Google email is not verified');

$sub = (string)($claims['sub'] ?? '');
$email = (string)($claims['email'] ?? '');
$name = (string)($claims['name'] ?? explode('@', $email)[0]);
if ($sub === '') json_error(401, 'invalid Google token');

$stmt = $pdo->prepare("SELECT * FROM ximnasio_pardelo_users WHERE auth_method = 'google' AND google_sub = ?");
$stmt->execute([$sub]);
$user = $stmt->fetch();

if (!$user) {
  $code = strtoupper(trim((string)($body['code'] ?? '')));
  if ($code === '') json_error(403, 'an invite code is required for your first sign-in');
  // Same single shared code as register.php, checked against ximnasio_pardelo_configuracion — not a
  // per-code table, so rotating it never affects accounts created earlier.
  if ($cfg['invite_code'] === '' || !hash_equals(strtoupper($cfg['invite_code']), $code)) json_error(403, 'invite code is invalid');

  $id = random_id();
  $pdo->beginTransaction();
  try {
    $ins = $pdo->prepare('INSERT INTO ximnasio_pardelo_users (id, name, auth_method, google_sub, email, invited_by) VALUES (?, ?, ?, ?, ?, ?)');
    $ins->execute([$id, $name, 'google', $sub, $email, $code]);

    $ins2 = $pdo->prepare('INSERT INTO ximnasio_pardelo_state (user_id, data, updated_at) VALUES (?, ?, ?)');
    $ins2->execute([$id, json_encode(new stdClass()), (int)(microtime(true) * 1000)]);

    $pdo->commit();
  } catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() === '23000') json_error(409, 'that Google account is already linked to a profile');
    throw $e;
  }

  $stmt = $pdo->prepare('SELECT * FROM ximnasio_pardelo_users WHERE id = ?');
  $stmt->execute([$id]);
  $user = $stmt->fetch();
}

if ($user['disabled']) json_error(403, 'this account has been disabled');
$user = maybe_promote_admin($pdo, $user, $cfg);
json_response(200, ['user' => user_public($user)], ['Set-Cookie' => make_session_cookie($user, $cfg)]);
