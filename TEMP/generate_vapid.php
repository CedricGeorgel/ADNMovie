<?php
/**
 * CONFIG/GENERATE_VAPID.PHP
 * Script one-shot — génère une paire de clés VAPID P-256
 * et écrit config/vapid_keys.php.
 *
 * Usage : php config/generate_vapid.php
 */

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../functions/utils.php';
    require_once __DIR__ . '/../functions/auth.php';
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!has_role('superadmin')) {
        http_response_code(403); die('403 Forbidden');
    }
}

$outputPath = __DIR__ . '/vapid_keys.php';
if (file_exists($outputPath)) {
    echo "VAPID keys already exist. Delete config/vapid_keys.php to regenerate.\n";
    exit(0);
}

// Generate P-256 EC key pair
$key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
if (!$key) {
    echo 'openssl_pkey_new() failed: ' . openssl_error_string() . "\n";
    exit(1);
}

$det = openssl_pkey_get_details($key);
$x   = str_pad($det['ec']['x'], 32, "\x00", STR_PAD_LEFT);
$y   = str_pad($det['ec']['y'], 32, "\x00", STR_PAD_LEFT);

// base64url-encode public key (uncompressed point 04||x||y)
$publicRaw = "\x04" . $x . $y;
$publicB64 = rtrim(strtr(base64_encode($publicRaw), '+/', '-_'), '=');

// Export private key as PEM
openssl_pkey_export($key, $privatePem);

$generated = date('Y-m-d H:i:s');
$privExport = var_export($privatePem, true);

$content = <<<PHP
<?php
/**
 * VAPID keys — generated {$generated} — DO NOT COMMIT
 */
return [
    'public'  => '{$publicB64}',
    'private' => {$privExport},
    'subject' => 'mailto:contact@adnmovie.fr',
];
PHP;

file_put_contents($outputPath, $content);

$msg = "VAPID keys generated.\nPublic key : {$publicB64}";
if (PHP_SAPI === 'cli') {
    echo $msg . "\n";
} else {
    echo '<pre style="font-family:monospace;padding:20px;">' . htmlspecialchars($msg) . '</pre>';
}
