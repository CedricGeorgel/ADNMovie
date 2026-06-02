<?php
/**
 * CONFIG/CRON_MASTER.PHP
 * Script de maintenance (Mode Diagnostic)
 */
session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
set_time_limit(120);

require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/../functions/auth_role_helper.php';

$isServerCron = (php_sapi_name() === 'cli' || empty($_SERVER['REMOTE_ADDR']));
$isAdmin = has_role('admin');

if (!$isServerCron && !$isAdmin) {
    http_response_code(403);
    die("Accès refusé.");
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== DÉMARRAGE DES TÂCHES DE MAINTENANCE ===\n\n";
flush();

// ── INIT DU RAPPORT ──────────────────────────────────────────────────────────
$report = [
    'last_run'       => date('Y-m-d H:i:s'),
    'timestamp'      => time(),
    'execution_mode' => php_sapi_name(),
    'tasks'          => [
        'room_events_deleted'    => null, // int
        'sessions_deleted'       => null, // int
        'ds_store_deleted'       => null, // int
        'tmdb_films_inserted'    => null, // int
        'oracle_films_cleaned'   => null, // array de ['id', 'title']
        'oracle_series_cleaned'  => null, // array de ['id', 'title']
        'dna_snapshots_taken'    => null, // int — tâche 6
        'providers_refreshed'    => null, // int — tâche 7
        'notifications_purged'   => null, // int — tâche 8
        'access_attempts_purged' => null, // int — tâche 9
        'new_episodes_notified'  => null, // int — tâche 10
        'watchlist_notified'     => null, // int — tâche 11
    ],
    'errors'         => [],
];

// ── LOG DE PASSAGE (JSON CUMULATIF) ─────────────────────────────────────────
// (le log est écrit à la FIN du script pour inclure toutes les données)

// ── TÂCHE 1 : Purge room_events ──────────────────────────────────────────────
echo "-> Lancement Tâche 1 : Purge room_events...\n";
flush();
try {
    db_execute("DELETE ea FROM event_attendees ea JOIN room_events re ON ea.event_id = re.id WHERE re.event_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $deletedCount = db_execute("DELETE FROM room_events WHERE event_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $report['tasks']['room_events_deleted'] = is_numeric($deletedCount) ? (int)$deletedCount : 0;
    echo "[OK] Purge room_events terminée ({$report['tasks']['room_events_deleted']} supprimés).\n\n";
} catch (Exception $e) {
    $report['errors'][] = "Tâche 1 : " . $e->getMessage();
    echo "[ERREUR] Tâche 1 : " . $e->getMessage() . "\n\n";
}

// ── TÂCHE 2 : Purge sessions inactives ──────────────────────────────────────
echo "-> Lancement Tâche 2 : Purge sessions inactives...\n";
flush();
try {
    $oldRooms = db_fetch_all(
        "SELECT id FROM rooms WHERE last_activity_at < DATE_SUB(NOW(), INTERVAL 365 DAY)"
    );
    if (!empty($oldRooms)) {
        $roomIds      = array_column($oldRooms, 'id');
        $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
        db_execute("DELETE FROM room_votes     WHERE room_id IN ($placeholders)", $roomIds);
        db_execute("DELETE FROM room_proposals WHERE room_id IN ($placeholders)", $roomIds);
        db_execute("DELETE FROM room_members   WHERE room_id IN ($placeholders)", $roomIds);
        $deletedRooms = db_execute("DELETE FROM rooms WHERE id IN ($placeholders)", $roomIds);
        $report['tasks']['sessions_deleted'] = is_numeric($deletedRooms) ? (int)$deletedRooms : count($roomIds);
        echo "[OK] Purge sessions terminée ({$report['tasks']['sessions_deleted']} supprimées).\n\n";
    } else {
        $report['tasks']['sessions_deleted'] = 0;
        echo "[OK] Aucune session inactive trouvée.\n\n";
    }
} catch (Exception $e) {
    $report['errors'][] = "Tâche 2 : " . $e->getMessage();
    echo "[ERREUR] Tâche 2 : " . $e->getMessage() . "\n\n";
}

// ── TÂCHE 3 : Scan .DS_Store ─────────────────────────────────────────────────
echo "-> Lancement Tâche 3 : Scan fichiers .DS_Store...\n";
flush();
try {
    $directory = new RecursiveDirectoryIterator(__DIR__ . '/../', RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator  = new RecursiveIteratorIterator($directory);
    $dsCount   = 0;
    foreach ($iterator as $file) {
        if ($file->getFilename() === '.DS_Store' && unlink($file->getPathname())) {
            $dsCount++;
        }
    }
    $report['tasks']['ds_store_deleted'] = $dsCount;
    echo "[OK] Nettoyage OS terminé ($dsCount supprimés).\n\n";
} catch (Exception $e) {
    $report['errors'][] = "Tâche 3 : " . $e->getMessage();
    echo "[ERREUR] Tâche 3 : " . $e->getMessage() . "\n\n";
}

// ── TÂCHE 4 : Appel API TMDB (Sorties Francophones) ──────────────────────────
echo "-> Lancement Tâche 4 : Appel API TMDB (FR, BE, CH, LU)...\n";
flush();
try {
    if (!defined('TMDB_API_KEY') || empty(TMDB_API_KEY)) {
        throw new Exception("Clé TMDB_API_KEY manquante.");
    }

    $regions = ['FR', 'BE', 'CH', 'LU'];
    $uniqueMovies = [];
    $apiCallsCount = 0;

    foreach ($regions as $region) {
        $currentPage = 1;
        
        do {
            $apiUrl = "https://api.themoviedb.org/3/movie/now_playing?api_key=" . TMDB_API_KEY . "&language=fr-FR&region=" . $region . "&page=" . $currentPage;
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_USERAGENT => 'ADNmovie-App/1.0'
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                echo "[Avertissement] TMDB : Échec pour la région $region (Code HTTP $httpCode).\n";
                break; // On abandonne ce pays, mais on passe au suivant
            }

            $data = json_decode($response, true);
            if (empty($data['results'])) break;

            // Dédoublonnage systématique à la volée via la clé du tableau
            foreach ($data['results'] as $m) {
                $uniqueMovies[$m['id']] = $m;
            }

            // On bride à 2 pages par région (soit le top 40) pour cibler la véritable "affiche"
            $totalPages = min((int)($data['total_pages'] ?? 1), 2);
            $currentPage++;
            $apiCallsCount++;

            usleep(250000); // Préservation du quota API (4 requêtes/seconde max)
        } while ($currentPage <= $totalPages);
    }

    if (!empty($uniqueMovies)) {
        db_begin();
        
        // Purge de l'existant
        db_execute("DELETE FROM movies_now_playing");
        
        // Insertion sécurisée : le IGNORE prévient tout crash SQL résiduel
        $sql = "INSERT IGNORE INTO movies_now_playing (tmdb_id, title, poster_path, release_date, vote_average, overview) VALUES (?, ?, ?, ?, ?, ?)";
        $insertedCount = 0;
        
        foreach ($uniqueMovies as $m) {
            db_execute($sql, [
                $m['id'], 
                $m['title'], 
                $m['poster_path'], 
                empty($m['release_date']) ? null : $m['release_date'], 
                $m['vote_average'], 
                $m['overview']
            ]);
            $insertedCount++;
        }
        
        db_commit();
        $report['tasks']['tmdb_films_inserted'] = $insertedCount;
        echo "[OK] TMDB terminé ($insertedCount films uniques insérés, $apiCallsCount appels API effectués).\n\n";
    } else {
        $report['tasks']['tmdb_films_inserted'] = 0;
        echo "[ERREUR] TMDB : Aucun résultat viable récupéré.\n\n";
    }
} catch (Exception $e) {
    if (function_exists('db_is_in_transaction') && db_is_in_transaction()) db_rollback();
    $report['errors'][] = "Tâche 4 : " . $e->getMessage();
    echo "[ERREUR] Tâche 4 : " . $e->getMessage() . "\n\n";
}

// ── TÂCHE 5 : Oracle Cleanup ─────────────────────────────────────────────────
echo "-> Lancement Tâche 5 : Oracle Cleanup...\n";
flush();

if (!defined('ORACLE_USER_ID')) define('ORACLE_USER_ID', 'IA-ORACLE-001');
require_once __DIR__ . '/../functions/ratings_logic.php';
if (file_exists(__DIR__ . '/../functions/snapshot_logic.php')) {
    require_once __DIR__ . '/../functions/snapshot_logic.php';
}

try {
    $targetMovies = db_fetch_all(
        "SELECT r_oracle.movie_id, m.title
         FROM ratings r_oracle
         JOIN movies m ON m.tmdb_id = r_oracle.movie_id
         JOIN (
             SELECT movie_id, COUNT(*) as human_votes
             FROM ratings
             WHERE user_id != ?
               AND scores IS NOT NULL
             GROUP BY movie_id
             HAVING human_votes >= 5
         ) humans ON humans.movie_id = r_oracle.movie_id
         WHERE r_oracle.user_id = ?",
        [ORACLE_USER_ID, ORACLE_USER_ID]
    );

    $oracleFilms = [];

    if (empty($targetMovies)) {
        echo "[Oracle Cleanup] Aucun film à traiter.\n";
    } else {
        foreach ($targetMovies as $row) {
            $movieId = $row['movie_id'];
            $title   = $row['title'] ?? "Film #$movieId";

            db_execute(
                "DELETE FROM ratings WHERE user_id = ? AND movie_id = ?",
                [ORACLE_USER_ID, $movieId]
            );

            update_movie_dna($movieId);

            $mDna = db_fetch_all('SELECT variance FROM movie_dna WHERE movie_id = ?', [$movieId]);
            $totalVar = 0;
            foreach ($mDna as $md) { $totalVar += (float)$md['variance']; }
            $polarization = count($mDna) > 0 ? round($totalVar / count($mDna), 2) : 0;

            $voteCount = (int)db_fetch_one(
                'SELECT COUNT(*) as cnt FROM ratings WHERE movie_id = ? AND scores IS NOT NULL',
                [$movieId]
            )['cnt'];

            db_execute(
                'UPDATE movies SET total_votes = ?, polarization_index = ? WHERE tmdb_id = ?',
                [$voteCount, $polarization, $movieId]
            );

            $oracleFilms[] = ['id' => $movieId, 'title' => $title, 'human_votes' => $voteCount];
            echo "[Oracle Cleanup] Film #$movieId \"$title\" → Oracle retiré, ADN recalculé ($voteCount votes humains).\n";
        }
    }

    $report['tasks']['oracle_films_cleaned'] = $oracleFilms;

    // ── Séries : même logique ──────────────────────────────────────────────────
    $targetSeries = db_fetch_all(
        "SELECT r_oracle.series_id, s.title
         FROM series_ratings r_oracle
         JOIN series s ON s.id = r_oracle.series_id
         JOIN (
             SELECT series_id, COUNT(*) as human_votes
             FROM series_ratings
             WHERE user_id != ?
               AND scores IS NOT NULL
             GROUP BY series_id
             HAVING human_votes >= 5
         ) humans ON humans.series_id = r_oracle.series_id
         WHERE r_oracle.user_id = ?",
        [ORACLE_USER_ID, ORACLE_USER_ID]
    );

    $oracleSeries = [];
    foreach ($targetSeries as $row) {
        $seriesId = $row['series_id'];
        $title    = $row['title'] ?? "Série #$seriesId";

        db_execute(
            "DELETE FROM series_ratings WHERE user_id = ? AND series_id = ?",
            [ORACLE_USER_ID, $seriesId]
        );

        $voteCount = (int)db_fetch_one(
            'SELECT COUNT(*) as cnt FROM series_ratings WHERE series_id = ? AND scores IS NOT NULL',
            [$seriesId]
        )['cnt'];

        db_execute('UPDATE series SET total_votes = ? WHERE id = ?', [$voteCount, $seriesId]);

        $oracleSeries[] = ['id' => $seriesId, 'title' => $title, 'human_votes' => $voteCount];
        echo "[Oracle Cleanup] Série #$seriesId \"$title\" → Oracle retiré ($voteCount votes humains).\n";
    }
    $report['tasks']['oracle_series_cleaned'] = $oracleSeries;

    echo "[Oracle Cleanup] Terminé.\n\n";

} catch (Exception $e) {
    $report['errors'][] = "Tâche 5 (Oracle) : " . $e->getMessage();
    echo "[ERREUR] Oracle Cleanup : " . $e->getMessage() . "\n\n";
}

// ── TÂCHE 6 : Snapshot ADN mensuel (le 25 uniquement) ───────────────────────────
echo "-> Lancement Tâche 6 : Snapshot ADN mensuel...\n";
flush();
try {
    $today = (int)date('d');
    $month = (int)date('m');

    // Snapshot mensuel : le 25 de chaque mois
    // Snapshot trimestriel : le 25 du 3e mois de chaque trimestre (mars, juin, sept, déc)
    // Snapshot annuel : le 25 décembre
    $doMonthly    = ($today === 25);
    $doQuarterly  = ($today === 25 && in_array($month, [3, 6, 9, 12]));
    $doYearly     = ($today === 25 && $month === 12);

    if (!$doMonthly) {
        $report['tasks']['dna_snapshots_taken'] = 0;
        echo "[OK] Pas le 25 — snapshot ignoré.\n\n";
    } elseif (!function_exists('get_current_period_key')) {
        $report['tasks']['dna_snapshots_taken'] = 0;
        echo "[SKIP] snapshot_logic.php non disponible.\n\n";
    } else {
        $types = ['monthly'];
        if ($doQuarterly) $types[] = 'quarterly';
        if ($doYearly)    $types[] = 'yearly';

        $totalSnapped = 0;

        foreach ($types as $snapType) {
            $periodKey = get_current_period_key($snapType);
            [$dateStart, $dateEnd] = get_period_bounds_for_type($snapType);

            // Users actifs sur la période
            $activeUsers = db_fetch_all(
                "SELECT DISTINCT user_id FROM ratings WHERE rated_at BETWEEN ? AND ?",
                [$dateStart, $dateEnd]
            );

            foreach ($activeUsers as $uRow) {
                $uid = $uRow['user_id'];

                // Déjà snapshoté pour cette période ?
                $exists = db_fetch_one(
                    "SELECT 1 FROM user_dna_history WHERE user_id=? AND period_key=? AND snapshot_type=? LIMIT 1",
                    [$uid, $periodKey, $snapType]
                );
                if ($exists) continue;

                $dnaRows = db_fetch_all(
                    'SELECT criterio, avg_score, variance, min_score, max_score, vote_count FROM user_dna WHERE user_id=?',
                    [$uid]
                );
                if (empty($dnaRows)) continue;

                foreach ($dnaRows as $dna) {
                    db_execute(
                        "INSERT IGNORE INTO user_dna_history
                            (user_id, criterio, avg_score, variance, min_score, max_score, vote_count, snapshot_type, period_key, snapped_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                        [$uid, $dna['criterio'], $dna['avg_score'],
                         $dna['variance'] ?? null, $dna['min_score'] ?? null,
                         $dna['max_score'] ?? null, $dna['vote_count'] ?? 0,
                         $snapType, $periodKey]
                    );
                }
                $totalSnapped++;
                echo "[Snapshot] ✓ $snapType/$periodKey — user $uid\n";
            }
        }

        $report['tasks']['dna_snapshots_taken'] = $totalSnapped;
        echo "[OK] Snapshots terminés ($totalSnapped users, types: " . implode(', ', $types) . ").\n\n";
    }
} catch (Exception $e) {
    $report['errors'][] = "Tâche 6 : " . $e->getMessage();
    $report['tasks']['dna_snapshots_taken'] = 0;
    echo "[ERREUR] Tâche 6 : " . $e->getMessage() . "\n\n";
}

// ── TÂCHE 7 : Refresh watch providers (TTL 3 jours, batch 50) ─────────────────
echo "-> Lancement Tâche 7 : Refresh watch providers...\n";
flush();
try {
    if (!defined('TMDB_API_KEY') || empty(TMDB_API_KEY)) throw new Exception("Clé TMDB_API_KEY manquante.");

    // Films dont les providers sont absents (NULL) ou vieux de plus de 3 jours
    // Les NULL passent en premier grâce au ORDER BY … IS NOT NULL
    $staleMovies = db_fetch_all(
        "SELECT tmdb_id FROM movies
         WHERE providers_updated_at IS NULL
            OR providers_updated_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
         ORDER BY providers_updated_at IS NOT NULL ASC, providers_updated_at ASC
         LIMIT 50",
        []
    );

    $refreshed = 0;
    $errors    = 0;

    foreach ($staleMovies as $movie) {
        $mid = (int) $movie['tmdb_id'];
        $url = "https://api.themoviedb.org/3/movie/{$mid}/watch/providers?api_key=" . TMDB_API_KEY;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_USERAGENT      => 'AdnMovie-App/2.0',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data      = json_decode($response, true);
            $providers = $data['results']['FR'] ?? [];
            db_execute(
                'UPDATE movies SET providers_data = ?, providers_updated_at = NOW() WHERE tmdb_id = ?',
                [json_encode($providers, JSON_UNESCAPED_UNICODE), $mid]
            );
            $refreshed++;
        } else {
            // En cas d'erreur API, on met quand même à jour le timestamp pour
            // ne pas re-tenter immédiatement ce film à chaque run
            db_execute(
                'UPDATE movies SET providers_updated_at = NOW() WHERE tmdb_id = ? AND providers_updated_at IS NULL',
                [$mid]
            );
            $errors++;
        }

        // 250 ms entre chaque requête ≈ 4 req/s — bien sous les limites TMDB
        usleep(250_000);
    }

    $report['tasks']['providers_refreshed'] = $refreshed;
    echo "[OK] Watch providers : $refreshed mis à jour, $errors erreurs API (sur " . count($staleMovies) . " traités).\n\n";

} catch (Exception $e) {
    $report['errors'][] = "Tâche 7 : " . $e->getMessage();
    $report['tasks']['providers_refreshed'] = 0;
    echo "[ERREUR] Tâche 7 : " . $e->getMessage() . "\n\n";
}

// ── TÂCHE 8 : Purge notifications lues (> 30 jours) ─────────────────────────
echo "-> Lancement Tâche 8 : Purge notifications lues...\n";
flush();
try {
    $deleted = db_execute(
        "DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $report['tasks']['notifications_purged'] = is_numeric($deleted) ? (int)$deleted : 0;
    echo "[OK] Notifications purgées ({$report['tasks']['notifications_purged']} supprimées).\n\n";
} catch (Exception $e) {
    $report['errors'][] = "Tâche 8 : " . $e->getMessage();
    $report['tasks']['notifications_purged'] = 0;
    echo "[ERREUR] Tâche 8 : " . $e->getMessage() . "\n\n";
}

// ── TÂCHE 9 : Purge access_attempts (> 90 jours) ────────────────────────────
echo "-> Lancement Tâche 9 : Purge access_attempts...\n";
flush();
try {
    $deleted = db_execute(
        "DELETE FROM access_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
    );
    $report['tasks']['access_attempts_purged'] = is_numeric($deleted) ? (int)$deleted : 0;
    echo "[OK] Tentatives d'accès purgées ({$report['tasks']['access_attempts_purged']} supprimées).\n\n";
} catch (Exception $e) {
    $report['errors'][] = "Tâche 9 : " . $e->getMessage();
    $report['tasks']['access_attempts_purged'] = 0;
    echo "[ERREUR] Tâche 9 : " . $e->getMessage() . "\n\n";
}

// ── TÂCHE 10 : Vérification nouveaux épisodes séries ───────────────────────────
echo "-> Lancement Tâche 10 : Vérification nouveaux épisodes séries...\n";
flush();
try {
    if (!function_exists('check_new_episodes_and_notify')) {
        require_once __DIR__ . '/../functions/series_logic.php';
    }
    ob_start();
    check_new_episodes_and_notify();
    $output = ob_get_clean();
    echo $output;
    $notifiedCount = substr_count($output, 'Nouveau :');
    $report['tasks']['new_episodes_notified'] = $notifiedCount;
    echo "[OK] Vérification épisodes terminée ($notifiedCount notifications envoyées).\n\n";
} catch (Exception $e) {
    $report['errors'][] = "Tâche 10 : " . $e->getMessage();
    $report['tasks']['new_episodes_notified'] = 0;
    echo "[ERREUR] Tâche 10 : " . $e->getMessage() . "\n\n";
}


// ── TÂCHE 11 : Watchlist films × providers abonnés ──────────────────────────
echo "-> Lancement Tâche 11 : Watchlist films × providers abonnés...\n";
flush();
try {
    if (!function_exists('check_watchlist_provider_and_notify')) {
        require_once __DIR__ . '/../functions/series_logic.php';
    }
    ob_start();
    check_watchlist_provider_and_notify();
    $output = ob_get_clean();
    echo $output;
    $notifiedCount = substr_count($output, 'Nouveau :');
    $report['tasks']['watchlist_notified'] = $notifiedCount;
    echo "[OK] Watchlist providers terminée ($notifiedCount notifications envoyées).\n\n";
} catch (Exception $e) {
    $report['errors'][] = "Tâche 11 : " . $e->getMessage();
    $report['tasks']['watchlist_notified'] = 0;
    echo "[ERREUR] Tâche 11 : " . $e->getMessage() . "\n\n";
}


// ── ÉCRITURE DU LOG JSON ─────────────────────────────────────────────────────
try {
    $logPath = __DIR__ . '/cron_status.json';
    $history = [];
    if (file_exists($logPath)) {
        $history = json_decode(file_get_contents($logPath), true) ?: [];
    }
    array_unshift($history, $report);
    $history = array_slice($history, 0, 50);
    file_put_contents($logPath, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "-> [LOG] Rapport complet sauvegardé dans /config/cron_status.json\n\n";
} catch (Exception $e) {
    echo "-> [LOG] Échec de l'écriture du JSON : " . $e->getMessage() . "\n\n";
}

echo "=== MAINTENANCE TERMINÉE ===";