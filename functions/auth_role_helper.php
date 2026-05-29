<?php
/**
 * FUNCTIONS/AUTH_ROLE_HELPER.PHP
 * Fonctions de vérification des rôles basées sur le champ `role` en BDD.
 *
 * Hiérarchie des rôles (du plus faible au plus fort) :
 *   user < moderator < admin < superadmin
 *
 * Usage :
 *   require_role('admin');           // Arrête avec 403 si pas admin+
 *   if (has_role('moderator')) ...   // Check booléen sans arrêter
 *   current_role()                   // Retourne le rôle actuel
 */

const ROLE_HIERARCHY = ['user' => 0, 'moderator' => 1, 'admin' => 2, 'superadmin' => 3];

// Configuration visuelle par rôle
const ROLE_CONFIG = [
    'user'       => ['label' => 'Spécimen',       'color' => '#A7C7E7', 'bg' => 'rgba(167,199,231,0.12)', 'border' => 'rgba(167,199,231,0.35)'],
    'moderator'  => ['label' => 'Modérateur',     'color' => '#6ee7b7', 'bg' => 'rgba(110,231,183,0.12)', 'border' => 'rgba(110,231,183,0.35)'],
    'admin'      => ['label' => 'Administrateur', 'color' => '#f87171', 'bg' => 'rgba(248,113,113,0.12)', 'border' => 'rgba(248,113,113,0.35)'],
    'superadmin' => ['label' => 'Administrateur', 'color' => '#f87171', 'bg' => 'rgba(248,113,113,0.12)', 'border' => 'rgba(248,113,113,0.35)'],
];

/**
 * Retourne le rôle de l'utilisateur connecté.
 */
function current_role(): string {
    if (!isset($_SESSION['user_id'])) return 'guest';

    if (isset($_SESSION['user_role'])) return $_SESSION['user_role'];

    $row = db_fetch_one('SELECT role FROM users WHERE id = ?', [$_SESSION['user_id']]);
    $role = $row['role'] ?? 'user';

    $_SESSION['user_role'] = $role;
    return $role;
}

/**
 * Vérifie si l'utilisateur connecté a au moins le rôle demandé.
 */
function has_role(string $minRole): bool {
    $current      = current_role();
    $currentLevel  = ROLE_HIERARCHY[$current]  ?? 0;
    $requiredLevel = ROLE_HIERARCHY[$minRole]   ?? 0;
    return $currentLevel >= $requiredLevel;
}

/**
 * Exige un rôle minimum. Logue la tentative et arrête avec 403 si insuffisant.
 */
function require_role(string $minRole): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php'); exit;
    }
    if (!has_role($minRole)) {
        _log_access_attempt($minRole);
        http_response_code(403);
        die('<div style="font-family:monospace;background:#0a0a0f;color:#f87171;padding:60px;text-align:center;">
            <h2>Accès refusé</h2>
            <p style="color:#64748b;">Rôle requis : ' . htmlspecialchars($minRole) . '</p>
            <a href="index.php" style="color:#A7C7E7;">← Retour</a>
        </div>');
    }
}

/**
 * Invalide le cache du rôle en session.
 * À appeler après un changement de rôle en BDD.
 */
function invalidate_role_cache(): void {
    unset($_SESSION['user_role']);
}

/**
 * Retourne le HTML du badge de rôle (Spécimen / Modérateur / Administrateur).
 */
function render_role_badge(string $role): string {
    $cfg = ROLE_CONFIG[$role] ?? ROLE_CONFIG['user'];
    return '<span style="background:' . $cfg['bg'] . ';color:' . $cfg['color'] . ';border:1px solid ' . $cfg['border'] . ';padding:4px 10px;border-radius:6px;font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;">'
         . htmlspecialchars($cfg['label'])
         . '</span>';
}

/**
 * Retourne le HTML du bouclier de rôle (admin/modérateur) à afficher inline.
 * Vide pour les utilisateurs simples.
 */
function role_shield_html(string $role): string {
    if ($role === 'admin' || $role === 'superadmin') {
        return '<span title="Administrateur" style="color:#f87171;font-size:0.75em;margin-left:4px;line-height:1;vertical-align:middle;">🛡</span>';
    }
    if ($role === 'moderator') {
        return '<span title="Modérateur" style="color:#6ee7b7;font-size:0.75em;margin-left:4px;line-height:1;vertical-align:middle;">🛡</span>';
    }
    return '';
}

/**
 * Logue une tentative d'accès non autorisée d'un utilisateur connecté.
 */
function _log_access_attempt(string $requiredRole): void {
    if (!function_exists('db_execute') || !function_exists('db_fetch_all')) return;
    $userId = $_SESSION['user_id'] ?? null;
    $page   = basename($_SERVER['SCRIPT_NAME'] ?? 'inconnu');
    $ip     = $_SERVER['REMOTE_ADDR'] ?? null;
    try {
        db_execute(
            'INSERT INTO access_attempts (user_id, page, required_role, ip, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$userId, $page, $requiredRole, $ip]
        );
        // Notification aux admins
        $admins = db_fetch_all("SELECT id FROM users WHERE role IN ('admin','superadmin')");
        foreach ($admins as $admin) {
            if ($admin['id'] === $userId) continue;
            db_execute(
                "INSERT INTO notifications (user_id, type, source_id, title, link, is_read, created_at)
                 VALUES (?, 'report', ?, ?, 'admin.php', 0, NOW())",
                [$admin['id'], $page . '_' . time(), "⛔ Accès bloqué : {$page} (rôle requis : {$requiredRole})"]
            );
        }
    } catch (\Throwable $e) {
        error_log('access_attempt log failed: ' . $e->getMessage());
    }
}
