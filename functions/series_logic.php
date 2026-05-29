<?php
/**
 * functions/series_logic.php
 * Logique métier pour les séries : détection nouveaux épisodes + notifications.
 */

if (!function_exists('db_fetch_all')) {
    require_once __DIR__ . '/core_db.php';
}

/**
 * Vérifie les nouveaux épisodes pour toutes les séries suivies
 * et envoie une notification aux followers concernés.
 *
 * Appelée par cron_master.php — tâche 10.
 * La fenêtre de détection est J-1 → J (pour couvrir un cron quotidien).
 */
function check_new_episodes_and_notify(): void
{
    if (!defined('TMDB_API_KEY') || empty(TMDB_API_KEY)) {
        echo "[Tâche 10] Clé TMDB_API_KEY manquante — abandon.\n";
        return;
    }

    // Fenêtre : hier → demain (tolérance si le cron tourne en fin/début de journée)
    $windowStart = date('Y-m-d', strtotime('-1 day'));
    $windowEnd   = date('Y-m-d', strtotime('+1 day'));

    // Séries qui ont au moins un follower
    $seriesWithFollowers = db_fetch_all(
        'SELECT DISTINCT s.id AS series_id, s.tmdb_id, s.title, s.total_seasons
         FROM user_series_follow f
         JOIN series s ON s.id = f.series_id
         WHERE f.is_ended = 0',
        []
    );

    if (empty($seriesWithFollowers)) {
        echo "[Tâche 10] Aucune série suivie active.\n";
        return;
    }

    echo "[Tâche 10] " . count($seriesWithFollowers) . " série(s) à vérifier.\n";

    foreach ($seriesWithFollowers as $serie) {
        $seriesId    = (int)$serie['series_id'];
        $tmdbId      = (int)$serie['tmdb_id'];
        $title       = $serie['title'];
        $totalSaisons = (int)($serie['total_seasons'] ?? 1);

        // Récupère les épisodes récents depuis TMDB pour chaque saison
        $newEpisodes = _fetch_new_episodes_from_tmdb($tmdbId, $totalSaisons, $windowStart, $windowEnd);

        if (empty($newEpisodes)) {
            continue;
        }

        // Mise à jour / insertion des épisodes en BDD
        foreach ($newEpisodes as $ep) {
            _upsert_episode($seriesId, $ep);
        }

        // Followers à notifier (non-ended, pas encore notifiés pour cet épisode)
        $followers = db_fetch_all(
            'SELECT user_id FROM user_series_follow WHERE series_id = ? AND is_ended = 0',
            [$seriesId]
        );

        foreach ($newEpisodes as $ep) {
            $epLabel  = 'S' . str_pad($ep['season'], 2, '0', STR_PAD_LEFT)
                      . 'E' . str_pad($ep['episode'], 2, '0', STR_PAD_LEFT);
            $epTitle  = $ep['name'] ? " — {$ep['name']}" : '';
            $notifTitle = "{$title} · {$epLabel}{$epTitle}";
            $notifLink  = "/fiche.php?id={$tmdbId}&type=tv&season={$ep['season']}&episode={$ep['episode']}";
            // source_id unique par épisode pour éviter les doublons
            $sourceId = "ep_{$seriesId}_{$ep['season']}_{$ep['episode']}";

            foreach ($followers as $follower) {
                $userId = $follower['user_id'];

                // Vérifier si la notif existe déjà (idempotence)
                $alreadyNotified = db_fetch_one(
                    'SELECT 1 FROM notifications WHERE user_id = ? AND source_id = ? AND type = "new_episode"',
                    [$userId, $sourceId]
                );
                if ($alreadyNotified) continue;

                db_execute(
                    'INSERT INTO notifications (user_id, type, source_id, title, link, is_read, created_at)
                     VALUES (?, "new_episode", ?, ?, ?, 0, NOW())',
                    [$userId, $sourceId, $notifTitle, $notifLink]
                );

                echo "[Tâche 10] Nouveau : {$notifTitle} → user {$userId}\n";
            }
        }
    }

    echo "[Tâche 10] Vérification terminée.\n";
}

/**
 * Récupère les épisodes diffusés entre $windowStart et $windowEnd
 * pour toutes les saisons d'une série TMDB.
 *
 * Utilise fetch_tmdb_season() de api_tmdb.php pour chaque saison.
 * On ne frappe TMDB que pour les saisons dont au moins un épisode
 * pourrait être dans la fenêtre — on ne peut pas le savoir à l'avance,
 * donc on interroge toutes les saisons du 1 au $totalSaisons.
 *
 * @return array  Liste de tableaux ['season', 'episode', 'name', 'air_date']
 */
function _fetch_new_episodes_from_tmdb(int $tmdbId, int $totalSaisons, string $windowStart, string $windowEnd): array
{
    if (!function_exists('fetch_tmdb_season')) {
        require_once __DIR__ . '/api_tmdb.php';
    }

    $newEpisodes = [];

    for ($s = 1; $s <= $totalSaisons; $s++) {
        $seasonData = fetch_tmdb_season($tmdbId, $s);
        if (!$seasonData || empty($seasonData['episodes'])) continue;

        foreach ($seasonData['episodes'] as $ep) {
            $airDate = $ep['air_date'] ?? null;
            if (!$airDate) continue;
            // Filtre : épisode diffusé dans la fenêtre de détection
            if ($airDate >= $windowStart && $airDate <= $windowEnd) {
                $newEpisodes[] = [
                    'season'   => $s,
                    'episode'  => $ep['episode_number'],
                    'name'     => $ep['name'] ?? '',
                    'air_date' => $airDate,
                ];
            }
        }
    }

    return $newEpisodes;
}

/**
 * Insère ou met à jour un épisode dans series_episodes.
 * Appelée après détection d'un nouvel épisode pour garder la BDD à jour.
 *
 * @param int   $seriesId  ID local (table series)
 * @param array $ep        ['season', 'episode', 'name', 'air_date']
 */
function _upsert_episode(int $seriesId, array $ep): void
{
    db_execute(
        'INSERT INTO series_episodes (series_id, season_number, episode_number, air_date, name)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             air_date = VALUES(air_date),
             name     = VALUES(name)',
        [$seriesId, $ep['season'], $ep['episode'], $ep['air_date'], $ep['name']]
    );
}
