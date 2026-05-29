<?php
/**
 * API_MOOD.PHP
 */

session_start();
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/formatting.php';
require_once __DIR__ . '/../components/movie-card.php';

// Films déjà vus par l'utilisateur connecté
$seenMovieIds    = [];
$subscribedIds   = [];
$filterByPlatform = !empty($_GET['my_platforms']) && isset($_SESSION['user_id']);

if (isset($_SESSION['user_id'])) {
    $seen = db_fetch_all(
        'SELECT movie_id FROM ratings WHERE user_id = ?',
        [$_SESSION['user_id']]
    );
    $seenMovieIds = array_column($seen, 'movie_id');

    if ($filterByPlatform) {
        $u = db_fetch_one('SELECT user_platforms FROM users WHERE id = ?', [$_SESSION['user_id']]);
        $subscribedIds = array_map('intval', json_decode($u['user_platforms'] ?? '[]', true) ?: []);
    }
}

// Si filtre plateforme actif : on charge les tmdb_id disponibles sur les abos de l'user
$platformMovieIds = null;
if ($filterByPlatform && !empty($subscribedIds)) {
    $allMovies = db_fetch_all('SELECT tmdb_id, providers_data FROM movies WHERE providers_data IS NOT NULL');
    $platformMovieIds = [];
    foreach ($allMovies as $m) {
        $data = json_decode($m['providers_data'], true) ?: [];
        $flatIds = array_column($data['flatrate'] ?? [], 'provider_id');
        if (array_intersect($subscribedIds, $flatIds)) {
            $platformMovieIds[] = (int)$m['tmdb_id'];
        }
    }
}

$energie = max(0, min(10, (float)($_GET['energie'] ?? 5)));
$emotion = max(0, min(10, (float)($_GET['emotion'] ?? 5)));
$realite = max(0, min(10, (float)($_GET['realite'] ?? 5)));

// Remapping 0-10 → -10/+10 pour correspondre à l'échelle movie_dna (Oracle -10/+10)
// 0 → -10  |  5 → 0 (neutre)  |  10 → +10
$e  = ($energie - 5) * 2;
$em = ($emotion - 5) * 2;
$r  = ($realite - 5) * 2;

$target = [
    // Énergie pure → rythme
    'dynamique'     => $e,
    // Intensité = énergie + charge émotionnelle
    'intensite'     => ($e + $em) / 2,
    // Réalité → univers du film (realite élevée = peu de dépaysement)
    'depaysement'   => -$r,
    'coherence'     => $r,
    // Émotion directement sur malaise
    'malaise'       => $em,
    // Profondeur narrative = émotion + ancrage dans le réel
    'complexite'    => ($em + $r) / 2,
    // Légèreté → prévisibilité (emotion faible = film prévisible/fun)
    'previsibilite' => -$em,
    // Neutre, peu discriminant
    'stylisation'   => 0.0,
];

$weights = [
    'dynamique'     => 1.5,
    'intensite'     => 2.0,  // fortement discriminant (drame émotionnel vs action vs comédie)
    'depaysement'   => 1.5,
    'coherence'     => 0.8,  // moins discriminant — un film léger peut être incohérent ou non
    'malaise'       => 2.0,  // critère le plus direct pour fun vs drame
    'complexite'    => 0.8,
    'previsibilite' => 0.8,
    'stylisation'   => 0.3,
];

$rows = db_fetch_all(
    "SELECT m.tmdb_id, m.title, m.poster, m.year,
            md.criterio, md.avg_score, md.sample_size
     FROM movies m
     JOIN movie_dna md ON md.movie_id = m.tmdb_id
     WHERE m.total_votes > 0",
    []
);

if (empty($rows)) { echo ''; exit; }

// Groupement par film + exclusion des films déjà vus
$films = [];
foreach ($rows as $row) {
    $id = $row['tmdb_id'];
    if (in_array($id, $seenMovieIds)) continue;
    if ($platformMovieIds !== null && !in_array($id, $platformMovieIds)) continue;
    if (!isset($films[$id])) {
        $films[$id] = [
            'id'     => $id,
            'title'  => $row['title'],
            'poster' => $row['poster'],
            'year'   => $row['year'],
            'dna'    => [],
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

    foreach ($target as $criterio => $targetVal) {
        if (!isset($film['dna'][$criterio])) continue;

        $filmAvg     = $film['dna'][$criterio]['avg'];
        $sampleSize  = $film['dna'][$criterio]['sample_size'];
        $reliability = $sampleSize / ($sampleSize + 4);
        $w           = ($weights[$criterio] ?? 1.0) * $reliability;
        $proximity   = max(0.0, 1.0 - (abs($targetVal - $filmAvg) / 20.0));
        $totalScore  += $proximity * $w;
        $totalWeight += $w;
    }

    if ($totalWeight < 1.0) continue;

    $candidates[] = [
        'id'     => $film['id'],
        'title'  => $film['title'],
        'poster' => $film['poster'],
        'year'   => (int)$film['year'],
        'score'  => round(($totalScore / $totalWeight) * 100),
    ];
}

if (empty($candidates)) { echo ''; exit; }

usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

$best = $candidates[0];
renderMovieCard($best, [
    'extra_label' => $best['score'] . '% MATCH',
    'is_reco'     => true,
]);