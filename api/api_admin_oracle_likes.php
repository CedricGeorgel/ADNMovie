<?php
/**
 * API_ADMIN_ORACLE_LIKES.PHP
 * Backfill Oracle like_score en batches depuis les votes TMDB.
 * Le JS chaîne les appels automatiquement — chaque requête traite LIMIT films.
 * Admin uniquement.
 */
require_once __DIR__ . '/../functions/utils.php';

header('Content-Type: application/json; charset=utf-8');

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST uniquement']);
    exit;
}

set_time_limit(120);

$offset = max(0, (int)($_POST['offset'] ?? 0));
$limit  = min(50, max(1, (int)($_POST['limit'] ?? 50)));

$pdo = getPDO();

$total  = (int)db_fetch_one("SELECT COUNT(*) AS cnt FROM movies")['cnt'];

$stmt = $pdo->prepare("SELECT tmdb_id FROM movies ORDER BY tmdb_id LIMIT ? OFFSET ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$movies = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
$skipped = 0;
$errors  = 0;

_oracle_ensure_user($pdo);

foreach ($movies as $row) {
    $tmdbId = (int)$row['tmdb_id'];

    $url      = "https://api.themoviedb.org/3/movie/{$tmdbId}?api_key=" . TMDB_API_KEY;
    $response = @file_get_contents($url);

    if (!$response) {
        $errors++;
        usleep(100000);
        continue;
    }

    $data = json_decode($response, true);
    if (empty($data['id'])) {
        $errors++;
        usleep(100000);
        continue;
    }

    $voteAvg   = isset($data['vote_average']) ? (float)$data['vote_average'] : null;
    $voteCount = isset($data['vote_count'])   ? (int)$data['vote_count']     : 0;

    $likeScore = _oracle_vote_to_like_score($voteAvg, $voteCount);
    $isLiked   = $likeScore !== null ? ($likeScore > 0 ? 1 : 0) : null;

    if ($likeScore === null) {
        $skipped++;
        usleep(25000);
        continue;
    }

    try {
        $pdo->prepare(
            "INSERT INTO ratings (user_id, movie_id, scores, like_score, is_liked, rating_weight, rated_at)
             VALUES (?, ?, NULL, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE like_score = VALUES(like_score), is_liked = VALUES(is_liked)"
        )->execute([ORACLE_USER_ID, $tmdbId, $likeScore, $isLiked, ORACLE_WEIGHT]);

        $updated++;
    } catch (Exception $e) {
        $errors++;
    }

    usleep(50000); // 50ms entre appels TMDB
}

$newOffset = $offset + count($movies);

echo json_encode([
    'success' => true,
    'total'   => $total,
    'offset'  => $newOffset,
    'done'    => $newOffset >= $total,
    'updated' => $updated,
    'skipped' => $skipped,
    'errors'  => $errors,
]);
