<?php
declare(strict_types=1);
// Standalone correctness check for lib/push.php's RFC 8291 encryption — no network involved.
// It plays both sides: encrypts a payload the way the server does (web_push_encrypt), then
// decrypts the resulting body the way a browser's push service worker would (ECDH from the
// *subscriber's* private key + the same HKDF chain), and checks the plaintext round-trips.
// Run: php api/tests/push_selftest.php

require_once __DIR__ . '/../lib/session.php'; // b64u_encode/decode
require_once __DIR__ . '/../lib/push.php';

function fail(string $msg): never { fwrite(STDERR, "FAIL: $msg\n"); exit(1); }

// --- simulate a browser's PushSubscription keypair ---
$uaRes = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
$uaDetails = openssl_pkey_get_details($uaRes);
$uaX = str_pad($uaDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT);
$uaY = str_pad($uaDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);
$uaPublicRaw = "\x04" . $uaX . $uaY;
$authSecret = random_bytes(16);

$sub = ['p256dh' => b64u_encode($uaPublicRaw), 'auth' => b64u_encode($authSecret)];

// --- VAPID keypair (only its private PEM is needed by web_push_encrypt's caller in real use;
// encryption itself doesn't touch VAPID at all, so any throwaway key works here) ---
$vapidRes = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
openssl_pkey_export($vapidRes, $vapidPem);

$payload = json_encode(['title' => 'O Ximnasio do Pardelo', 'body' => 'Test notification', 'tag' => 'test']);
$body = web_push_encrypt($sub, $payload, $vapidPem);

// --- parse the aes128gcm body the way a push service / browser would ---
if (strlen($body) < 21) fail('body too short');
$salt = substr($body, 0, 16);
$rs = unpack('N', substr($body, 16, 4))[1];
$idlen = ord($body[20]);
if ($idlen !== 65) fail("unexpected keyid length $idlen");
$asPublicRaw = substr($body, 21, 65);
$ciphertextAndTag = substr($body, 21 + 65);
$ciphertext = substr($ciphertextAndTag, 0, -16);
$tag = substr($ciphertextAndTag, -16);
if ($rs !== 4096) fail("unexpected record size $rs");

// --- browser-side ECDH: shared secret from ua's PRIVATE key + the server's ephemeral public key ---
$asPubKey = openssl_pkey_get_public(ec_p256_public_pem_from_raw($asPublicRaw));
$sharedSecret = openssl_pkey_derive($asPubKey, $uaRes, 32);
if ($sharedSecret === false) fail('browser-side ECDH failed: ' . openssl_error_string());

[$cek, $nonce] = webpush_derive_keys($sharedSecret, $authSecret, $uaPublicRaw, $asPublicRaw, $salt);

$plaintext = openssl_decrypt($ciphertext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
if ($plaintext === false) fail('AES-GCM decrypt failed — keys/nonce derivation mismatch');
if (substr($plaintext, -1) !== "\x02") fail('missing 0x02 record delimiter');
$recovered = substr($plaintext, 0, -1);
if ($recovered !== $payload) fail("payload mismatch:\n  sent: $payload\n  got:  $recovered");

// --- VAPID JWT: check it has 3 dot-separated base64url parts and the raw signature verifies ---
$jwt = vapid_jwt('https://example.com', 'mailto:test@example.com', $vapidPem);
$parts = explode('.', $jwt);
if (count($parts) !== 3) fail('JWT does not have 3 parts');
[$h, $c, $s] = $parts;
$sig = b64u_decode($s);
if (strlen($sig) !== 64) fail('raw ECDSA signature is not 64 bytes (r||s)');
// re-encode raw r||s back to DER and verify with openssl to confirm the conversion is correct
$r = ltrim(substr($sig, 0, 32), "\x00"); $sVal = ltrim(substr($sig, 32, 32), "\x00");
$derInt = fn(string $v) => "\x02" . (ord($v[0]) & 0x80 ? chr(strlen($v) + 1) . "\x00" . $v : chr(strlen($v)) . $v);
$derBody = $derInt($r) . $derInt($sVal);
$der = "\x30" . chr(strlen($derBody)) . $derBody;
$vapidDetails = openssl_pkey_get_details($vapidRes);
$vX = str_pad($vapidDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT);
$vY = str_pad($vapidDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);
$vapidPublicPem = ec_p256_public_pem_from_raw("\x04" . $vX . $vY);
$ok = openssl_verify("$h.$c", $der, openssl_pkey_get_public($vapidPublicPem), OPENSSL_ALGO_SHA256);
if ($ok !== 1) fail('VAPID JWT signature does not verify');

echo "OK — encrypt/decrypt round-trip and VAPID JWT signature both verified.\n";
