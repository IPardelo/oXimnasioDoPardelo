<?php
declare(strict_types=1);

// Web Push (VAPID + aes128gcm), implemented directly against ext-openssl — the Node backend
// used the `web-push` npm package; there is no Composer equivalent available here (this
// hosting has no package manager access), so this is a from-scratch implementation of
// RFC 8291 (message encryption) + RFC 8292 (VAPID). See docs/DATABASE_BACKEND.md for how it
// was verified (an encrypt→decrypt round-trip self-test, since a real push service can't be
// hit from a dev sandbox either).

function b64u_pad(string $s): string {
  return $s . str_repeat('=', (4 - strlen($s) % 4) % 4);
}

// ---- VAPID keypair: one per install, generated on first use and stored in `ximnasio_pardelo_settings`. ----

function vapid_get_keys(PDO $pdo): array {
  $stmt = $pdo->prepare('SELECT value FROM ximnasio_pardelo_settings WHERE name = ?');
  $stmt->execute(['vapid_private_pem']);
  $pem = $stmt->fetchColumn();
  $stmt->execute(['vapid_public_raw']);
  // second execute reuses the same prepared statement — re-fetch after re-running it
  $pub = $stmt->fetchColumn();

  if ($pem && $pub) return ['private_pem' => $pem, 'public_raw_b64u' => $pub];

  $res = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
  if ($res === false) throw new RuntimeException('openssl_pkey_new failed: ' . openssl_error_string());
  openssl_pkey_export($res, $pem);
  $details = openssl_pkey_get_details($res);
  $x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
  $y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
  $pub = b64u_encode("\x04" . $x . $y);

  $ins = $pdo->prepare('INSERT INTO ximnasio_pardelo_settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = value');
  $ins->execute(['vapid_private_pem', $pem]);
  $ins->execute(['vapid_public_raw', $pub]);
  // Someone else may have raced us to insert — re-read so every caller ends up with the same pair.
  $stmt->execute(['vapid_private_pem']); $pem = $stmt->fetchColumn();
  $stmt->execute(['vapid_public_raw']);  $pub = $stmt->fetchColumn();
  return ['private_pem' => $pem, 'public_raw_b64u' => $pub];
}

// ---- Build an OpenSSL EC public key (PEM) from a subscription's raw 65-byte uncompressed point. ----
// openssl has no "import raw point" call, so this hand-builds the fixed-shape SubjectPublicKeyInfo
// DER wrapper for a P-256 key (the ASN.1 here is constant except for the 65-byte point itself).

function ec_p256_public_pem_from_raw(string $raw65): string {
  if (strlen($raw65) !== 65 || $raw65[0] !== "\x04") throw new InvalidArgumentException('bad EC point');
  $spki = hex2bin(
    '3059301306072a8648ce3d020106082a8648ce3d030107034200'
  ) . $raw65;
  $b64 = base64_encode($spki);
  $lines = trim(chunk_split($b64, 64, "\n"));
  return "-----BEGIN PUBLIC KEY-----\n$lines\n-----END PUBLIC KEY-----\n";
}

// ---- VAPID JWT (RFC 8292): ES256-signed, raw r||s (not the DER openssl_sign produces). ----

function vapid_jwt(string $audience, string $subject, string $privatePem): string {
  $header = b64u_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
  $claims = b64u_encode(json_encode([
    'aud' => $audience,
    'exp' => time() + 12 * 3600,
    'sub' => $subject,
  ]));
  $signingInput = "$header.$claims";
  $priv = openssl_pkey_get_private($privatePem);
  if ($priv === false) throw new RuntimeException('bad VAPID private key');
  openssl_sign($signingInput, $der, $priv, OPENSSL_ALGO_SHA256);
  $raw = der_ecdsa_to_raw($der, 32);
  return $signingInput . '.' . b64u_encode($raw);
}

// DER `SEQUENCE { INTEGER r, INTEGER s }` -> fixed-width raw r||s, left-padded/truncated to $len each.
function der_ecdsa_to_raw(string $der, int $len): string {
  $pos = 0;
  $readLen = function () use ($der, &$pos): int {
    $b = ord($der[$pos++]);
    if ($b < 0x80) return $b;
    $n = $b & 0x7f; $v = 0;
    for ($i = 0; $i < $n; $i++) $v = ($v << 8) | ord($der[$pos++]);
    return $v;
  };
  if (ord($der[$pos++]) !== 0x30) throw new RuntimeException('bad DER signature');
  $readLen(); // sequence length, unused
  $ints = [];
  for ($k = 0; $k < 2; $k++) {
    if (ord($der[$pos++]) !== 0x02) throw new RuntimeException('bad DER signature');
    $l = $readLen();
    $v = substr($der, $pos, $l); $pos += $l;
    $v = ltrim($v, "\x00");
    $ints[] = str_pad($v, $len, "\x00", STR_PAD_LEFT);
  }
  return $ints[0] . $ints[1];
}

