<?php
/**
 * API_RATE_SERIES.PHP — Notation d'une série TV
 * Sauvegarde dans series_ratings + met à jour user_series_dna.
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../functions/ratings_logic.php';
require_once __DIR__ . '/../functions/notifications_logic.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$userId = $_SESSION['user_id'];
$tmdbId = (int)($_POST['movie_id'] ?? 0); // même champ que le modal film
$criteria = CRITERIA;

if (!$tmdbId) {
    echo json_encode(['success' => false, 'message' => 'ID Série manquant']);
    exit;
}

// ── Résoudre / créer l'entrée dans la table series ────────────────────────────
$series = db_fetch_one('SELECT id FROM series WHERE tmdb_id = ?', [$tmdbId]);
if (!$series) {
    db_execute('INSERT IGNORE INTO series (tmdb_id, created_at) VALUES (?, NOW())', [$tmdbId]);
    $series = db_fetch_one('SELECT id FROM series WHERE tmdb_id = ?', [$tmdbId]);
}

if (!$series) {
    echo json_encode(['success' => false, 'message' => 'Impossible de résoudre la série en BDD']);
    exit;
}
$seriesId = (int)$series['id'];

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
$weight = $filledCount > 0 ? round($filledCount / count($criteria), 2) : 0.1;

// ── Sauvegarde ────────────────────────────────────────────────────────────────
try {
    db_begin();

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
        [$userId, $seriesId, json_encode($finalScores), $likeScore, $isLiked ? 1 : 0, $weight]
    );

    // Fermeture de la boucle feedback recommandations séries
    db_execute(
        "UPDATE recommendation_feedback
         SET outcome = 'rated', outcome_at = NOW(), is_active = 0
         WHERE user_id = ? AND movie_id = ? AND outcome = 'pending'",
        [$userId, -$tmdbId]
    );

    // Compteur de votes + polarisation simplifiée sur la série
    $voteCount = (int)db_fetch_one(
        'SELECT COUNT(*) AS cnt FROM series_ratings WHERE series_id = ? AND scores IS NOT NULL',
        [$seriesId]
    )['cnt'];
    db_execute('UPDATE series SET total_votes = ? WHERE id = ?', [$voteCount, $seriesId]);

    // ADN série de l'utilisateur
    update_user_series_dna($userId);

    // Si l'utilisateur vient d'atteindre exactement 10 votes séries, ses recos séries
    // étaient peut-être basées sur l'ADN film (fallback) → on les invalide pour recalcul.
    $newSeriesVotes = (int)(db_fetch_one(
        'SELECT COALESCE(MAX(vote_count), 0) AS cnt FROM user_series_dna WHERE user_id = ?', [$userId]
    )['cnt'] ?? 0);
    if ($newSeriesVotes === 10) {
        db_execute(
            "UPDATE recommendation_feedback SET is_active = 0
             WHERE user_id = ? AND movie_id < 0 AND is_active = 1 AND outcome = 'pending'",
            [$userId]
        );
    }

    db_commit();

} catch (Exception $e) {
    db_rollback();
    echo json_encode(['success' => false, 'message' => 'Erreur BDD : ' . $e->getMessage()]);
    exit;
}

// ── Retour : ADN série pour le radar de mutation ───────────────────────────────
$userDnaRows = db_fetch_all(
    'SELECT criterio, avg_score FROM user_series_dna WHERE user_id = ?',
    [$userId]
);
$pDna = [];
foreach ($userDnaRows as $ud) {
    $pDna[$ud['criterio']] = round($ud['avg_score'], 2);
}

// Notification aux amis si la série résonne >= 80% avec leur ADN série
notify_friends_dna_vote($userId, $tmdbId, $finalScores, 'series');

echo json_encode(['success' => true, 'dna' => $pDna]);
