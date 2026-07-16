<?php
/**
 * API_ADMIN_ORACLE_LIKES.PHP
 * Backfill Oracle like_score sur tous les films à partir des votes TMDB.
 * Ne touche pas aux scores ADN existants.
 * Admin uniquement.
 */
require_once __DIR__ . '/../functions/utils.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../functions/core_db.php';
require_once __DIR__ . '/../api/api_oracle.php';

header('Content-Type: application/json; charset=utf-8');

start_persistent_session();
require_role('admin');

set_time_limit(0);
ini_set('max_execution_time', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST uniquement']);
    exit;
}

$pdo = getPDO();

$movies = $pdo->query("SELECT id AS tmdb_id FROM movies ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$total   = count($movies);
$updated = 0;
$skipped = 0;
$errors  = 0;

foreach ($movies as $row) {
    $tmdbId = (int)$row['tmdb_id'];

    $url      = "https://api.themoviedb.org/3/movie/{$tmdbId}?api_key=" . TMDB_API_KEY . "&language=fr-FR";
    $response = @file_get_contents($url);

    if (!$response) {
        $errors++;
        usleep(200000);
        continue;
    }

    $data = json_decode($response, true);
    if (empty($data['id'])) {
        $errors++;
        usleep(200000);
        continue;
    }

    $voteAvg   = isset($data['vote_average']) ? (float)$data['vote_average'] : null;
    $voteCount = isset($data['vote_count'])   ? (int)$data['vote_count']     : 0;

    $likeScore = _oracle_vote_to_like_score($voteAvg, $voteCount);
    $isLiked   = $likeScore !== null ? ($likeScore > 0 ? 1 : 0) : null;

    if ($likeScore === null) {
        $skipped++;
        usleep(50000);
        continue;
    }

    try {
        _oracle_ensure_user($pdo);

        $pdo->prepare(
            "INSERT INTO ratings (user_id, movie_id, scores, like_score, is_liked, rating_weight, rated_at)
             VALUES (?, ?, NULL, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE like_score = VALUES(like_score), is_liked = VALUES(is_liked)"
        )->execute([ORACLE_USER_ID, $tmdbId, $likeScore, $isLiked, ORACLE_WEIGHT]);

        $updated++;
    } catch (Exception $e) {
        $errors++;
    }

    usleep(100000); // 100ms entre appels TMDB
}

echo json_encode([
    'success' => true,
    'total'   => $total,
    'updated' => $updated,
    'skipped' => $skipped,
    'errors'  => $errors,
]);
