<?php
/**
 * AUTH.PHP
 */

require_once __DIR__ . '/../functions/core_db.php';

/**
 * Redirige vers l'accueil si l'utilisateur n'est pas connecté.
 */
function check_auth(): void {
    // start_persistent_session() a déjà tourné (via utils.php) et tenté la restauration
    // par cookie. Si user_id est là, on est connecté.
    if (isset($_SESSION['user_id'])) return;

    // Session morte et cookie absent — laisse JS tenter le fallback localStorage (iOS PWA)
    // avant de rediriger, pour éviter la déconnexion silencieuse sur mobile
    $return = json_encode($_SERVER['REQUEST_URI'] ?? 'index.php');
    ?><!DOCTYPE html><html lang="fr"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reconnexion…</title>
    <style>html,body{margin:0;background:#0a0a0a;display:flex;align-items:center;justify-content:center;height:100vh;color:#444;font-family:monospace;font-size:0.75rem;letter-spacing:1px;}</style>
    </head><body><span>reconnexion…</span>
    <script>
    (async function() {
        try {
            var t = localStorage.getItem('_pwa_token');
            if (!t) { location.replace('index.php'); return; }
            var r = await fetch('api/api_restore_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_pwa_token=' + encodeURIComponent(t)
            });
            var d = await r.json();
            if (d.success) { location.replace(<?= $return ?>); }
            else { localStorage.removeItem('_pwa_token'); location.replace('index.php'); }
        } catch(e) { location.replace('index.php'); }
    })();
    </script>
    </body></html><?php
    exit;
}

/**
 * Charge le profil complet d'un utilisateur depuis la BDD.
 * Remplace : json_decode(file_get_contents("bdd/users/$userId.json"))
 *
 * @param string $userId
 * @return array|null
 */
function get_user_by_id($userId) {
    if (!$userId) return null;
    return db_fetch_one(
        'SELECT * FROM users WHERE id = ?',
        [$userId]
    );
}

/**
 * Charge l'annuaire léger de tous les utilisateurs (id, username, avatar).
 * Remplace : json_decode(file_get_contents("bdd/users_list.json"))
 *
 * @return array  Tableau indexé par user_id : ['id' => ..., 'username' => ..., 'avatar' => ...]
 */
function get_users_directory() {
    $rows = db_fetch_all('SELECT id, username, avatar FROM users WHERE is_anonymized = 0');

    // On réindexe par user_id pour conserver le même comportement qu'avant
    $directory = [];
    foreach ($rows as $row) {
        $directory[$row['id']] = $row;
    }
    return $directory;
}

/**
 * Vérifie si un utilisateur possède un badge spécifique.
 * Remplace : in_array($badgeName, $user['badges'])
 *
 * @param string $userId
 * @param string $badgeName
 * @return bool
 */
function has_badge($userId, string $badgeName): bool {
    if (!$userId) return false;
    $row = db_fetch_one(
        'SELECT 1 FROM user_badges WHERE user_id = ? AND badge_id = ?',
        [$userId, $badgeName]
    );
    return $row !== null;
}

/**
 * Retourne tous les badges d'un utilisateur.
 *
 * @param string $userId
 * @return array  Liste de badge_id
 */
function get_user_badges($userId): array {
    if (!$userId) return [];
    $rows = db_fetch_all(
        'SELECT badge_id, earned_at FROM user_badges WHERE user_id = ? ORDER BY earned_at ASC',
        [$userId]
    );
    return array_column($rows, null, 'badge_id');
}

/**
 * Détruit la session et déconnecte l'utilisateur.
 */
function logout() {
    // On garantit l'accès au moteur de session, peu importe d'où on appelle la fonction
    require_once __DIR__ . '/../api/auth_helpers.php';
    
    // On charge la session de 30 jours pour pouvoir la détruire
    start_persistent_session();

    clear_remember_token();

    $_SESSION = [];
    session_destroy();

    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    
    header('Location: index.php');
    exit;
}