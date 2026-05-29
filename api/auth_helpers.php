<?php
/**
 * AUTH_HELPERS.PHP
 * Fonctions partagées entre api_login.php et api_google_login.php
 */

// ── Durée de session : 30 jours ───────────────────────────────
const SESSION_LIFETIME = 60 * 60 * 24 * 30; // 30 jours en secondes

/**
 * Configure et démarre une session longue durée.
 * Si la session est vide mais qu'un remember_token valide existe, restaure la session.
 */
function start_persistent_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

    $savePath = __DIR__ . '/../sessions_data';
    if (!is_dir($savePath)) mkdir($savePath, 0700, true);
    $htaccess = $savePath . '/.htaccess';
    if (!file_exists($htaccess)) file_put_contents($htaccess, "Deny from all\n");
    session_save_path($savePath);

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    if (!isset($_SESSION['user_id'])) {
        // Session vide : tente de restaurer depuis le remember_token cookie
        restore_session_from_token();
    }
}

/**
 * Génère un token persistant, le stocke en DB et pose le cookie.
 * Appelé juste après le login.
 */
function set_remember_token(string $userId): void {
    if (!function_exists('db_execute')) {
        require_once __DIR__ . '/../functions/core_db.php';
    }

    $token     = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires   = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

    // Supprime les anciens tokens de cet utilisateur et les tokens expirés
    db_execute(
        'DELETE FROM remember_tokens WHERE user_id = ? OR expires_at < NOW()',
        [$userId]
    );
    db_execute(
        'INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
        [$userId, $tokenHash, $expires]
    );

    setcookie('remember_token', $token, [
        'expires'  => time() + SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Si un remember_token valide est présent dans les cookies, restaure la session.
 * Renouvelle le token seulement si l'expiration approche (< 15 jours restants)
 * pour éviter les race conditions avec les requêtes AJAX concurrentes.
 */
function restore_session_from_token(): void {
    $token = $_COOKIE['remember_token'] ?? '';
    if (empty($token)) return;

    if (!function_exists('db_fetch_one')) {
        require_once __DIR__ . '/../functions/core_db.php';
    }

    $row = db_fetch_one(
        'SELECT user_id, expires_at FROM remember_tokens WHERE token_hash = ? AND expires_at > NOW()',
        [hash('sha256', $token)]
    );

    if (!$row) {
        // Token inconnu ou expiré — on ne touche pas au cookie ici pour éviter
        // d'effacer un token valide posé par une requête concurrente (race condition)
        return;
    }

    $_SESSION['user_id'] = $row['user_id'];

    // Renouvelle le cookie de session
    setcookie(session_name(), session_id(), [
        'expires'  => time() + SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Renouvelle le remember_token seulement si expiration < 15 jours
    // (évite la race condition : on ne détruit pas le token actif entre deux requêtes)
    $remaining = strtotime($row['expires_at']) - time();
    if ($remaining < 60 * 60 * 24 * 15) {
        set_remember_token($row['user_id']);
    }
}

/**
 * Supprime le remember token de la DB et du cookie.
 * Appelé lors du logout.
 */
function clear_remember_token(): void {
    $token = $_COOKIE['remember_token'] ?? '';
    if (!empty($token)) {
        if (!function_exists('db_execute')) {
            require_once __DIR__ . '/../functions/core_db.php';
        }
        db_execute(
            'DELETE FROM remember_tokens WHERE token_hash = ?',
            [hash('sha256', $token)]
        );
        setcookie('remember_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/**
 * Télécharge et stocke localement l'avatar depuis une URL distante.
 */
function upsert_avatar_from_url(string $uid, ?string $remoteUrl): string {
    $fallback = 'assets/avatars/default-avatar.png';
    if (!$remoteUrl) return $fallback;

    $urlPath = parse_url($remoteUrl, PHP_URL_PATH);
    $ext     = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $ext = 'png';

    $avatarDir     = __DIR__ . '/../assets/avatars/';
    $fullLocalPath = $avatarDir . $uid . '.' . $ext;

    if (!is_dir($avatarDir)) mkdir($avatarDir, 0775, true);

    foreach (['png', 'jpg', 'jpeg', 'gif', 'webp'] as $oldExt) {
        if ($oldExt !== $ext && file_exists($avatarDir . $uid . '.' . $oldExt)) {
            unlink($avatarDir . $uid . '.' . $oldExt);
        }
    }

    $ch = curl_init($remoteUrl);
    $fp = fopen($fullLocalPath, 'wb');
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'MoovieApp/2.0',
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    curl_exec($ch);
    $error    = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if (!$error && $httpCode === 200 && file_exists($fullLocalPath) && filesize($fullLocalPath) > 1024) {
        $imageData = file_get_contents($fullLocalPath);
        if (@imagecreatefromstring($imageData) === false) {
            unlink($fullLocalPath);
            return $fallback;
        }
        return 'assets/avatars/' . $uid . '.' . $ext;
    }

    if (file_exists($fullLocalPath)) unlink($fullLocalPath);
    return $fallback;
}

/**
 * Crée ou met à jour un utilisateur en BDD.
 * Retourne true si l'utilisateur était absent depuis plus de 30 jours.
 */
function upsert_user(string $uid, string $username, ?string $email, string $avatar, string $provider): bool {
    $existing    = db_fetch_one('SELECT last_login FROM users WHERE id = ?', [$uid]);
    $wasInactive = $existing !== null
        && !empty($existing['last_login'])
        && strtotime($existing['last_login']) < strtotime('-30 days');

    db_execute(
        'INSERT INTO users (id, username, email, avatar, provider, created_at, last_login)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
             avatar     = VALUES(avatar),
             last_login = NOW(),
             username   = IF(username = "" OR username IS NULL, VALUES(username), username),
             email      = COALESCE(VALUES(email), email)',
        [$uid, $username, $email, $avatar, $provider]
    );

    return $wasInactive;
}