// ---- Message encryption (RFC 8291 aes128gcm). Split from delivery so it's testable without
// hitting a real push service — see tests/push_selftest.php. ----

// $sub = ['p256dh' => base64url, 'auth' => base64url]. Returns the raw request body to POST.
function web_push_encrypt(array $sub, string $payloadJson, string $vapidPrivatePem): string {
  $uaPublicRaw = b64u_decode($sub['p256dh']);
  $authSecret = b64u_decode($sub['auth']);
  if (strlen($uaPublicRaw) !== 65 || strlen($authSecret) !== 16) throw new RuntimeException('invalid subscription keys');

  // Ephemeral "application server" keypair — one per message, never reused or stored.
  $asRes = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
  $asDetails = openssl_pkey_get_details($asRes);
  $asX = str_pad($asDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT);
  $asY = str_pad($asDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);
  $asPublicRaw = "\x04" . $asX . $asY;

  $uaPubKey = openssl_pkey_get_public(ec_p256_public_pem_from_raw($uaPublicRaw));
  $sharedSecret = openssl_pkey_derive($uaPubKey, $asRes, 32);
  if ($sharedSecret === false) throw new RuntimeException('ECDH derive failed: ' . openssl_error_string());

  $salt = random_bytes(16);
  [$cek, $nonce] = webpush_derive_keys($sharedSecret, $authSecret, $uaPublicRaw, $asPublicRaw, $salt);

  $plaintext = $payloadJson . "\x02";   // delimiter: 0x02 = last (only) record
  $ciphertext = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
  if ($ciphertext === false) throw new RuntimeException('AES-GCM encrypt failed');

  $rs = pack('N', 4096); // record size, big-endian uint32 — plenty for our small payloads
  return $salt . $rs . chr(65) . $asPublicRaw . $ciphertext . $tag;
}

// $sub = ['endpoint' => ..., 'p256dh' => base64url, 'auth' => base64url]
// Returns the HTTP status code from the push service, or throws on a local/encryption error.
function web_push_send(array $sub, string $payloadJson, PDO $pdo, string $subject): int {
  $vapid = vapid_get_keys($pdo);
  $body = web_push_encrypt($sub, $payloadJson, $vapid['private_pem']);

  $endpointOrigin = parse_url($sub['endpoint'], PHP_URL_SCHEME) . '://' . parse_url($sub['endpoint'], PHP_URL_HOST);
  $jwt = vapid_jwt($endpointOrigin, $subject, $vapid['private_pem']);

  $ch = curl_init($sub['endpoint']);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/octet-stream',
      'Content-Encoding: aes128gcm',
      'TTL: 86400',
      'Authorization: vapid t=' . $jwt . ', k=' . $vapid['public_raw_b64u'],
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
  ]);
  curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return (int)$status;
}

// Shared by encrypt (here) and the self-test decrypt path in tests/push_selftest.php.
function webpush_derive_keys(string $sharedSecret, string $authSecret, string $uaPublicRaw, string $asPublicRaw, string $salt): array {
  // RFC 8291 §3.3 — authentication info binds both parties' public keys into the IKM.
  $prkKey = hash_hmac('sha256', $sharedSecret, $authSecret, true);
  $keyInfo = "WebPush: info\x00" . $uaPublicRaw . $asPublicRaw;
  $ikm = hkdf_expand($prkKey, $keyInfo, 32);

  // RFC 8188 — the actual content-encryption key/nonce, salted per message.
  $prk = hash_hmac('sha256', $salt, $ikm, true);
  $cek = hkdf_expand($prk, "Content-Encoding: aes128gcm\x00", 16);
  $nonce = hkdf_expand($prk, "Content-Encoding: nonce\x00", 12);
  return [$cek, $nonce];
}

function hkdf_expand(string $prk, string $info, int $len): string {
  $t = ''; $okm = ''; $i = 1;
  while (strlen($okm) < $len) {
    $t = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
    $okm .= $t;
    $i++;
  }
  return substr($okm, 0, $len);
}

// Sends to every subscription this user has, dropping any the push service reports gone
// (404/410 — the browser unsubscribed or the endpoint expired). Mirrors the Node backend's
// sendPush: best-effort, never throws for the caller.
function send_push_to_user(PDO $pdo, string $userId, array $payload, string $appName): void {
  $stmt = $pdo->prepare('SELECT * FROM ximnasio_pardelo_push_subscriptions WHERE user_id = ?');
  $stmt->execute([$userId]);
  $subs = $stmt->fetchAll();
  if (!$subs) return;
  $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
  $subject = 'mailto:admin@localhost';
  foreach ($subs as $sub) {
    try {
      $status = web_push_send($sub, $json, $pdo, $subject);
      if ($status === 404 || $status === 410) {
        $pdo->prepare('DELETE FROM ximnasio_pardelo_push_subscriptions WHERE id = ?')->execute([$sub['id']]);
      }
    } catch (Throwable $e) {
      error_log('O Ximnasio do Pardelo push send failed for user ' . $userId . ': ' . $e->getMessage());
    }
  }
}
