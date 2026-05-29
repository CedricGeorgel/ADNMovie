<?php
/**
 * API_GROUP_ADN.PHP
 * Calcule l'ADN groupé des membres d'une session et trouve les films les plus proches.
 * GET ?room_id=X
 */
require_once 'auth_helpers.php';
start_persistent_session();
ini_set('display_errors', 0);

require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../functions/rooms_logic.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$userId = $_SESSION['user_id'];
$roomId = $_GET['room_id'] ?? '';

if (!$roomId) {
    echo json_encode(['success' => false, 'message' => 'room_id manquant']);
    exit;
}

$room = get_room($roomId);
if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Session introuvable']);
    exit;
}

if (!in_array($userId, $room['members'])) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

$criteriaList = ['complexite', 'previsibilite', 'intensite', 'malaise', 'stylisation', 'dynamique', 'depaysement', 'coherence'];
$members      = $room['members'];

// ── Construire l'ADN groupé (moyenne des user_dna de chaque membre) ────────
$groupScores  = array_fill_keys($criteriaList, 0.0);
$memberCounts = array_fill_keys($criteriaList, 0);

foreach ($members as $memberId) {
    $rows = db_fetch_all(
        'SELECT criterio, avg_score FROM user_dna WHERE user_id = ?',
        [$memberId]
    );
    foreach ($rows as $row) {
        if (array_key_exists($row['criterio'], $groupScores)) {
            $groupScores[$row['criterio']]  += (float) $row['avg_score'];
            $memberCounts[$row['criterio']] += 1;
        }
    }
}

$membersWithAdn = 0;
foreach ($criteriaList as $c) {
    if ($memberCounts[$c] > 0) {
        $groupScores[$c]  /= $memberCounts[$c];
        $membersWithAdn    = max($membersWithAdn, $memberCounts[$c]);
    }
}

if ($membersWithAdn === 0) {
    echo json_encode([
        'success'  => true,
        'no_data'  => true,
        'message'  => 'Aucun membre n\'a encore noté de films.',
    ]);
    exit;
}

// ── Charger tout le movie_dna en une seule requête ─────────────────────────
$allDnaRows = db_fetch_all(
    'SELECT movie_id, criterio, avg_score FROM movie_dna',
    []
);

$movieDnaMap = [];
foreach ($allDnaRows as $row) {
    $movieDnaMap[$row['movie_id']][$row['criterio']] = (float) $row['avg_score'];
}

// Distance euclidienne sur les 8 critères
function euclidean_dist(array $group, array $movie, array $criteria): float {
    $sum = 0.0;
    foreach ($criteria as $c) {
        $d    = ($group[$c] ?? 0.0) - ($movie[$c] ?? 0.0);
        $sum += $d * $d;
    }
    return sqrt($sum);
}

// ── Film le plus proche parmi les propositions ─────────────────────────────
$proposals       = get_session_proposals($roomId);
$closestProposal = null;
$minDistProp     = PHP_FLOAT_MAX;

foreach ($proposals as $prop) {
    $mid = (int) $prop['movie_id'];
    if (empty($movieDnaMap[$mid])) continue;
    $dist = euclidean_dist($groupScores, $movieDnaMap[$mid], $criteriaList);
    if ($dist < $minDistProp) {
        $minDistProp     = $dist;
        $movie           = db_fetch_one('SELECT tmdb_id, title, poster FROM movies WHERE tmdb_id = ?', [$mid]);
        $closestProposal = $movie
            ? ['id' => $mid, 'title' => $movie['title'], 'poster' => $movie['poster'], 'distance' => round($dist, 2)]
            : null;
    }
}

// ── Film le plus proche dans tout le catalogue ─────────────────────────────
$closestCatalogue = null;
$minDistCat       = PHP_FLOAT_MAX;

$allMovies = db_fetch_all(
    'SELECT DISTINCT m.tmdb_id, m.title, m.poster
     FROM movies m
     INNER JOIN movie_dna md ON md.movie_id = m.tmdb_id',
    []
);

foreach ($allMovies as $movie) {
    $mid = (int) $movie['tmdb_id'];
    if (empty($movieDnaMap[$mid])) continue;
    $dist = euclidean_dist($groupScores, $movieDnaMap[$mid], $criteriaList);
    if ($dist < $minDistCat) {
        $minDistCat       = $dist;
        $closestCatalogue = ['id' => $mid, 'title' => $movie['title'], 'poster' => $movie['poster'], 'distance' => round($dist, 2)];
    }
}

echo json_encode([
    'success'           => true,
    'group_adn'         => array_values($groupScores),
    'group_adn_assoc'   => $groupScores,
    'members_with_data' => $membersWithAdn,
    'total_members'     => count($members),
    'closest_proposal'  => $closestProposal,
    'closest_catalogue' => $closestCatalogue,
]);
