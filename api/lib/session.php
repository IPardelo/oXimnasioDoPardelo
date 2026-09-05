<?php
declare(strict_types=1);

// Signed session cookie — same scheme as the old Node backend: payload `<uid>:<expiry>:<ver>`,
// HMAC-SHA256 over it, base64url. Bumping a user's session_ver (logout-all) invalidates every
// cookie ever issued for that account without needing a server-side session store.

function b64u_encode(string $bin): string {
  return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}
function b64u_decode(string $s): string {
  return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)) ?: '';
}

function session_sign(string $payload, string $secret): string {
  return $payload . '.' . b64u_encode(hash_hmac('sha256', $payload, $secret, true));
}

function session_verify(string $token, string $secret): ?string {
  $i = strrpos($token, '.');
  if ($i === false) return null;
  $payload = substr($token, 0, $i);
  $mac = substr($token, $i + 1);
  $expect = b64u_encode(hash_hmac('sha256', $payload, $secret, true));
  if (!hash_equals($expect, $mac)) return null;
  return $payload;
}

function make_session_cookie(array $user, array $cfg): string {
  $exp = (time() + (int)($cfg['session_days'] ?? 90) * 86400) * 1000;
  $payload = $user['id'] . ':' . $exp . ':' . (int)$user['session_ver'];
  $token = session_sign($payload, $cfg['session_secret']);
  $secure = str_starts_with($cfg['origin'], 'https:') ? '; Secure' : '';
  $maxAge = (int)($cfg['session_days'] ?? 90) * 86400;
  return "gymsid=$token; Path=/; Max-Age=$maxAge; HttpOnly{$secure}; SameSite=Lax";
}

function clear_session_cookie(array $cfg): string {
  $secure = str_starts_with($cfg['origin'], 'https:') ? '; Secure' : '';
  return "gymsid=; Path=/; Max-Age=0; HttpOnly{$secure}; SameSite=Lax";
}

// Resolves the caller's user row from the gymsid cookie, or null. Disabled accounts and cookies
// whose embedded version no longer matches session_ver (signed out everywhere) both read as null.
function read_session(PDO $pdo, array $cfg): ?array {
  $token = $_COOKIE['gymsid'] ?? null;
  if (!$token) return null;
  $payload = session_verify($token, $cfg['session_secret']);
  if (!$payload) return null;
  $parts = explode(':', $payload);
  if (count($parts) !== 3) return null;
  [$uid, $exp, $ver] = $parts;
  if ((int)$exp < (int)(microtime(true) * 1000)) return null;
  $stmt = $pdo->prepare('SELECT * FROM ximnasio_pardelo_users WHERE id = ?');
  $stmt->execute([$uid]);
  $user = $stmt->fetch();
  if (!$user || $user['disabled']) return null;
  if ((int)$ver !== (int)$user['session_ver']) return null;
  return $user;
}

function require_auth(PDO $pdo, array $cfg): array {
  $user = read_session($pdo, $cfg);
  if (!$user) json_error(401, 'not signed in');
  return $user;
}

function is_admin(array $user): bool {
  return (bool)$user['admin'];
}

function require_admin(PDO $pdo, array $cfg): array {
  $user = require_auth($pdo, $cfg);
  if (!is_admin($user)) json_error(403, 'forbidden');
  return $user;
}

// Applies config's admin_usernames/admin_emails on sign-in, so promoting an account to admin is
// just editing the ximnasio_pardelo_configuracion table (admin_usernames/admin_emails rows) and signing
// in again — no manual UPDATE on the user row needed. No-op once admin already equals 1.
function maybe_promote_admin(PDO $pdo, array $user, array $cfg): array {
  if ($user['admin']) return $user;
  $match = false;
  if ($user['auth_method'] === 'password' && $user['username']) {
    foreach ($cfg['admin_usernames'] ?? [] as $u) {
      if (strcasecmp($u, $user['username']) === 0) { $match = true; break; }
    }
  } elseif ($user['auth_method'] === 'google' && $user['email']) {
    foreach ($cfg['admin_emails'] ?? [] as $e) {
      if (strcasecmp($e, $user['email']) === 0) { $match = true; break; }
    }
  }
  if ($match) {
    $pdo->prepare('UPDATE ximnasio_pardelo_users SET admin = 1 WHERE id = ?')->execute([$user['id']]);
    $user['admin'] = 1;
  }
  return $user;
}

function user_public(array $user): array {
  return ['id' => $user['id'], 'name' => $user['name'], 'admin' => (bool)$user['admin']];
}
