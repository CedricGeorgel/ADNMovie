<?php
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/movie-card.php';
require_once 'components/search-bar.php';

check_auth();

$currentUserId = $_SESSION['user_id'];
$currentUser   = get_user_by_id($currentUserId);

$targetId   = $_GET['id'] ?? $currentUserId;
$targetUser = get_user_by_id($targetId);

if (!$targetUser || !$currentUser) { header('Location: index.php'); exit; }

$isOwnCollection = ((string)$targetId === (string)$currentUserId);
$pageTitle = $isOwnCollection ? "Ma Collection" : "Archives de " . h($targetUser['username']);
$pageDesc  = $isOwnCollection ? "Retrouvez votre historique et vos coups de cœur." : "Exploration du registre de " . h($targetUser['username']) . ".";

$filter = $_GET['filter'] ?? 'liked';
if (!in_array($filter, ['liked', 'seen', 'wishlist'])) $filter = 'liked';

$privacySettings = json_decode($targetUser['privacy_settings'] ?? '{}', true) ?: [];
$wishlistPublic  = $privacySettings['wishlist_public'] ?? true;
if ($filter === 'wishlist' && !$wishlistPublic && !$isOwnCollection) $filter = 'liked';

$showRating = ($filter === 'seen');

$criteriaLabels = [
    'complexite'    => 'Complexité',
    'previsibilite' => 'Prévisib.',
    'intensite'     => 'Intensité',
    'malaise'       => 'Malaise',
    'stylisation'   => 'Stylisation',
    'dynamique'     => 'Dynamisme',
    'depaysement'   => 'Dépaysement',
    'coherence'     => 'Cohérence',
];

// ── Films ─────────────────────────────────────────────────────────────────────
if ($filter === 'liked') {
    $movies = db_fetch_all(
        'SELECT m.title, m.poster, m.year, m.tmdb_id AS id,
                r.rated_at, r.scores, r.is_liked, r.like_score
         FROM ratings r JOIN movies m ON m.tmdb_id = r.movie_id
         WHERE r.user_id = ? AND r.is_liked = 1
         ORDER BY r.rated_at DESC',
        [$targetId]
    );
} elseif ($filter === 'wishlist') {
    $movies = db_fetch_all(
        'SELECT m.title, m.poster, m.year, m.tmdb_id AS id,
                NULL AS rated_at, NULL AS scores, NULL AS is_liked, NULL AS like_score
         FROM user_wishlist w
         JOIN movies m ON m.tmdb_id = w.movie_id
         WHERE w.user_id = ? AND w.content_type = "movie"
         ORDER BY w.added_at DESC',
        [$targetId]
    );
} else {
    $movies = db_fetch_all(
        'SELECT m.title, m.poster, m.year, m.tmdb_id AS id,
                r.rated_at, r.scores, r.is_liked, r.like_score
         FROM ratings r JOIN movies m ON m.tmdb_id = r.movie_id
         WHERE r.user_id = ?
         ORDER BY r.rated_at DESC',
        [$targetId]
    );
}

// ── Séries ────────────────────────────────────────────────────────────────────
$series = [];
if ($filter === 'wishlist') {
    $series = db_fetch_all(
        'SELECT s.title, s.poster, s.year, s.tmdb_id AS id,
                NULL AS rated_at, NULL AS scores, NULL AS is_liked, NULL AS like_score
         FROM user_wishlist w
         JOIN series s ON s.id = w.series_id
         WHERE w.user_id = ? AND w.content_type = "series"
         ORDER BY w.added_at DESC',
        [$targetId]
    );
} elseif ($filter === 'liked') {
    $series = db_fetch_all(
        'SELECT s.title, s.poster, s.year, s.tmdb_id AS id,
                sr.rated_at, sr.scores, sr.is_liked, sr.like_score
         FROM series_ratings sr JOIN series s ON s.id = sr.series_id
         WHERE sr.user_id = ? AND sr.is_liked = 1
         ORDER BY sr.rated_at DESC',
        [$targetId]
    );
} else {
    $series = db_fetch_all(
        'SELECT s.title, s.poster, s.year, s.tmdb_id AS id,
                sr.rated_at, sr.scores, sr.is_liked, sr.like_score
         FROM series_ratings sr JOIN series s ON s.id = sr.series_id
         WHERE sr.user_id = ?
         ORDER BY sr.rated_at DESC',
        [$targetId]
    );
}

