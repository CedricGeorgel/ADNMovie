<?php
/**
 * MIGRATION : Passage au vote par saison
 * ─────────────────────────────────────────────────────────────────────────────
 * À exécuter UNE SEULE FOIS depuis le terminal ou un navigateur (admin uniquement).
 * CLI : php migrate_season_ratings.php
 * Web : placer à la racine, accéder en étant admin, supprimer ensuite.
 *
 * Ce script :
 *   1. Crée la table `season_ratings`
 *   2. Crée la table `series_dna`
 *   3. Migre les votes existants de `series_ratings` → `season_ratings`
 *      avec season_number = 0 (sentinelle "saison inconnue", à corriger manuellement)
 *   4. Vide `series_ratings` des colonnes devenues inutiles (context_period/slot)
 *      et la reconfigure comme table agrégée calculée
 *   5. Affiche un rapport de migration
 */

// ── Bootstrap ──────────────────────────────────────────────────────────────
define('CLI_MODE', php_sapi_name() === 'cli');

if (!CLI_MODE) {
    // Protection web minimale : réserver aux admins
    session_start();
    require_once __DIR__ . '/config/settings.php';
    require_once __DIR__ . '/functions/core_db.php';
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        die('Accès refusé.');
    }
    $me = db_fetch_one('SELECT role FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if (($me['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('Réservé aux administrateurs.');
    }
} else {
    $rootDir = dirname(__FILE__);
    require_once $rootDir . '/config/settings.php';
    require_once $rootDir . '/functions/core_db.php';
}

// ── Helpers affichage ──────────────────────────────────────────────────────
function out(string $msg, string $level = 'info'): void {
    $icons = ['info' => 'ℹ', 'ok' => '✅', 'warn' => '⚠️', 'err' => '❌', 'section' => '══'];
    $icon  = $icons[$level] ?? '·';
    if (CLI_MODE) {
        echo $icon . ' ' . $msg . "\n";
    } else {
        $color = match($level) {
            'ok'      => '#4CAF82',
            'warn'    => '#F4A94A',
            'err'     => '#FA6B6B',
            'section' => '#7EB8F7',
            default   => '#ccc',
        };
        echo "<p style='color:{$color};font-family:monospace;margin:2px 0;'>{$icon} " . htmlspecialchars($msg) . "</p>\n";
        flush();
    }
}

if (!CLI_MODE) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Migration ADNMovie</title></head>";
    echo "<body style='background:#0d0d0d;padding:30px;'>";
    echo "<h2 style='color:#fff;font-family:monospace;'>Migration — Vote par saison</h2>\n";
}

$pdo = get_db_connection();

// ════════════════════════════════════════════════════════════════════════════
// ÉTAPE 1 — Création de `season_ratings`
// ════════════════════════════════════════════════════════════════════════════
out('ÉTAPE 1 — Création de season_ratings', 'section');

