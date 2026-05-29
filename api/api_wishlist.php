<?php
/**
 * API_WISHLIST.PHP
 * Toggle film ou série dans la wishlist de l'utilisateur.
 * POST : movie_id  (film)
 *   OU  series_id  (série, id local BDD)
 * Retourne : { success, wishlisted (bool) }
 */
require_once 'auth_helpers.php';
start_persistent_session();
ini_set('display_errors', 0);
require_once __DIR__ . '/../functions/core_db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

$userId = $_SESSION['user_id'];

$movieId  = isset($_POST['movie_id'])  && $_POST['movie_id']  !== '' ? (int)$_POST['movie_id']  : null;
$seriesId = isset($_POST['series_id']) && $_POST['series_id'] !== '' ? (int)$_POST['series_id'] : null;

if (!$movieId && !$seriesId) {
    echo json_encode(['success' => false, 'message' => 'movie_id ou series_id manquant']);
    exit;
}

if ($seriesId) {
    // ── Série ─────────────────────────────────────────────────────────
    $exists = db_fetch_one(
        'SELECT 1 FROM user_wishlist WHERE user_id = ? AND series_id = ?',
        [$userId, $seriesId]
    );
    if ($exists) {
        db_execute(
            'DELETE FROM user_wishlist WHERE user_id = ? AND series_id = ?',
            [$userId, $seriesId]
        );
        echo json_encode(['success' => true, 'wishlisted' => false]);
    } else {
        db_execute(
            'INSERT IGNORE INTO user_wishlist (user_id, series_id, content_type) VALUES (?, ?, "series")',
            [$userId, $seriesId]
        );
        echo json_encode(['success' => true, 'wishlisted' => true]);
    }
} else {
    // ── Film ──────────────────────────────────────────────────────────
    $exists = db_fetch_one(
        'SELECT 1 FROM user_wishlist WHERE user_id = ? AND movie_id = ?',
        [$userId, $movieId]
    );
    if ($exists) {
        db_execute(
            'DELETE FROM user_wishlist WHERE user_id = ? AND movie_id = ?',
            [$userId, $movieId]
        );
        echo json_encode(['success' => true, 'wishlisted' => false]);
    } else {
        db_execute(
            'INSERT IGNORE INTO user_wishlist (user_id, movie_id, content_type) VALUES (?, ?, "movie")',
            [$userId, $movieId]
        );
        echo json_encode(['success' => true, 'wishlisted' => true]);
    }
}