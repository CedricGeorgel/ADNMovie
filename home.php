<?php

require_once 'functions/utils.php';
require_once 'functions/api_tmdb.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/search-bar.php';
require_once 'components/movie-card.php';
require_once 'functions/core_db.php'; // Sécurité : ajout explicite de la connexion BDD

$currentUser = isset($_SESSION['user_id']) ? get_user_by_id($_SESSION['user_id']) : null;
$userId      = $_SESSION['user_id'] ?? null;

// ── EN CE MOMENT AU CINÉMA (Via table locale alimentée par le CRON) ───────────
$nowPlayingRows = db_fetch_all(
    "SELECT tmdb_id AS id, title, poster_path, release_date 
     FROM movies_now_playing 
     ORDER BY release_date DESC 
     LIMIT 200"
);

$nowPlaying = [];
foreach ($nowPlayingRows as $m) {
    $nowPlaying[] = [
        'id'     => $m['id'],
        'title'  => $m['title'],
        'poster' => $m['poster_path'] ? 'https://image.tmdb.org/t/p/w300' . $m['poster_path'] : null,
        'year'   => $m['release_date'] ? substr($m['release_date'], 0, 4) : '',
    ];
}

// ── POLARISATION ──────────────────────────────────────────────────────────────
$agitating = db_fetch_all(
    "SELECT DISTINCT m.tmdb_id AS id, m.title, m.poster, m.year, m.polarization_index
     FROM movies m
     INNER JOIN ratings r ON r.movie_id = m.tmdb_id
     WHERE m.polarization_index > 2
       AND m.total_votes >= 3
       AND r.rated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     ORDER BY m.polarization_index DESC
     LIMIT 12"
);

// ── ANGLES MORTS ──────────────────────────────────────────────────────────────
$blindSpots = db_fetch_all(
    "SELECT m.tmdb_id AS id, m.title, m.poster, m.year, m.total_votes,
            AVG(ABS(md.avg_score)) AS adn_strength
     FROM movies m
     JOIN movie_dna md ON md.movie_id = m.tmdb_id
     WHERE m.total_votes < 5 AND m.total_votes > 0
     GROUP BY m.tmdb_id
     HAVING adn_strength > 4
     ORDER BY adn_strength DESC
     LIMIT 12"
);

// ── NOUVEAUX ÉPISODES (séries suivies par l'utilisateur) ─────────────────────
$recentSeriesEpisodes = [];
if ($userId) {
    $recentSeriesEpisodes = db_fetch_all(
        "SELECT s.tmdb_id AS id, s.title, s.poster, s.year,
                se.season_number, se.episode_number, se.air_date, se.name AS episode_name
         FROM user_series_follow f
         JOIN series s ON s.id = f.series_id
         JOIN series_episodes se ON se.series_id = f.series_id
         WHERE f.user_id = ?
           AND f.is_ended = 0
           AND se.air_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()
         ORDER BY se.air_date DESC
         LIMIT 20",
        [$userId]
    );
}

