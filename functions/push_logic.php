<?php
/**
 * FUNCTIONS/PUSH_LOGIC.PHP
 * Web Push notifications — RFC 8030 + RFC 8291 (aes128gcm) + VAPID (RFC 8292)
 * Requires PHP 8.1+ (openssl_pkey_derive) and ext-openssl, ext-curl.
 */

require_once __DIR__ . '/../functions/core_db.php';

// ── Helpers base64url ─────────────────────────────────────────────────────────

function push_b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function push_b64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

// ── HKDF (RFC 5869) ───────────────────────────────────────────────────────────

function push_hkdf(string $salt, string $ikm, string $info, int $length): string {
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $t   = '';
    $okm = '';
    for ($i = 1; strlen($okm) < $length; $i++) {
        $t    = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
        $okm .= $t;
    }
    return substr($okm, 0, $length);
}

// ── EC helpers ────────────────────────────────────────────────────────────────

/**
 * Converts raw EC point coordinates (x, y) to a PEM SubjectPublicKeyInfo.
 * Used to feed the subscription's p256dh key into OpenSSL for ECDH.
 */
function push_ec_point_to_pem(string $x, string $y): string {
    $x = str_pad($x, 32, "\x00", STR_PAD_LEFT);
    $y = str_pad($y, 32, "\x00", STR_PAD_LEFT);

    // SubjectPublicKeyInfo DER for P-256 uncompressed point
    $der = hex2bin('3059')
         . hex2bin('301306072a8648ce3d020106082a8648ce3d030107')
         . "\x03\x42\x00\x04" . $x . $y;

    return "-----BEGIN PUBLIC KEY-----\n"
         . chunk_split(base64_encode($der), 64, "\n")
         . "-----END PUBLIC KEY-----";
}

/**
 * Converts a DER-encoded ECDSA signature to the raw R||S form required by JWT ES256.
 */
function push_der_to_raw_sig(string $der): string {
    $offset = 2;
    if (ord($der[1]) & 0x80) {
        $offset = 2 + (ord($der[1]) & 0x7f);
    }
    $offset++; // skip INTEGER tag for r
    $rLen = ord($der[$offset++]);
    $r    = substr($der, $offset, $rLen);
    $offset += $rLen;
    $offset++; // skip INTEGER tag for s
    $sLen = ord($der[$offset++]);
    $s    = substr($der, $offset, $sLen);

    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    return $r . $s;
}

// ── VAPID keys ────────────────────────────────────────────────────────────────

function get_vapid_keys(): array {
    $path = __DIR__ . '/../config/vapid_keys.php';
    if (!file_exists($path)) {
        throw new RuntimeException('VAPID keys not generated. Run config/generate_vapid.php as admin.');
    }
    return require $path;
}

function get_vapid_public_key(): string {
    try {
        return get_vapid_keys()['public'];
    } catch (Throwable) {
        return '';
    }
}

// ── VAPID JWT ─────────────────────────────────────────────────────────────────

function push_vapid_jwt(string $endpoint, string $subject, string $privateKeyPem): string {
    $parts    = parse_url($endpoint);
    $audience = $parts['scheme'] . '://' . $parts['host'];

    $header  = push_b64url_encode('{"typ":"JWT","alg":"ES256"}');
    $payload = push_b64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200,
        'sub' => $subject,
    ], JSON_UNESCAPED_SLASHES));

    $signingInput = $header . '.' . $payload;
    $privKey      = openssl_pkey_get_private($privateKeyPem);
    openssl_sign($signingInput, $derSig, $privKey, OPENSSL_ALGO_SHA256);

    return $signingInput . '.' . push_b64url_encode(push_der_to_raw_sig($derSig));
}

// ── Payload encryption (RFC 8291 aes128gcm) ───────────────────────────────────

function push_encrypt_payload(string $payload, string $p256dh, string $auth): string {
    if (!function_exists('openssl_pkey_derive')) {
        throw new RuntimeException('openssl_pkey_derive() required (PHP 8.1+)');
    }

    $receiverPub = push_b64url_decode($p256dh); // 65 bytes: 04 || x || y
    $authSecret  = push_b64url_decode($auth);    // 16 bytes

    // Generate salt and local ephemeral key pair
    $salt     = random_bytes(16);
    $localKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $det      = openssl_pkey_get_details($localKey);
    $lx       = str_pad($det['ec']['x'], 32, "\x00", STR_PAD_LEFT);
    $ly       = str_pad($det['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    $localPub = "\x04" . $lx . $ly; // 65 bytes

    // ECDH
    $rx          = substr($receiverPub, 1, 32);
    $ry          = substr($receiverPub, 33, 32);
    $receiverKey = openssl_pkey_get_public(push_ec_point_to_pem($rx, $ry));
    $sharedSec   = openssl_pkey_derive($receiverKey, $localKey, 32);

    // IKM
    $ikmInfo = "WebPush: info\x00" . $receiverPub . $localPub;
    $ikm     = push_hkdf($authSecret, $sharedSec, $ikmInfo, 32);

    // CEK and nonce
    $cek   = push_hkdf($salt, $ikm, "Content-Encoding: aes128gcm\x00", 16);
    $nonce = push_hkdf($salt, $ikm, "Content-Encoding: nonce\x00",     12);

    // Encrypt
    $tag        = '';
    $ciphertext = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

    // aes128gcm header: salt(16) + rs(4) + keyid_len(1) + keyid(65)
    $header = $salt . pack('N', 4096) . chr(65) . $localPub;

    return $header . $ciphertext . $tag;
}

// ── HTTP send ─────────────────────────────────────────────────────────────────

function push_send_one(array $subscription, string $title, string $body, string $url = '/'): bool {
    try {
        $keys    = get_vapid_keys();
        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url], JSON_UNESCAPED_UNICODE);
        $enc     = push_encrypt_payload($payload, $subscription['p256dh'], $subscription['auth']);
        $jwt     = push_vapid_jwt($subscription['endpoint'], $keys['subject'], $keys['private']);

        $ch = curl_init($subscription['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'TTL: 86400',
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'Authorization: vapid t=' . $jwt . ',k=' . $keys['public'],
            ],
            CURLOPT_POSTFIELDS => $enc,
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 410 Gone = subscription expired, remove it
        if ($code === 410 || $code === 404) {
            db_execute('DELETE FROM push_subscriptions WHERE endpoint = ?', [$subscription['endpoint']]);
        }

        return $code >= 200 && $code < 300;
    } catch (Throwable) {
        return false;
    }
}

// ── Public API ────────────────────────────────────────────────────────────────

/**
 * Sends a Web Push notification to all active subscriptions of a user.
 * Called from notifications_logic.php after creating an internal notification.
 */
function send_push_to_user(string $userId, string $title, string $body, string $url = '/'): void {
    $subs = db_fetch_all(
        'SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?',
        [$userId]
    );
    foreach ($subs as $sub) {
        push_send_one($sub, $title, $body, $url);
    }
}
