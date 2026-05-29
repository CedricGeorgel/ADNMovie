<?php
/**
 * API/API_RATE_EPISODE.PHP — Notation d'un épisode TV
 *
 * Sauvegarde dans episode_ratings, puis remonte la chaîne :
 *   episode_ratings → _recalc_season_from_episodes → season_ratings
 *                   → _recalc_series_aggregate     → series_ratings
 *                   → update_user_series_dna
 *                   → update_series_dna
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/ratings_logic.php';
require_once __DIR__ . '/../functions/series_aggregate_logic.php';
require_once __DIR__ . '/../functions/notifications_logic.php';
require_once __DIR__ . '/../functions/achievements_logic.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId   = $_SESSION['user_id'];
$criteria = CRITERIA;

// ── Paramètres ────────────────────────────────────────────────────────────────
$seriesId      = (int)($_POST['series_id']      ?? 0);
$seasonNumber  = (int)($_POST['season_number']  ?? 0);
$episodeNumber = (int)($_POST['episode_number'] ?? 0);
$totalEpisodes = (int)($_POST['total_episodes'] ?? 0); // envoyé par fiche.php

if (!$seriesId) {
    echo json_encode(['success' => false, 'message' => 'ID Série manquant']);
    exit;
}
if ($seasonNumber <= 0) {
    echo json_encode(['success' => false, 'message' => 'Numéro de saison invalide']);
    exit;
}
if ($episodeNumber <= 0) {
    echo json_encode(['success' => false, 'message' => 'Numéro d\'épisode invalide']);
    exit;
}

// ── Lecture du formulaire ─────────────────────────────────────────────────────
$active_scores = $_POST['active_scores'] ?? [];
$raw_scores    = $_POST['scores']        ?? [];

$likeActive = isset($_POST['like_active']) && $_POST['like_active'] === '1';
$likeScore  = null;
if ($likeActive && isset($_POST['like_score'])) {
    $likeScore = max(-10, min(10, (int)$_POST['like_score']));
}
$isLiked = $likeScore !== null && $likeScore > 0;

$finalScores = [];
$filledCount = 0;
foreach ($criteria as $c) {
    $val = isset($active_scores[$c]) ? (float)$raw_scores[$c] : null;
    $finalScores[$c] = $val;
    if ($val !== null) $filledCount++;
}

// Poids de l'épisode individuel = 1 / total_episodes de la saison
// (sera affiné lors de l'agrégation saison, mais on stocke une valeur initiale)
$epWeight = $totalEpisodes > 0
    ? round(1 / $totalEpisodes, 4)
    : 0.1;

$h      = (int)date('G');
$d      = (int)date('N');
$slot   = ($h >= 5 && $h < 18) ? 'journee' : (($h >= 18 && $h < 23) ? 'soiree' : 'nuit');
$period = ($d >= 6) ? 'weekend' : 'semaine';

try {
    db_begin();

    // 1. Upsert de la note d'épisode
    db_execute(
        'INSERT INTO episode_ratings
            (user_id, series_id, season_number, episode_number,
             scores, like_score, is_liked, rating_weight,
             context_period, context_slot, rated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
             scores         = VALUES(scores),
             like_score     = VALUES(like_score),
             is_liked       = VALUES(is_liked),
             rating_weight  = VALUES(rating_weight),
             context_period = VALUES(context_period),
             context_slot   = VALUES(context_slot),
             rated_at       = NOW()',
        [$userId, $seriesId, $seasonNumber, $episodeNumber,
         json_encode($finalScores), $likeScore, $isLiked ? 1 : 0,
         $epWeight, $period, $slot]
    );

    // 2. Mettre à jour total_episodes dans season_ratings si on le connaît
    if ($totalEpisodes > 0) {
        db_execute(
            'UPDATE season_ratings SET total_episodes = ?
             WHERE series_id = ? AND season_number = ? AND total_episodes IS NULL',
            [$totalEpisodes, $seriesId, $seasonNumber]
        );
        // Si la ligne n'existe pas encore pour cet user, on l'insère avec total_episodes
        db_execute(
            'INSERT INTO season_ratings
                (user_id, series_id, season_number, total_episodes, rating_weight, rated_at)
             VALUES (?, ?, ?, ?, 0, NOW())
             ON DUPLICATE KEY UPDATE
                 total_episodes = COALESCE(total_episodes, VALUES(total_episodes))',
            [$userId, $seriesId, $seasonNumber, $totalEpisodes]
        );
    }

    // 3. Recalcul saison depuis les épisodes
    _recalc_season_from_episodes($userId, $seriesId, $seasonNumber);

    // 4. Recalcul agrégat série
    _recalc_series_aggregate($userId, $seriesId);

    // 5. S'assurer que la série existe en BDD
    db_execute(
        'INSERT IGNORE INTO series (tmdb_id, title, created_at) VALUES (?, \'\', NOW())',
        [$seriesId]
    );

    // 6. ADN série utilisateur
    update_user_series_dna($userId);

    // 7. ADN collectif série
    update_series_dna($seriesId);

    // 8. Stats agrégées série
    $mDna = db_fetch_all('SELECT variance FROM series_dna WHERE series_id = ?', [$seriesId]);
    $totalVar = 0;
    foreach ($mDna as $md) { $totalVar += (float)$md['variance']; }
    $polarization = count($mDna) > 0 ? round($totalVar / count($mDna), 2) : 0;

    $voteCount = (int)(db_fetch_one(
        'SELECT COUNT(DISTINCT user_id) as cnt
         FROM season_ratings
         WHERE series_id = ? AND scores IS NOT NULL',
        [$seriesId]
    )['cnt'] ?? 0);

    db_execute(
        'UPDATE series SET total_votes = ?, polarization_index = ? WHERE id = ?',
        [$voteCount, $polarization, $seriesId]
    );

    // 9. exploration_rate utilisateur
    $totalRated = (int)(db_fetch_one(
        'SELECT (SELECT COUNT(*) FROM ratings WHERE user_id = ?)
              + (SELECT COUNT(DISTINCT series_id) FROM season_ratings WHERE user_id = ?)
         AS cnt',
        [$userId, $userId]
    )['cnt'] ?? 0);
    $explorationRate = $totalRated > 0 ? round(1 / (1 + log($totalRated)), 2) : 0.15;
    db_execute('UPDATE users SET exploration_rate = ? WHERE id = ?', [$explorationRate, $userId]);

    // 10. ADN pour le radar de mutation
    $userDnaRows = db_fetch_all(
        'SELECT criterio, avg_score FROM user_series_dna WHERE user_id = ?',
        [$userId]
    );
    $pDna = [];
    foreach ($userDnaRows as $ud) {
        $pDna[$ud['criterio']] = round($ud['avg_score'], 2);
    }

    db_commit();

} catch (Exception $e) {
    db_rollback();
    echo json_encode(['success' => false, 'message' => 'Erreur BDD : ' . $e->getMessage()]);
    exit;
}

notify_friends_dna_vote($userId, $seriesId, $finalScores, 'tv');
check_and_award_achievements($userId);
try { refresh_user_backdrop($userId); } catch (\Throwable $ignored) {}

echo json_encode([
    'success'        => true,
    'dna'            => $pDna,
    'season_number'  => $seasonNumber,
    'episode_number' => $episodeNumber,
]);