<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/achievements_logic.php';
require_once __DIR__ . '/../functions/ratings_logic.php';
require_once __DIR__ . '/../functions/series_aggregate_logic.php';
require_once __DIR__ . '/../functions/notifications_logic.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId      = $_SESSION['user_id'];
$action      = $_POST['action']       ?? 'rate';
$contentKind = $_POST['content_kind'] ?? 'movie'; // 'movie' | 'tv'

// Critères V3
$criteria = CRITERIA;

// ════════════════════════════════════════════════════════════════════════════
// ROUTAGE : Film vs Série
// ════════════════════════════════════════════════════════════════════════════

if ($contentKind === 'tv') {

    // ── Paramètres série ──────────────────────────────────────────────────
    $seriesId     = (int)($_POST['series_id']     ?? 0);
    $seasonNumber = (int)($_POST['season_number'] ?? 0);

    if (!$seriesId) {
        echo json_encode(['success' => false, 'message' => 'ID Série manquant']);
        exit;
    }

    // ── ACTION : seen / liked (Toggle rapide — niveau série) ──────────────
    if ($action === 'seen' || $action === 'liked') {
        $existing = db_fetch_one(
            'SELECT is_liked FROM series_ratings WHERE user_id = ? AND series_id = ?',
            [$userId, $seriesId]
        );

        if ($action === 'seen') {
            if ($existing) {
                echo json_encode(['success' => true, 'already_seen' => true]);
            } else {
                db_execute(
                    'INSERT IGNORE INTO series_ratings (user_id, series_id, rated_at) VALUES (?, ?, NOW())',
                    [$userId, $seriesId]
                );
                echo json_encode(['success' => true]);
            }
            exit;
        }

        if ($action === 'liked') {
            if ($existing) {
                $newLiked = $existing['is_liked'] ? 0 : 1;
                db_execute(
                    'UPDATE series_ratings SET is_liked = ? WHERE user_id = ? AND series_id = ?',
                    [$newLiked, $userId, $seriesId]
                );
                echo json_encode(['success' => true, 'is_liked' => (bool)$newLiked]);
            } else {
                db_execute(
                    'INSERT INTO series_ratings (user_id, series_id, is_liked, rated_at) VALUES (?, ?, 1, NOW())',
                    [$userId, $seriesId]
                );
                echo json_encode(['success' => true, 'is_liked' => true]);
            }
            exit;
        }
    }

    // ── ACTION : rate (Vote ADN par saison) ───────────────────────────────

    if ($seasonNumber <= 0) {
        echo json_encode(['success' => false, 'message' => 'Numéro de saison invalide']);
        exit;
    }

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

    $weight = $filledCount > 0 ? round($filledCount / count($criteria), 2) : 0.1;

    // Contexte temporel
    $h      = (int)date('G');
    $d      = (int)date('N');
    $slot   = ($h >= 5 && $h < 18) ? 'journee' : (($h >= 18 && $h < 23) ? 'soiree' : 'nuit');
    $period = ($d >= 6) ? 'weekend' : 'semaine';

    try {
        db_begin();

        // 1. Upsert du vote de saison
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
            [$userId, $seriesId, $seasonNumber, json_encode($finalScores),
             $likeScore, $isLiked ? 1 : 0, $weight, $period, $slot]
        );

        // 2. Recalcul de la note agrégée de la série pour cet utilisateur
        //    = moyenne des scores par critère sur toutes ses saisons notées
        _recalc_series_aggregate($userId, $seriesId);

        // 3. S'assurer que la série existe en BDD
        db_execute(
            'INSERT IGNORE INTO series (tmdb_id, title, created_at) VALUES (?, \'\', NOW())',
            [$seriesId]
        );

        // 4. Mise à jour ADN série utilisateur
        update_user_series_dna($userId);

        // 5. Mise à jour series_dna (ADN collectif de la série)
        update_series_dna($seriesId);

        // 6. Mise à jour des stats agrégées sur la table series
        $mDna = db_fetch_all(
            'SELECT variance FROM series_dna WHERE series_id = ?',
            [$seriesId]
        );
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

        // 7. Mise à jour exploration_rate utilisateur
        $totalRated = (int)(db_fetch_one(
            'SELECT (SELECT COUNT(*) FROM ratings WHERE user_id = ?)
                  + (SELECT COUNT(DISTINCT series_id) FROM season_ratings WHERE user_id = ?)
             AS cnt',
            [$userId, $userId]
        )['cnt'] ?? 0);
        $explorationRate = $totalRated > 0 ? round(1 / (1 + log($totalRated)), 2) : 0.15;
        db_execute('UPDATE users SET exploration_rate = ? WHERE id = ?', [$explorationRate, $userId]);

        // 8. Préparation du retour ADN pour le radar
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

    // Notifications amis + badges
    notify_friends_dna_vote($userId, $seriesId, $finalScores, 'tv');
    check_and_award_achievements($userId);
    try { refresh_user_backdrop($userId); } catch (\Throwable $ignored) {}

    echo json_encode([
        'success'       => true,
        'dna'           => $pDna,
        'season_number' => $seasonNumber,
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// FILM (logique originale inchangée)
// ════════════════════════════════════════════════════════════════════════════

$movieId = (int)($_POST['movie_id'] ?? 0);

if (!$movieId) {
    echo json_encode(['success' => false, 'message' => 'ID Film manquant']);
    exit;
}

// ── ACTION : seen / liked ─────────────────────────────────────────────────
if ($action === 'seen' || $action === 'liked') {
    $existing = db_fetch_one(
        'SELECT is_liked FROM ratings WHERE user_id = ? AND movie_id = ?',
        [$userId, $movieId]
    );

    if ($action === 'seen') {
        if ($existing) {
            echo json_encode(['success' => true, 'already_seen' => true]);
        } else {
            db_execute(
                'INSERT IGNORE INTO ratings (user_id, movie_id, rated_at) VALUES (?, ?, NOW())',
                [$userId, $movieId]
            );
            echo json_encode(['success' => true]);
        }
        exit;
    }

    if ($action === 'liked') {
        if ($existing) {
            $newLiked = $existing['is_liked'] ? 0 : 1;
            db_execute(
                'UPDATE ratings SET is_liked = ? WHERE user_id = ? AND movie_id = ?',
                [$newLiked, $userId, $movieId]
            );
            echo json_encode(['success' => true, 'is_liked' => (bool)$newLiked]);
        } else {
            db_execute(
                'INSERT INTO ratings (user_id, movie_id, is_liked, rated_at) VALUES (?, ?, 1, NOW())',
                [$userId, $movieId]
            );
            echo json_encode(['success' => true, 'is_liked' => true]);
        }
        exit;
    }
}

// ── ACTION : rate ─────────────────────────────────────────────────────────

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

$weight = $filledCount > 0 ? round($filledCount / count($criteria), 2) : 0.1;

$h      = (int)date('G');
$d      = (int)date('N');
$slot   = ($h >= 5 && $h < 18) ? 'journee' : (($h >= 18 && $h < 23) ? 'soiree' : 'nuit');
$period = ($d >= 6) ? 'weekend' : 'semaine';

try {
    db_begin();

    db_execute(
        'INSERT INTO ratings
            (user_id, movie_id, scores, like_score, is_liked, rating_weight, context_period, context_slot, rated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
             scores = VALUES(scores), like_score = VALUES(like_score), is_liked = VALUES(is_liked),
             rating_weight = VALUES(rating_weight), context_period = VALUES(context_period),
             context_slot = VALUES(context_slot), rated_at = NOW()',
        [$userId, $movieId, json_encode($finalScores), $likeScore, $isLiked ? 1 : 0, $weight, $period, $slot]
    );

    db_execute(
        "UPDATE recommendation_feedback
         SET outcome = 'rated', outcome_at = NOW(), is_active = 0
         WHERE user_id = ? AND movie_id = ? AND outcome = 'pending'",
        [$userId, $movieId]
    );

    db_execute('INSERT IGNORE INTO movies (tmdb_id, created_at) VALUES (?, NOW())', [$movieId]);

    update_movie_dna($movieId);
    update_user_dna($userId);

    $newFilmVotes = (int)(db_fetch_one(
        'SELECT COALESCE(MAX(vote_count), 0) AS cnt FROM user_dna WHERE user_id = ?',
        [$userId]
    )['cnt'] ?? 0);
    if ($newFilmVotes === 10) {
        db_execute(
            "UPDATE recommendation_feedback SET is_active = 0
             WHERE user_id = ? AND movie_id > 0 AND is_active = 1 AND outcome = 'pending'",
            [$userId]
        );
    }

    $mDna = db_fetch_all('SELECT variance FROM movie_dna WHERE movie_id = ?', [$movieId]);
    $totalVar = 0;
    foreach ($mDna as $md) { $totalVar += (float)$md['variance']; }
    $polarization = count($mDna) > 0 ? round($totalVar / count($mDna), 2) : 0;
    $voteCount = (int)(db_fetch_one(
        'SELECT COUNT(*) as cnt FROM ratings WHERE movie_id = ? AND scores IS NOT NULL',
        [$movieId]
    )['cnt'] ?? 0);

    db_execute(
        'UPDATE movies SET total_votes = ?, polarization_index = ? WHERE tmdb_id = ?',
        [$voteCount, $polarization, $movieId]
    );

    $totalRated = (int)(db_fetch_one(
        'SELECT COUNT(*) as cnt FROM ratings WHERE user_id = ?',
        [$userId]
    )['cnt'] ?? 0);
    $explorationRate = $totalRated > 0 ? round(1 / (1 + log($totalRated)), 2) : 0.15;
    db_execute('UPDATE users SET exploration_rate = ? WHERE id = ?', [$explorationRate, $userId]);

    $userDnaRows = db_fetch_all('SELECT criterio, avg_score FROM user_dna WHERE user_id = ?', [$userId]);
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

notify_friends_dna_vote($userId, $movieId, $finalScores, 'movie');
check_and_award_achievements($userId);
try { refresh_user_backdrop($userId); } catch (\Throwable $ignored) {}

echo json_encode(['success' => true, 'dna' => $pDna]);