// ── CLASSIQUES ────────────────────────────────────────────────────────────────
$classics = db_fetch_all(
    "SELECT m.tmdb_id AS id, m.title, m.poster, m.year, m.total_votes,
            AVG(ABS(md.avg_score)) AS adn_strength
     FROM movies m
     JOIN movie_dna md ON md.movie_id = m.tmdb_id
     WHERE m.total_votes >= 5
     GROUP BY m.tmdb_id
     HAVING adn_strength > 5
     ORDER BY m.total_votes DESC, adn_strength DESC
     LIMIT 12"
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bibliothèque</title>
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
    <link rel="stylesheet" href="assets/library.css?v=<?= filemtime('assets/library.css') ?>">
    
    <?php 
    if (function_exists('renderScripts')) {
        renderScripts();
    } else {
        echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
              <script src="assets/js/radars.js" defer></script>
              <script src="assets/js/ui.js" defer></script>
              <script src="assets/js/search.js" defer></script>';
    } 
    ?>
    <meta name="theme-color" content="#050505">

<meta name="apple-mobile-web-app-capable" content="yes">

<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="page-films">
<div class="container">
    <?php renderHeader($currentUser); ?>
    <?php renderNav('home'); ?>

    <main>
        <section class="rooms-header">
            <h1>BIBLIOTHÈQUE</h1>
        </section>
        <?php renderSearchBar(); ?>

        <div id="default-library-view">
            
            <?php if (!empty($recentSeriesEpisodes)): ?>
            <div class="h-section">
                <div class="h-section__header">
                    <span class="h-section__label">Nouveaux épisodes</span>
                    <span class="h-section__count">Séries que vous suivez · 7 derniers jours</span>
                </div>
                <div class="h-scroll">
                    <?php foreach ($recentSeriesEpisodes as $m):
                        $epLabel = 'S' . str_pad($m['season_number'], 2, '0', STR_PAD_LEFT)
                                 . 'E' . str_pad($m['episode_number'], 2, '0', STR_PAD_LEFT);
                    ?>
                        <?php renderMovieCard($m, [
                            'show_meta'    => true,
                            'show_badges'  => false,
                            'content_type' => 'tv',
                            'extra_label'  => '📺 ' . $epLabel,
                        ]); ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <hr class="section-divider">
            <?php endif; ?>

            <?php if (!empty($nowPlaying)): ?>
            <div class="h-section">
                <div class="h-section__header">
                    <span class="h-section__label">En ce moment au cinéma</span>
                    <span class="h-section__count"><?= count($nowPlaying) ?> films</span>
                </div>
                <div class="h-scroll">
                    <?php 
                    foreach ($nowPlaying as $m) {
                        renderMovieCard($m, ['show_meta' => true, 'show_badges' => false]);
                    } 
                    ?>
                </div>
            </div>
            <hr class="section-divider">
            <?php endif; ?>

            <?php if (!empty($agitating)): ?>
                    <?php if (true): ?>
                        <div class="h-section">
                            <div class="h-section__header">
                                <span class="h-section__label">Ce qui agite la communauté</span>
                                <span class="h-section__count">Les avis polarisés sur les 30 derniers jours </span>
                            </div>
                            <div class="h-scroll">
                                <?php foreach ($agitating as $m): ?>
                                    <?php renderMovieCard($m, [
                                        'show_meta'   => true,
                                        'show_badges' => false,
                                        'extra_label' => '⚡ ' . round($m['polarization_index'], 1)
                                    ]); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <hr class="section-divider">
                    <?php endif; ?>
                <?php endif; ?>
            <?php if (!empty($blindSpots)): ?>
            <div class="h-section">
                <div class="h-section__header">
                    <span class="h-section__label">Souches Instables</span>
                    <span class="h-section__count">Un ADN fort sur peu de votes, des pépites à découvrir </span>
                </div>
                <div class="h-scroll">
                    <?php 
                    foreach ($blindSpots as $m) {
                        renderMovieCard($m, [
                            'show_meta' => true, 
                            'show_badges' => false, 
                            'extra_label' => '🔭 ' . $m['total_votes'] . ' vote' . ($m['total_votes'] > 1 ? 's' : '')
                        ]);
                    } 
                    ?>
                </div>
            </div>
            <hr class="section-divider">
            <?php endif; ?>

            <?php if (!empty($classics)): ?>
            <div class="h-section">
                <div class="h-section__header">
                    <span class="h-section__label">ADN Piliers</span>
                    <span class="h-section__count">Un ADN fort sur beaucoup de votes, à voir absolument ! </span>
                </div>
                <div class="h-scroll">
                    <?php 
                    foreach ($classics as $m) {
                        renderMovieCard($m, ['show_meta' => true, 'show_badges' => false]);
                    } 
                    ?>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <div id="moviesGrid" class="movie-grid"></div>

    </main>
</div>

<div class="flex-spacer"></div>
<?php renderFooter(); ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Gestion de l'affichage (Bascule Vue par défaut / Recherche)
    const searchInput = document.getElementById('movieSearchInput');
    const defaultView = document.getElementById('default-library-view');
    const moviesGrid = document.getElementById('moviesGrid');

    if (searchInput && defaultView && moviesGrid) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            if (query.length > 0) {
                defaultView.style.display = 'none';
            } else {
                defaultView.style.display = 'block';
                moviesGrid.innerHTML = '';
                moviesGrid.classList.remove('search-results-mode');
            }
        });
    }
});
</script>
</body>
</html>