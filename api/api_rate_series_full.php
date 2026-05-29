<?php
/**
 * API_RATE_SERIES_FULL.PHP — Notation d'une série complète
 *
 * Applique la même note sur toutes les saisons connues dans season_ratings
 * (peu importe le user qui les a notées), en préservant les notes que
 * l'utilisateur courant a déjà saisies manuellement.
 *
 * Logique d'insertion : INSERT IGNORE sur (user_id, series_id, season_number)
 * → si l'user a déjà une ligne pour cette saison, on ne touche pas à sa note.
 * → si la saison n'existe que chez ORACLE (ou d'autres users), on crée la ligne.
 */

session_start();
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
$seriesId = (int)($_POST['series_id'] ?? 0);

if (!$seriesId) {
    echo json_encode(['success' => false, 'message' => 'ID Série manquant']);
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
$weight = $filledCount > 0 ? round($filledCount / count($criteria), 2) : 0.1;

// ── Récupérer les saisons connues pour cette série ────────────────────────────
// On prend les season_number distincts présents dans season_ratings,
// peu importe le user (ORACLE ou autres).
$knownSeasons = db_fetch_all(
    'SELECT DISTINCT season_number FROM season_ratings WHERE series_id = ? ORDER BY season_number ASC',
    [$seriesId]
);

if (empty($knownSeasons)) {
    echo json_encode(['success' => false, 'message' => 'Aucune saison connue pour cette série. ORACLE n\'a pas encore analysé cette série.']);
    exit;
}

// Contexte temporel
$h      = (int)date('G');
$d      = (int)date('N');
$slot   = ($h >= 5 && $h < 18) ? 'journee' : (($h >= 18 && $h < 23) ? 'soiree' : 'nuit');
$period = ($d >= 6) ? 'weekend' : 'semaine';

$scoresJson = json_encode($finalScores);
$insertedCount = 0;

try {
    db_begin();

    foreach ($knownSeasons as $row) {
        $seasonNumber = (int)$row['season_number'];

        // INSERT IGNORE : si l'user a déjà noté cette saison, on ne touche pas
        $result = db_execute(
            'INSERT IGNORE INTO season_ratings
                (user_id, series_id, season_number, scores, like_score, is_liked,
                 rating_weight, context_period, context_slot, rated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [$userId, $seriesId, $seasonNumber, $scoresJson,
             $likeScore, $isLiked ? 1 : 0, $weight, $period, $slot]
        );

        // db_execute retourne le nombre de lignes affectées
        if ($result > 0) $insertedCount++;
    }

    // Recalcul de l'agrégat série pour cet user
    _recalc_series_aggregate($userId, $seriesId);

    // Mise à jour ADN série utilisateur
    update_user_series_dna($userId);

    // Mise à jour ADN collectif de la série
    update_series_dna($seriesId);

    // Stats agrégées sur la table series
    $mDna = db_fetch_all('SELECT variance FROM series_dna WHERE series_id = ?', [$seriesId]);
    $totalVar = 0;
    foreach ($mDna as $md) { $totalVar += (float)$md['variance']; }
    $polarization = count($mDna) > 0 ? round($totalVar / count($mDna), 2) : 0;

    $voteCount = (int)(db_fetch_one(
        'SELECT COUNT(DISTINCT user_id) as cnt FROM season_ratings WHERE series_id = ? AND scores IS NOT NULL',
        [$seriesId]
    )['cnt'] ?? 0);

    db_execute(
        'UPDATE series SET total_votes = ?, polarization_index = ? WHERE id = ?',
        [$voteCount, $polarization, $seriesId]
    );

    // Mise à jour exploration_rate utilisateur
    $totalRated = (int)(db_fetch_one(
        'SELECT (SELECT COUNT(*) FROM ratings WHERE user_id = ?)
              + (SELECT COUNT(DISTINCT series_id) FROM season_ratings WHERE user_id = ?)
         AS cnt',
        [$userId, $userId]
    )['cnt'] ?? 0);
    $explorationRate = $totalRated > 0 ? round(1 / (1 + log($totalRated)), 2) : 0.15;
    db_execute('UPDATE users SET exploration_rate = ? WHERE id = ?', [$explorationRate, $userId]);

    // Retour ADN pour le radar de mutation
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

check_and_award_achievements($userId);
try { refresh_user_backdrop($userId); } catch (\Throwable $ignored) {}

echo json_encode([
    'success'        => true,
    'dna'            => $pDna,
    'seasons_total'  => count($knownSeasons),
    'seasons_added'  => $insertedCount,
    'seasons_kept'   => count($knownSeasons) - $insertedCount,
]);