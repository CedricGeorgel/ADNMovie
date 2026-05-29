<?php
/**
 * API_ANOMALY_MOOD.PHP
 * Retourne des films très aimés par la communauté qui contredisent l'ADN de l'utilisateur.
 * Logique : ADN inversé (scores × -1) × taux de like élevé.
 */
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/auth_helpers.php';
start_persistent_session();
require_once __DIR__ . '/../functions/formatting.php';
require_once __DIR__ . '/../components/movie-card.php';

if (!isset($_SESSION['user_id'])) {
    echo '<p style="font-family:monospace;font-size:0.75rem;color:#ff0055;text-align:center;padding:40px 0;">'
       . '&gt; ACCÈS REFUSÉ — Connexion requise pour accéder à l\'anomalie génomique.</p>';
    exit;
}

$userId   = $_SESSION['user_id'];
$criteria = ['complexite','previsibilite','intensite','malaise',
             'stylisation','dynamique','depaysement','coherence'];

// ── 1. ADN utilisateur ────────────────────────────────────────────────────
$myRows = db_fetch_all('SELECT criterio, avg_score, vote_count FROM user_dna WHERE user_id = ?', [$userId]);
if (empty($myRows)) {
    echo '<p style="font-family:monospace;font-size:0.75rem;color:#ff0055;text-align:center;padding:40px 0;">'
       . '&gt; ADN INSUFFISANT — Analyse davantage de films pour calibrer ton génome.</p>';
    exit;
}

$maxVotes = max(array_column($myRows, 'vote_count'));
if ($maxVotes < 10) {
    echo '<p style="font-family:monospace;font-size:0.75rem;color:#ff0055;text-align:center;padding:40px 0;">'
       . '&gt; ADN INSUFFISANT — Analyse davantage de films pour calibrer ton génome.</p>';
    exit;
}

$myDna = array_column($myRows, 'avg_score', 'criterio');

// ── 2. Inversion de l'ADN ────────────────────────────────────────────────
$invertedDna = [];
foreach ($criteria as $c) {
    $invertedDna[$c] = -(float)($myDna[$c] ?? 0);
}

// Norme du vecteur inversé (pour cosinus)
$normMe = 0;
foreach ($criteria as $c) { $v = $invertedDna[$c]; $normMe += $v * $v; }
if ($normMe == 0) { echo ''; exit; }

// ── 3. Films déjà vus ────────────────────────────────────────────────────
$seen = db_fetch_all('SELECT movie_id FROM ratings WHERE user_id = ?', [$userId]);
$seenIds = array_column($seen, 'movie_id');

// ── 4. Films très aimés (taux like >= 40%, min 5 votes) ──────────────────
$liked = db_fetch_all(
    "SELECT movie_id,
            ROUND(SUM(is_liked) / COUNT(*), 3) AS like_rate,
            COUNT(*) AS vote_cnt
     FROM ratings
     WHERE scores IS NOT NULL
     GROUP BY movie_id
     HAVING vote_cnt >= 2 AND like_rate >= 0.4
     ORDER BY like_rate DESC
     LIMIT 300",
    []
);

if (empty($liked)) { echo ''; exit; }

$likedIds      = array_column($liked, 'movie_id');
$likeRateById  = array_column($liked, 'like_rate', 'movie_id');

// Exclure films déjà vus
$candidateIds = array_diff($likedIds, $seenIds);
if (empty($candidateIds)) { echo ''; exit; }

// ── 5. movie_dna + métadonnées pour ces films ────────────────────────────
$placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
$rows = db_fetch_all(
    "SELECT m.tmdb_id, m.title, m.poster, m.year,
            md.criterio, md.avg_score, md.sample_size
     FROM movies m
     JOIN movie_dna md ON md.movie_id = m.tmdb_id
     WHERE m.tmdb_id IN ($placeholders)",
    array_values($candidateIds)
);

if (empty($rows)) { echo ''; exit; }

// ── 6. Calcul de similarité avec l'ADN inversé ───────────────────────────
$films = [];
foreach ($rows as $row) {
    $id = $row['tmdb_id'];
    if (!isset($films[$id])) {
        $films[$id] = [
            'id'        => $id,
            'title'     => $row['title'],
            'poster'    => $row['poster'],
            'year'      => (int)$row['year'],
            'like_rate' => (float)($likeRateById[$id] ?? 0),
            'dna'       => [],
        ];
    }
    $films[$id]['dna'][$row['criterio']] = [
        'avg'         => (float)$row['avg_score'],
        'sample_size' => (int)$row['sample_size'],
    ];
}

$candidates = [];
foreach ($films as $film) {
    $totalScore  = 0.0;
    $totalWeight = 0.0;

    foreach ($criteria as $c) {
        if (!isset($film['dna'][$c])) continue;
        $filmAvg    = $film['dna'][$c]['avg'];
        $sampleSize = $film['dna'][$c]['sample_size'];
        $reliability = $sampleSize / ($sampleSize + 4);
        $proximity  = max(0.0, 1.0 - abs($invertedDna[$c] - $filmAvg) / 20.0);
        $totalScore  += $proximity * $reliability;
        $totalWeight += $reliability;
    }

    if ($totalWeight < 1.0) continue;

    $matchScore = ($totalScore / $totalWeight) * 100;

    // Score final : résonance inversée × taux de like
    $finalScore = $matchScore * (0.5 + $film['like_rate'] * 0.5);

    $candidates[] = [
        'id'         => $film['id'],
        'title'      => $film['title'],
        'poster'     => $film['poster'],
        'year'       => $film['year'],
        'score'      => round($finalScore),
        'like_rate'  => $film['like_rate'],
    ];
}

if (empty($candidates)) { echo ''; exit; }

usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

// ── 7. Rendu des 3 meilleurs ─────────────────────────────────────────────
$best = $candidates[0];
$likeLabel = round($best['like_rate'] * 100) . '% aimé · ANOMALIE';
renderMovieCard($best, [
    'extra_label' => $likeLabel,
    'is_reco'     => true,
]);
