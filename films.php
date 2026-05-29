<?php
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/search-bar.php';
require_once 'components/movie-card.php';
require_once 'functions/core_db.php';

require_role('moderator');

$currentUser = get_user_by_id($_SESSION['user_id']);

// Onglet actif : 'movies' ou 'series'
$tab = $_GET['tab'] ?? 'movies';
if (!in_array($tab, ['movies', 'series'])) $tab = 'movies';

// Tri
$sort = $_GET['sort'] ?? 'votes';
if (!in_array($sort, ['votes', 'title', 'year'])) $sort = 'votes';

$orderBy = match($sort) {
    'title' => 'title ASC',
    'year'  => 'year DESC',
    default => 'total_votes DESC, title ASC',
};

if ($tab === 'series') {
    $items = db_fetch_all(
        "SELECT tmdb_id AS id, title, poster, year, total_votes, 'tv' AS content_type
         FROM series
         ORDER BY $orderBy"
    );
} else {
    $items = db_fetch_all(
        "SELECT tmdb_id AS id, title, poster, year, total_votes, 'movie' AS content_type
         FROM movies
         ORDER BY $orderBy"
    );
}

$totalMovies = db_fetch_one("SELECT COUNT(*) AS cnt FROM movies")['cnt'] ?? 0;
$totalSeries = db_fetch_one("SELECT COUNT(*) AS cnt FROM series")['cnt'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Collection intégrale</title>
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
    <link rel="stylesheet" href="assets/library.css?v=<?= file_exists('assets/library.css') ? filemtime('assets/library.css') : 1 ?>">
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
        <section class="search-header" style="margin-bottom:24px;">
            <h1>COLLECTION INTÉGRALE</h1>
            <p style="color:var(--text-dim);font-size:0.8rem;text-transform:uppercase;letter-spacing:1px;margin-top:4px;">
                Accès modérateur · <?= number_format($totalMovies) ?> films · <?= number_format($totalSeries) ?> séries
            </p>
            <?php renderSearchBar("Rechercher dans la collection..."); ?>
        </section>

        <!-- Onglets films / séries -->
        <div class="dash-links" style="max-width:400px;margin:0 0 20px 0;display:flex;gap:10px;">
            <a href="films.php?tab=movies&sort=<?= h($sort) ?>"
               class="btn-base <?= $tab === 'movies' ? 'active' : '' ?>"
               style="flex:1;text-align:center;">
                Films <span style="opacity:.5;font-size:.75rem;">(<?= number_format($totalMovies) ?>)</span>
            </a>
            <a href="films.php?tab=series&sort=<?= h($sort) ?>"
               class="btn-base <?= $tab === 'series' ? 'active' : '' ?>"
               style="flex:1;text-align:center;">
                Séries <span style="opacity:.5;font-size:.75rem;">(<?= number_format($totalSeries) ?>)</span>
            </a>
        </div>

        <!-- Tri -->
        <div style="display:flex;gap:8px;margin-bottom:24px;align-items:center;">
            <span style="color:var(--text-dim);font-size:.75rem;text-transform:uppercase;letter-spacing:1px;">Trier :</span>
            <a href="films.php?tab=<?= h($tab) ?>&sort=votes"
               class="btn-base <?= $sort === 'votes' ? 'active' : '' ?>" style="padding:4px 12px;font-size:.75rem;">
                Votes
            </a>
            <a href="films.php?tab=<?= h($tab) ?>&sort=title"
               class="btn-base <?= $sort === 'title' ? 'active' : '' ?>" style="padding:4px 12px;font-size:.75rem;">
                Titre
            </a>
            <a href="films.php?tab=<?= h($tab) ?>&sort=year"
               class="btn-base <?= $sort === 'year' ? 'active' : '' ?>" style="padding:4px 12px;font-size:.75rem;">
                Année
            </a>
        </div>

        <!-- Grille -->
        <div id="filmsGrid" class="movie-grid">
            <?php if (empty($items)): ?>
                <div class="empty-state" style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text-dim);">
                    <p>Aucun contenu dans cette catégorie.</p>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <?php renderMovieCard($item, [
                        'show_meta'    => true,
                        'show_badges'  => false,
                        'content_type' => $item['content_type'],
                        'lazy'         => true,
                        'extra_label'  => $item['total_votes'] > 0
                            ? $item['total_votes'] . ' vote' . ($item['total_votes'] > 1 ? 's' : '')
                            : null,
                    ]); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="moviesGrid" class="movie-grid"></div>
    </main>
</div>

<div class="flex-spacer"></div>
<?php renderFooter(); ?>

</body>
</html>