$pdo->exec("
CREATE TABLE IF NOT EXISTS `season_ratings` (
  `id`             INT            NOT NULL AUTO_INCREMENT,
  `user_id`        VARCHAR(100)   NOT NULL,
  `series_id`      INT            NOT NULL,
  `season_number`  SMALLINT UNSIGNED NOT NULL,
  `scores`         JSON           DEFAULT NULL,
  `like_score`     INT            DEFAULT NULL,
  `is_liked`       TINYINT(1)     DEFAULT 0,
  `rating_weight`  FLOAT          DEFAULT 0,
  `context_period` VARCHAR(50)    DEFAULT NULL,
  `context_slot`   VARCHAR(50)    DEFAULT NULL,
  `rated_at`       TIMESTAMP      NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_season` (`user_id`, `series_id`, `season_number`),
  KEY `idx_series`  (`series_id`),
  KEY `idx_user`    (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

out('Table season_ratings créée (ou déjà existante)', 'ok');

// ════════════════════════════════════════════════════════════════════════════
// ÉTAPE 2 — Création de `series_dna`
// ════════════════════════════════════════════════════════════════════════════
out('ÉTAPE 2 — Création de series_dna', 'section');

$pdo->exec("
CREATE TABLE IF NOT EXISTS `series_dna` (
  `series_id`   INT           NOT NULL,
  `criterio`    VARCHAR(50)   NOT NULL,
  `avg_score`   FLOAT         DEFAULT 0,
  `variance`    FLOAT         DEFAULT 0,
  `sample_size` INT           DEFAULT 0,
  PRIMARY KEY (`series_id`, `criterio`),
  CONSTRAINT `series_dna_ibfk_1`
    FOREIGN KEY (`series_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

out('Table series_dna créée (ou déjà existante)', 'ok');

// ════════════════════════════════════════════════════════════════════════════
// ÉTAPE 3 — Migration series_ratings → season_ratings
// ════════════════════════════════════════════════════════════════════════════
out('ÉTAPE 3 — Migration des votes existants', 'section');

$existing = $pdo->query('SELECT COUNT(*) FROM series_ratings')->fetchColumn();
out("Votes trouvés dans series_ratings : {$existing}");

if ($existing > 0) {
    // Vérifier si la migration a déjà été faite partiellement
    $alreadyMigrated = $pdo->query('SELECT COUNT(*) FROM season_ratings')->fetchColumn();
    if ($alreadyMigrated > 0) {
        out("season_ratings contient déjà {$alreadyMigrated} ligne(s) — migration ignorée pour éviter les doublons.", 'warn');
        out("Supprimer manuellement season_ratings si vous voulez relancer.", 'warn');
    } else {
        // Migrer : season_number = 0 = sentinelle "saison inconnue"
        // Le champ context_period/context_slot n'existe pas dans l'ancienne table → NULL
        $migrated = $pdo->exec("
            INSERT INTO season_ratings
                (user_id, series_id, season_number, scores, like_score, is_liked, rating_weight, rated_at)
            SELECT
                user_id,
                series_id,
                0          AS season_number,
                scores,
                like_score,
                is_liked,
                rating_weight,
                rated_at
            FROM series_ratings
        ");
        out("{$migrated} vote(s) migré(s) avec season_number = 0 (saison inconnue).", 'ok');
        out("Pensez à corriger manuellement season_number = 0 pour vos votes personnels.", 'warn');
    }
} else {
    out('Aucun vote à migrer.', 'ok');
}

// ════════════════════════════════════════════════════════════════════════════
// ÉTAPE 4 — Reconfiguration de series_ratings comme table agrégée
// ════════════════════════════════════════════════════════════════════════════
out('ÉTAPE 4 — Reconfiguration de series_ratings', 'section');

// Ajouter les colonnes context_period/slot si absentes (pour uniformité)
try {
    $pdo->exec("ALTER TABLE series_ratings ADD COLUMN `context_period` VARCHAR(50) DEFAULT NULL");
    out("Colonne context_period ajoutée à series_ratings", 'ok');
} catch (\PDOException $e) {
    out("context_period déjà présente dans series_ratings", 'info');
}

try {
    $pdo->exec("ALTER TABLE series_ratings ADD COLUMN `context_slot` VARCHAR(50) DEFAULT NULL");
    out("Colonne context_slot ajoutée à series_ratings", 'ok');
} catch (\PDOException $e) {
    out("context_slot déjà présente dans series_ratings", 'info');
}

out("series_ratings conservée comme table agrégée (recalculée après chaque vote de saison).", 'ok');

// ════════════════════════════════════════════════════════════════════════════
// ÉTAPE 5 — Rapport final
// ════════════════════════════════════════════════════════════════════════════
out('ÉTAPE 5 — Rapport', 'section');

$countSR  = $pdo->query('SELECT COUNT(*) FROM season_ratings')->fetchColumn();
$countSDna = $pdo->query('SELECT COUNT(*) FROM series_dna')->fetchColumn();

out("season_ratings : {$countSR} ligne(s)", 'ok');
out("series_dna     : {$countSDna} ligne(s) (vide, sera peuplée par les prochains votes)", 'ok');
out("Migration terminée.", 'ok');
out("IMPORTANT : Supprimer ce fichier après exécution !", 'warn');

if (!CLI_MODE) {
    echo "</body></html>";
}