$totalMovies = count($movies);
$totalSeries = count($series);
$totalAll    = $totalMovies + $totalSeries;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <link rel="icon" href="/assets/Icons/Logo2.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/assets/Icons/Logo3.png">
    <meta property="og:site_name" content="ADN Movie">
    <meta property="og:type" content="website">
    <meta property="og:title" content="ADN Movie">
    <meta property="og:description" content="Découvrez vos résonances cinématographiques">
    <meta property="og:image" content="https://adnmovie.fr/assets/Icons/Named_logo1.png">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:image" content="https://adnmovie.fr/assets/Icons/Named_logo1.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime('assets/style.css') ?>">
    <?php if (function_exists('renderScripts')) renderScripts(); ?>
    <meta name="theme-color" content="#050505">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="page-collection">
<div class="container">
    <?php renderHeader($currentUser); ?>
    <?php renderNav('collection'); ?>

    <main>
        <div class="rooms-header" style="text-align:center;margin-bottom:20px;">
            <h1><?= $pageTitle ?></h1>
            <p><?= $pageDesc ?></p>
        </div>

        <div class="search-area-collection" style="margin-bottom:24px;">
            <?php renderSearchBar("Filtrer cette collection..."); ?>
        </div>

        <!-- Filtre liked/seen/wishlist -->
        <div class="dash-links" style="max-width:550px;margin:0 auto 16px auto;display:flex;gap:15px;">
            <a href="collection.php?id=<?= h($targetId) ?>&filter=liked"    class="btn-base <?= $filter === 'liked'    ? 'active' : '' ?>" style="flex:1;text-align:center;">❤️ Likés</a>
            <a href="collection.php?id=<?= h($targetId) ?>&filter=seen"     class="btn-base <?= $filter === 'seen'     ? 'active' : '' ?>" style="flex:1;text-align:center;">✓ Vus</a>
            <?php if ($isOwnCollection || $wishlistPublic): ?>
            <a href="collection.php?id=<?= h($targetId) ?>&filter=wishlist" class="btn-base <?= $filter === 'wishlist' ? 'active' : '' ?>" style="flex:1;text-align:center;">🔖 Watchlist</a>
            <?php endif; ?>
        </div>

        <!-- Filtre type (JS) -->
        <div style="max-width:550px;margin:0 auto 32px auto;display:flex;gap:10px;justify-content:center;">
            <button class="btn-base active" id="type-all"    onclick="filterType('all')"    style="flex:1;text-align:center;font-size:0.75rem;">Tout</button>
            <button class="btn-base"        id="type-movies" onclick="filterType('movies')" style="flex:1;text-align:center;font-size:0.75rem;">🎬 Films</button>
            <button class="btn-base"        id="type-series" onclick="filterType('series')" style="flex:1;text-align:center;font-size:0.75rem;">📺 Séries</button>
        </div>

        <div id="collectionGrid" class="movie-grid">
            <?php if ($totalAll === 0): ?>
                <div class="empty-state" style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-dim);">
                    <p>Aucun contenu dans cette catégorie pour le moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($movies as $movie): ?>
                    <div class="collection-item" data-type="movie">
                        <?php renderMovieCard($movie, [
                            'show_badges'    => true,
                            'content_type'   => 'movie',
                            'rating_footer'  => $showRating ? array_merge($movie, ['criteria_labels' => $criteriaLabels]) : null,
                        ]); ?>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($series as $s): ?>
                    <div class="collection-item" data-type="series">
                        <?php renderMovieCard($s, [
                            'show_badges'    => false,
                            'content_type'   => 'tv',
                            'rating_footer'  => $showRating ? array_merge($s, ['criteria_labels' => $criteriaLabels]) : null,
                        ]); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php renderFooter(); ?>

<script src="/assets/js/collection.js?v=2" defer></script>
</body>
</html>