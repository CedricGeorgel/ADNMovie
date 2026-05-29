<?php
/**
 * ORACLE_RENOTATE_SERIES.PHP (Version API Asynchrone v3)
 * ─────────────────────────────────────────────────────────────────────────────
 * Initialise les tables manquantes (y compris series_dna).
 * Traite par lots avec gestion des erreurs (rollback automatique).
 * Intègre un mode "Purger et recommencer" pour un contrôle total.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 300);

define('CLI_MODE', php_sapi_name() === 'cli');
$rootDir = CLI_MODE ? dirname(__FILE__) : __DIR__;

require_once $rootDir . '/config/settings.php';
require_once $rootDir . '/functions/core_db.php';
require_once $rootDir . '/api/api_oracle.php';
require_once $rootDir . '/functions/api_tmdb.php';

if (!defined('ORACLE_USER_ID')) define('ORACLE_USER_ID', 'IA-ORACLE-001');
if (!defined('ORACLE_WEIGHT'))  define('ORACLE_WEIGHT', 0.1);

// ── Authentification ──────────────────────────────────────────────────────────
if (!CLI_MODE) {
    session_start();
    if (!isset($_SESSION['user_id'])) { http_response_code(403); die('Accès refusé.'); }
    $me = db_fetch_one('SELECT role FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if (($me['role'] ?? '') !== 'admin') { http_response_code(403); die('Réservé aux admins.'); }
}

// ── Mécanisme de Logging ──────────────────────────────────────────────────────
global $apiLogs;
$apiLogs = [];
define('API_MODE', isset($_GET['action']));

function out(string $msg, string $level = 'info'): void {
    global $apiLogs;
    if (API_MODE) {
        $apiLogs[] = ['level' => $level, 'msg' => $msg];
        return;
    }
    if (CLI_MODE) {
        $icons = ['info' => '·', 'ok' => '✓', 'warn' => '⚠', 'err' => '✗', 'section' => '▶', 'skip' => '○'];
        echo ($icons[$level] ?? '·') . ' ' . $msg . "\n";
    }
}

// ── INITIALISATION PDO & STRUCTURE ────────────────────────────────────────────
try {
    $pdo = getPDO();
    _oracle_ensure_user($pdo);
    
    // Provisionnement strict des tables nécessaires
    $pdo->exec("CREATE TABLE IF NOT EXISTS `season_ratings` (
      `id` int NOT NULL AUTO_INCREMENT, `user_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
      `series_id` int NOT NULL, `season_number` smallint unsigned NOT NULL,
      `scores` json DEFAULT NULL, `like_score` int DEFAULT NULL, `is_liked` tinyint(1) DEFAULT '0',
      `rating_weight` float DEFAULT '0', `rated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`), UNIQUE KEY `unique_user_season` (`user_id`,`series_id`,`season_number`),
      CONSTRAINT `fk_season_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `series_seasons` (
      `series_id` int NOT NULL, `season_number` smallint unsigned NOT NULL,
      `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL, `episode_count` smallint unsigned DEFAULT '0',
      `poster_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL, `overview` text COLLATE utf8mb4_unicode_ci,
      `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`series_id`,`season_number`),
      CONSTRAINT `fk_series_seasons_ref` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // L'absente responsable du crash
    $pdo->exec("CREATE TABLE IF NOT EXISTS `series_dna` (
      `series_id` int NOT NULL,
      `criterio` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
      `avg_score` float DEFAULT '0',
      `variance` float DEFAULT '0',
      `sample_size` int DEFAULT '0',
      PRIMARY KEY (`series_id`,`criterio`),
      CONSTRAINT `fk_series_dna_ref` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    
} catch (Exception $e) {
    if (API_MODE) { echo json_encode(['error' => $e->getMessage()]); exit; }
    die("Erreur SQL initiale : " . $e->getMessage());
}

// ══════════════════════════════════════════════════════════════════════════════
// ROUTEUR API (AJAX)
// ══════════════════════════════════════════════════════════════════════════════
if (API_MODE) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    // ── Purge Totale (Remise à 0) ─────────────
    if ($action === 'reset') {
        db_execute('DELETE FROM season_ratings WHERE user_id = ?', [ORACLE_USER_ID]);
        db_execute('DELETE FROM series_ratings WHERE user_id = ?', [ORACLE_USER_ID]);
        $pdo->exec('TRUNCATE TABLE series_dna');
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // ── Statut global ─────────────────────────
    if ($action === 'status') {
        $total = (int)(db_fetch_one('SELECT COUNT(*) as c FROM series')['c'] ?? 0);
        $remaining = (int)(db_fetch_one(
            'SELECT COUNT(s.id) as c FROM series s
             LEFT JOIN season_ratings sr ON s.id = sr.series_id AND sr.user_id = ?
             WHERE sr.id IS NULL', [ORACLE_USER_ID]
        )['c'] ?? 0);
        echo json_encode(['total' => $total, 'remaining' => $remaining, 'done' => $total - $remaining]);
        exit;
    }

    // ── Traitement d'un lot ───────────────────
    if ($action === 'process') {
        $dryRun = isset($_GET['dry-run']) && $_GET['dry-run'] == '1';
        if ($dryRun) out('MODE DRY-RUN — aucune écriture', 'warn');

        $series = db_fetch_all(
            'SELECT s.id, s.tmdb_id, s.title, s.total_seasons, s.genres 
             FROM series s
             LEFT JOIN season_ratings sr ON s.id = sr.series_id AND sr.user_id = ?
             WHERE sr.id IS NULL
             ORDER BY s.id LIMIT 20',
            [ORACLE_USER_ID]
        );

        $totalBatch = count($series);
        $errors = 0;

        if ($totalBatch === 0) {
            echo json_encode(['status' => 'finished', 'logs' => [['level'=>'ok', 'msg'=>"🎉 Migration 100% terminée !"]], 'processed' => 0]);
            exit;
        }

        out("Traitement du lot : {$totalBatch} séries", 'section');

        foreach ($series as $serie) {
            $seriesId    = (int)$serie['id'];
            $tmdbId      = (int)$serie['tmdb_id'];
            $title       = $serie['title'];
            $localTotalSeasons = (int)$serie['total_seasons']; 
            
            $rawGenres   = json_decode($serie['genres'] ?? '[]', true) ?: [];
            $genres      = array_values(array_filter(array_map(fn($g) => is_array($g) ? ($g['name'] ?? '') : $g, $rawGenres)));

            out("── {$title} (tmdb:{$tmdbId})", 'section');

            $tmdbData = null;
            $totalSeasons = $localTotalSeasons;

            if (!$dryRun) {
                $tmdbData = fetch_tmdb_series($tmdbId);
                if ($tmdbData) {
                    $totalSeasons = (int)($tmdbData['total_seasons'] ?? 0);
                    if ($totalSeasons !== $localTotalSeasons) {
                        db_execute('UPDATE series SET total_seasons = ? WHERE id = ?', [$totalSeasons, $seriesId]);
                        out("  Correction BDD : {$localTotalSeasons} -> {$totalSeasons} saison(s)", 'info');
                    }

                    if (!empty($tmdbData['seasons'])) {
                        $seasonsInserted = 0;
                        foreach ($tmdbData['seasons'] as $s) {
                            try {
                                db_execute(
                                    'INSERT INTO series_seasons (series_id, season_number, name, episode_count, poster_path, overview)
                                     VALUES (?, ?, ?, ?, ?, ?)
                                     ON DUPLICATE KEY UPDATE name = VALUES(name), episode_count = VALUES(episode_count), poster_path = VALUES(poster_path), overview = VALUES(overview)',
                                    [$seriesId, $s['season_number'], $s['name'], $s['episode_count'], $s['poster_path'], $s['overview']]
                                );
                                $seasonsInserted++;
                            } catch (Exception $e) {}
                        }
                    }
                } else {
                    out("  Impossible de récupérer les données TMDB.", 'warn');
                }
            }

            if ($totalSeasons <= 0) {
                out("  0 saison confirmée, skip définitif.", 'skip');
                if (!$dryRun) {
                    try { db_execute('INSERT IGNORE INTO season_ratings (user_id, series_id, season_number, scores, is_liked) VALUES (?, ?, 0, "{}", 0)', [ORACLE_USER_ID, $seriesId]); } catch (Exception $e) {}
                }
                continue;
            }

            if (!$dryRun) {
                db_execute('DELETE FROM season_ratings WHERE user_id = ? AND series_id = ?', [ORACLE_USER_ID, $seriesId]);
                db_execute('DELETE FROM series_ratings WHERE user_id = ? AND series_id = ?', [ORACLE_USER_ID, $seriesId]);
            }

            try {
                $keywords   = oracle_fetch_keywords($tmdbId, 'tv');
                $baseScores = oracle_compute_scores($keywords, $genres);
            } catch (Exception $e) {
                out("  Erreur calcul Oracle : " . $e->getMessage(), 'err');
                $errors++; continue;
            }

            $seasonsDone = 0;
            for ($sn = 1; $sn <= $totalSeasons; $sn++) {
                usleep(110000); 
                $seasonData = _oracle_fetch_season_tmdb($tmdbId, $sn);
                if (empty($seasonData)) continue;

                $voteAvg   = isset($seasonData['vote_average']) ? (float)$seasonData['vote_average'] : null;
                $voteCount = (int)($seasonData['vote_count'] ?? 0);
                $likeScore = _oracle_vote_to_like_score($voteAvg, $voteCount);
                $isLiked   = ($likeScore !== null && $likeScore > 0) ? 1 : 0;

                $seasonScores = [];
                foreach ($baseScores as $c => $v) $seasonScores[$c] = round(max(-10, min(10, $v + (mt_rand(-3, 3) / 10))), 1);

                if (!$dryRun) {
                    try {
                        db_execute('INSERT INTO season_ratings (user_id, series_id, season_number, scores, like_score, is_liked, rating_weight, rated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                            [ORACLE_USER_ID, $seriesId, $sn, json_encode($seasonScores), $likeScore, $isLiked, ORACLE_WEIGHT]
                        );
                        $seasonsDone++;
                    } catch (Exception $e) { out("  S{$sn} : échec INSERT", 'err'); }
                }
            }

            if (!$dryRun && $seasonsDone > 0) {
                try {
                    _oracle_recalc_series_aggregate($pdo, ORACLE_USER_ID, $seriesId);
                    _oracle_update_series_dna($pdo, $seriesId);
                    
                    $vCount = (int)(db_fetch_one('SELECT COUNT(DISTINCT user_id) AS cnt FROM season_ratings WHERE series_id = ? AND scores IS NOT NULL', [$seriesId])['cnt'] ?? 0);
                    db_execute('UPDATE series SET total_votes = ? WHERE id = ?', [$vCount, $seriesId]);
                    
                    out("  → {$seasonsDone} saison(s) notée(s), ADN agrégé avec succès", 'ok');
                } catch (Exception $e) { 
                    out("  Erreur agrégat : " . $e->getMessage(), 'err'); 
                    // Rollback de sécurité : on efface les notes de la série plantée pour qu'elle soit reprise
                    db_execute('DELETE FROM season_ratings WHERE user_id = ? AND series_id = ?', [ORACLE_USER_ID, $seriesId]);
                    $errors++; 
                }
            }
        }

        echo json_encode([
            'status' => 'continue',
            'logs' => $apiLogs,
            'processed' => $totalBatch,
            'errors' => $errors
        ]);
        exit;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// INTERFACE UTILISATEUR (HTML / JS)
// ══════════════════════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Oracle — Migration des Séries</title>
    <style>
        :root {
            --bg: #0d0d0d; --panel: #1a1a1a; --text: #eaeaea; --dim: #888;
            --accent: #7EB8F7; --danger: #FA6B6B; --success: #4CAF82; --warn: #F4A94A;
        }
        body { background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace; margin: 0; padding: 40px; display: flex; flex-direction: column; align-items: center; }
        .container { max-width: 900px; width: 100%; }
        h1 { font-weight: 500; font-size: 1.5rem; border-bottom: 1px solid #333; padding-bottom: 15px; margin-bottom: 30px; }
        
        .controls { display: flex; gap: 15px; margin-bottom: 30px; align-items: center; flex-wrap: wrap; }
        button { background: #333; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-family: inherit; font-size: 0.9rem; cursor: pointer; transition: all 0.2s; }
        button:hover { background: #444; }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        button.btn-primary { background: var(--accent); color: #000; font-weight: 600; }
        button.btn-primary:hover:not(:disabled) { background: #6da2e0; }
        button.btn-danger { background: var(--danger); color: #fff; }
        button.btn-danger:hover:not(:disabled) { background: #e05e5e; }
        button.btn-outline { background: transparent; border: 1px solid #555; color: #aaa; }
        button.btn-outline:hover:not(:disabled) { background: rgba(255,0,0,0.1); border-color: var(--danger); color: var(--danger); }

        .progress-wrapper { background: var(--panel); border: 1px solid #333; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .stats { display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--dim); margin-bottom: 10px; }
        .progress-bar { width: 100%; height: 8px; background: #222; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: var(--accent); width: 0%; transition: width 0.3s ease; }

        .console { background: #000; border: 1px solid #222; border-radius: 8px; height: 500px; overflow-y: auto; padding: 15px; font-family: 'Courier New', Courier, monospace; font-size: 0.8rem; line-height: 1.5; color: #ccc; scroll-behavior: smooth; }
        .log-info { color: #aaa; }
        .log-ok { color: var(--success); }
        .log-warn { color: var(--warn); }
        .log-err { color: var(--danger); }
        .log-section { color: var(--accent); font-weight: bold; margin-top: 10px; }
        .log-skip { color: #555; }
    </style>
</head>
<body>

<div class="container">
    <h1>Oracle — Protocole de migration des séries</h1>

    <div class="progress-wrapper">
        <div class="stats">
            <span id="stat-text">Initialisation...</span>
            <span id="stat-pct">0%</span>
        </div>
        <div class="progress-bar"><div class="progress-fill" id="p-fill"></div></div>
    </div>

    <div class="controls">
        <button id="btn-start" class="btn-primary" disabled>▶ Lancer</button>
        <button id="btn-stop" class="btn-danger" disabled>⏸ Pauser</button>
        <label style="font-size: 0.85rem; color: var(--dim); display: flex; align-items: center; gap: 8px; margin-right:auto;">
            <input type="checkbox" id="chk-dryrun"> Simulation
        </label>
        <button id="btn-reset" class="btn-outline">⚠ Purger et Recommencer de 0</button>
    </div>

    <div class="console" id="console"></div>
</div>

<script>
    const btnStart = document.getElementById('btn-start');
    const btnStop = document.getElementById('btn-stop');
    const btnReset = document.getElementById('btn-reset');
    const chkDryRun = document.getElementById('chk-dryrun');
    const pFill = document.getElementById('p-fill');
    const statText = document.getElementById('stat-text');
    const statPct = document.getElementById('stat-pct');
    const consoleEl = document.getElementById('console');

    let isRunning = false;
    let globalTotal = 0;
    let globalDone = 0;

    const logToConsole = (msg, level) => {
        const div = document.createElement('div');
        div.className = `log-${level}`;
        const icons = { info: '·', ok: '✓', warn: '⚠', err: '✗', section: '▶', skip: '○' };
        div.textContent = `${icons[level] || '·'} ${msg}`;
        consoleEl.appendChild(div);
        consoleEl.scrollTop = consoleEl.scrollHeight;
    };

    const updateProgressUI = () => {
        if (globalTotal === 0) return;
        const pct = Math.min(100, Math.round((globalDone / globalTotal) * 100));
        pFill.style.width = `${pct}%`;
        statPct.textContent = `${pct}%`;
        statText.textContent = `${globalDone} / ${globalTotal} séries traitées`;
    };

    const fetchStatus = async () => {
        try {
            const res = await fetch('?action=status');
            const data = await res.json();
            globalTotal = data.total;
            globalDone = data.done;
            updateProgressUI();
            
            if (data.remaining > 0) {
                btnStart.disabled = false;
                logToConsole("Système prêt. " + data.remaining + " séries en attente.", 'info');
            } else {
                logToConsole("Base de données à jour. Aucune action requise.", 'ok');
            }
        } catch (e) {
            logToConsole("Erreur de connexion au serveur.", 'err');
        }
    };

    const processBatch = async () => {
        if (!isRunning) return;

        const isDryRun = chkDryRun.checked ? '&dry-run=1' : '';
        
        try {
            const res = await fetch(`?action=process${isDryRun}`);
            const data = await res.json();

            if (data.error) {
                logToConsole("Erreur critique : " + data.error, 'err');
                stopProcess();
                return;
            }

            if (data.logs && data.logs.length > 0) {
                data.logs.forEach(l => logToConsole(l.msg, l.level));
            }

            if (data.status === 'finished') {
                globalDone = globalTotal;
                updateProgressUI();
                stopProcess();
                btnStart.disabled = true;
                return;
            }

            if (data.status === 'continue') {
                globalDone += data.processed;
                // Si la base contient moins que la différence prévue (suite à un rollback d'erreur), on se recale avec un appel statut
                if(data.errors > 0) fetchStatus(); else updateProgressUI();
                
                setTimeout(() => { if (isRunning) processBatch(); }, 2000);
            }

        } catch (e) {
            logToConsole("Échec réseau. Nouvelle tentative dans 5 secondes...", 'err');
            setTimeout(() => { if (isRunning) processBatch(); }, 5000);
        }
    };

    const startProcess = () => {
        isRunning = true;
        btnStart.disabled = true;
        btnStop.disabled = false;
        btnReset.disabled = true;
        chkDryRun.disabled = true;
        logToConsole("--- Lancement du protocole ---", 'section');
        processBatch();
    };

    const stopProcess = () => {
        isRunning = false;
        btnStart.disabled = false;
        btnStop.disabled = true;
        btnReset.disabled = false;
        chkDryRun.disabled = false;
        logToConsole("--- Protocole en pause ---", 'warn');
    };

    btnReset.addEventListener('click', async () => {
        if (confirm("Attention : Cela va effacer TOUTES les notes Oracle existantes sur les séries et tout reprendre à zéro. Confirmer ?")) {
            btnReset.disabled = true;
            btnStart.disabled = true;
            logToConsole("Purge des données de l'Oracle en cours...", 'warn');
            try {
                await fetch('?action=reset');
                logToConsole("--- Base de données purgée. Reprise à zéro ---", 'ok');
                await fetchStatus();
            } catch(e) {
                logToConsole("Erreur lors de la purge.", 'err');
            }
            btnReset.disabled = false;
        }
    });

    btnStart.addEventListener('click', startProcess);
    btnStop.addEventListener('click', stopProcess);

    fetchStatus();
</script>

</body>
</html>