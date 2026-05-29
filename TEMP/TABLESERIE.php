<?php
/**
 * INIT_TABLES.PHP
 * ─────────────────────────────────────────────────────────────────────────────
 * Initialisation des tables manquantes pour la migration des séries par saison.
 */

// ── FORÇAGE AFFICHAGE ERREURS (DÉBOGAGE) ─────────────────────────────────────
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('CLI_MODE', php_sapi_name() === 'cli');

$rootDir = CLI_MODE ? dirname(__FILE__) : __DIR__;

// Vérification de la présence des fichiers requis avant inclusion
if (!file_exists($rootDir . '/config/settings.php') || !file_exists($rootDir . '/functions/core_db.php')) {
    http_response_code(500);
    die("Erreur critique : Fichiers de configuration ou de base de données introuvables. Vérifiez que ce script est à la racine du projet.");
}

require_once $rootDir . '/config/settings.php';
require_once $rootDir . '/functions/core_db.php';

// ── Protection web ────────────────────────────────────────────────────────────
if (!CLI_MODE) {
    session_start();
    if (!isset($_SESSION['user_id'])) { 
        http_response_code(403); 
        die('Accès refusé. Connectez-vous d\'abord.'); 
    }
    
    // On suppose que la fonction db_fetch_one existe, puisqu'elle est dans tes autres scripts
    $me = db_fetch_one('SELECT role FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if (($me['role'] ?? '') !== 'admin') { 
        http_response_code(403); 
        die('Réservé aux admins.'); 
    }
    
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Initialisation Tables</title></head>";
    echo "<body style='background:#0d0d0d;padding:30px;font-family:monospace;color:#aaa;'>";
    echo "<h2 style='color:#fff;'>Initialisation des tables SQL</h2>\n";
}

function log_msg(string $msg, bool $is_ok = true): void {
    $prefix = $is_ok ? '[✓]' : '[✗]';
    $color = $is_ok ? '#4CAF82' : '#FA6B6B';
    if (CLI_MODE) {
        echo $prefix . ' ' . $msg . "\n";
    } else {
        echo "<p style='color:{$color};margin:5px 0;'>{$prefix} " . htmlspecialchars($msg) . "</p>\n";
        flush();
    }
}

try {
    // 1. Table des notations par saison
    $sql1 = "CREATE TABLE IF NOT EXISTS `season_ratings` (
      `id` int NOT NULL AUTO_INCREMENT,
      `user_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
      `series_id` int NOT NULL,
      `season_number` smallint unsigned NOT NULL,
      `scores` json DEFAULT NULL,
      `like_score` int DEFAULT NULL,
      `is_liked` tinyint(1) DEFAULT '0',
      `rating_weight` float DEFAULT '0',
      `rated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_user_season` (`user_id`,`series_id`,`season_number`),
      CONSTRAINT `fk_season_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    db_execute($sql1, []);
    log_msg("Table 'season_ratings' vérifiée/créée.");

    // 2. Table pour stocker les métadonnées des saisons localement
    $sql2 = "CREATE TABLE IF NOT EXISTS `series_seasons` (
      `series_id` int NOT NULL,
      `season_number` smallint unsigned NOT NULL,
      `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `episode_count` smallint unsigned DEFAULT '0',
      `poster_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `overview` text COLLATE utf8mb4_unicode_ci,
      `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`series_id`,`season_number`),
      CONSTRAINT `fk_series_seasons_ref` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    db_execute($sql2, []);
    log_msg("Table 'series_seasons' vérifiée/créée.");
    
    log_msg("Migration de la structure terminée avec succès.");

} catch (Exception $e) {
    log_msg("Erreur d'exécution SQL : " . $e->getMessage(), false);
} catch (Error $e) {
    // Capture les erreurs fatales PHP (ex: fonction db_execute non trouvée)
    log_msg("Erreur système PHP : " . $e->getMessage(), false);
}

if (!CLI_MODE) echo "</body></html>";