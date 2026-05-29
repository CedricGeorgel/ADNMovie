<?php
/**
 * FUNCTIONS/SERIES_AGGREGATE_LOGIC.PHP
 *
 * Chaîne d'agrégation complète pour les séries TV :
 *   episode_ratings → season_ratings → series_ratings → series_dna
 *
 * Règles de préservation :
 *   - Note épisode  → recalcule la saison  → recalcule la série
 *   - Note saison   → recalcule la série   (épisodes non touchés)
 *   - Note série    → INSERT IGNORE sur saisons uniquement (épisodes non touchés)
 *
 * Poids :
 *   - Saison calculée depuis épisodes : weight = épisodes_notés / total_episodes
 *   - Série : moyenne des weights de saison
 *
 * Nécessite core_db.php et ratings_logic.php déjà chargés.
 */

if (!function_exists('db_fetch_all')) {
    require_once __DIR__ . '/core_db.php';
}


// ════════════════════════════════════════════════════════════════════════════
// ÉPISODE → SAISON
// ════════════════════════════════════════════════════════════════════════════

if (!function_exists('_recalc_season_from_episodes')) {

/**
 * Recalcule season_ratings pour un user/série/saison donnés
 * à partir des épisodes notés dans episode_ratings.
 *
 * Le rating_weight = épisodes_notés / total_episodes (stocké dans season_ratings).
 * Si total_episodes est NULL (colonne non renseignée), on utilise le nombre
 * d'épisodes notés comme dénominateur de repli (weight = 1.0).
 *
 * Si aucun épisode n'est noté, on supprime la ligne de saison agrégée
 * SAUF si elle avait été saisie manuellement (détectée par source = 'manual').
 * → En pratique on recalcule toujours : la note manuelle est écrasée par
 *   la moyenne des épisodes dès qu'un épisode est noté (comportement voulu).
 */
function _recalc_season_from_episodes(string $userId, int $seriesId, int $seasonNumber): void
{
    $episodes = db_fetch_all(
        'SELECT scores, like_score, is_liked, rating_weight
         FROM episode_ratings
         WHERE user_id = ? AND series_id = ? AND season_number = ? AND scores IS NOT NULL',
        [$userId, $seriesId, $seasonNumber]
    );

    if (empty($episodes)) {
        // Aucun épisode noté → on supprime la saison agrégée pour cet user
        db_execute(
            'DELETE FROM season_ratings
             WHERE user_id = ? AND series_id = ? AND season_number = ?',
            [$userId, $seriesId, $seasonNumber]
        );
        return;
    }

    // Récupérer total_episodes pour ce user/série/saison
    $seasonRow = db_fetch_one(
        'SELECT total_episodes FROM season_ratings
         WHERE series_id = ? AND season_number = ? AND total_episodes IS NOT NULL
         LIMIT 1',
        [$seriesId, $seasonNumber]
    );
    $totalEpisodes = $seasonRow ? (int)$seasonRow['total_episodes'] : count($episodes);
    $notedCount    = count($episodes);
    $weight        = $totalEpisodes > 0
        ? round($notedCount / $totalEpisodes, 4)
        : 1.0;

    // Agréger scores par critère
    $sumByCritere   = [];
    $countByCritere = [];
    $sumLikeScore   = 0;
    $countLikeScore = 0;
    $isLikedAny     = 0;

    foreach ($episodes as $row) {
        $scores = json_decode($row['scores'], true) ?? [];
        foreach ($scores as $criterio => $val) {
            if ($val !== null) {
                $sumByCritere[$criterio]   = ($sumByCritere[$criterio]   ?? 0) + (float)$val;
                $countByCritere[$criterio] = ($countByCritere[$criterio] ?? 0) + 1;
            }
        }
        if ($row['like_score'] !== null) {
            $sumLikeScore += (int)$row['like_score'];
            $countLikeScore++;
        }
        if ($row['is_liked']) $isLikedAny = 1;
    }

    $aggScores = [];
    foreach ($sumByCritere as $criterio => $sum) {
        $aggScores[$criterio] = round($sum / $countByCritere[$criterio], 4);
    }
    $aggLikeScore = $countLikeScore > 0 ? (int)round($sumLikeScore / $countLikeScore) : null;

    $h      = (int)date('G');
    $d      = (int)date('N');
    $slot   = ($h >= 5 && $h < 18) ? 'journee' : (($h >= 18 && $h < 23) ? 'soiree' : 'nuit');
    $period = ($d >= 6) ? 'weekend' : 'semaine';

    db_execute(
        'INSERT INTO season_ratings
            (user_id, series_id, season_number, scores, like_score, is_liked,
             rating_weight, context_period, context_slot, rated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
             scores         = VALUES(scores),
             like_score     = VALUES(like_score),
             is_liked       = VALUES(is_liked),
             rating_weight  = VALUES(rating_weight),
             context_period = VALUES(context_period),
             context_slot   = VALUES(context_slot),
             rated_at       = NOW()',
        [$userId, $seriesId, $seasonNumber,
         json_encode($aggScores), $aggLikeScore, $isLikedAny ? 1 : 0,
         $weight, $period, $slot]
    );
}

} // end if !function_exists


// ════════════════════════════════════════════════════════════════════════════
// SAISON → SÉRIE
// ════════════════════════════════════════════════════════════════════════════

if (!function_exists('_recalc_series_aggregate')) {

/**
 * Recalcule series_ratings pour un user/série donné à partir de season_ratings.
 * La note agrégée = moyenne pondérée des saisons (pondération par rating_weight).
 */
function _recalc_series_aggregate(string $userId, int $seriesId): void
{
    $seasons = db_fetch_all(
        'SELECT scores, like_score, is_liked, rating_weight
         FROM season_ratings
         WHERE user_id = ? AND series_id = ? AND scores IS NOT NULL',
        [$userId, $seriesId]
    );

    if (empty($seasons)) {
        db_execute(
            'DELETE FROM series_ratings WHERE user_id = ? AND series_id = ?',
            [$userId, $seriesId]
        );
        return;
    }

    $sumByCritere   = [];
    $countByCritere = [];
    $sumLikeScore   = 0;
    $countLikeScore = 0;
    $sumWeight      = 0;
    $isLikedAny     = 0;

    foreach ($seasons as $row) {
        $scores = json_decode($row['scores'], true) ?? [];
        foreach ($scores as $criterio => $val) {
            if ($val !== null) {
                $sumByCritere[$criterio]   = ($sumByCritere[$criterio]   ?? 0) + (float)$val;
                $countByCritere[$criterio] = ($countByCritere[$criterio] ?? 0) + 1;
            }
        }
        if ($row['like_score'] !== null) {
            $sumLikeScore += (int)$row['like_score'];
            $countLikeScore++;
        }
        if ($row['is_liked']) $isLikedAny = 1;
        $sumWeight += (float)$row['rating_weight'];
    }

    $aggScores = [];
    foreach ($sumByCritere as $criterio => $sum) {
        $aggScores[$criterio] = round($sum / $countByCritere[$criterio], 4);
    }

    $aggLikeScore = $countLikeScore > 0 ? (int)round($sumLikeScore / $countLikeScore) : null;
    $aggWeight    = round($sumWeight / count($seasons), 4);

    db_execute(
        'INSERT INTO series_ratings
            (user_id, series_id, scores, like_score, is_liked, rating_weight, rated_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
             scores        = VALUES(scores),
             like_score    = VALUES(like_score),
             is_liked      = VALUES(is_liked),
             rating_weight = VALUES(rating_weight),
             rated_at      = NOW()',
        [$userId, $seriesId, json_encode($aggScores), $aggLikeScore, $isLikedAny, $aggWeight]
    );
}

} // end if !function_exists


// ════════════════════════════════════════════════════════════════════════════
// ADN COLLECTIF SÉRIE
// ════════════════════════════════════════════════════════════════════════════

if (!function_exists('update_series_dna')) {

/**
 * Met à jour series_dna (ADN collectif de la série) depuis season_ratings.
 */
function update_series_dna(int $seriesId): void
{
    $rows = db_fetch_all(
        'SELECT scores, rating_weight
         FROM season_ratings
         WHERE series_id = ? AND scores IS NOT NULL',
        [$seriesId]
    );

    if (empty($rows)) {
        db_execute('DELETE FROM series_dna WHERE series_id = ?', [$seriesId]);
        return;
    }

    $data = [];
    foreach ($rows as $row) {
        $scores = json_decode($row['scores'], true) ?? [];
        $w = max(0.01, (float)$row['rating_weight']);
        foreach ($scores as $criterio => $val) {
            if ($val !== null) {
                $data[$criterio]['values'][]  = (float)$val;
                $data[$criterio]['weights'][] = $w;
            }
        }
    }

    foreach ($data as $criterio => $d) {
        $n    = count($d['values']);
        $wSum = array_sum($d['weights']);

        $wavg = 0;
        foreach ($d['values'] as $i => $v) {
            $wavg += $v * $d['weights'][$i];
        }
        $wavg = $wSum > 0 ? $wavg / $wSum : 0;

        $wvar = 0;
        foreach ($d['values'] as $i => $v) {
            $wvar += $d['weights'][$i] * pow($v - $wavg, 2);
        }
        $wvar = $wSum > 0 ? $wvar / $wSum : 0;

        db_execute(
            'INSERT INTO series_dna (series_id, criterio, avg_score, variance, sample_size)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 avg_score   = VALUES(avg_score),
                 variance    = VALUES(variance),
                 sample_size = VALUES(sample_size)',
            [$seriesId, $criterio, round($wavg, 4), round($wvar, 4), $n]
        );
    }
}

} // end if !function_exists