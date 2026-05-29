<?php
/**
 * FICHE.PHP - Détails film, série, saison ou épisode
 */

require_once 'functions/utils.php';
require_once 'functions/ratings_logic.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/cast-card.php';
require_once 'components/radar-chart.php';
require_once 'components/button.php';
require_once 'components/modal-rate.php';
require_once 'components/watch-providers.php';
require_once 'components/tmdb-attribution.php';
require_once 'components/avatar.php';
require_once 'components/comment.php';
require_once 'components/signal-btn.php';
require_once 'functions/api_tmdb.php';

// ── Paramètres URL ────────────────────────────────────────────────────────────
$tmdbId  = (int)($_GET['id'] ?? 0);
$type    = $_GET['type'] ?? 'movie';  // movie|tv
$season  = isset($_GET['season'])  ? (int)$_GET['season']  : null;
$episode = isset($_GET['episode']) ? (int)$_GET['episode'] : null;

if (!$tmdbId) {
    header('Location: home.php');
    exit;
}

// Determine content type
if ($type === 'tv' && $season && $episode) $contentType = 'episode';
elseif ($type === 'tv' && $season)         $contentType = 'season';
elseif ($type === 'tv')                    $contentType = 'tv';
else                                       $contentType = 'movie';

// ── Chargement des données ────────────────────────────────────────────────────
$userId          = $_SESSION['user_id'] ?? null;
$currentUser     = $userId ? get_user_by_id($userId) : null;
$userPlatformIds = array_map('intval', json_decode($currentUser['user_platforms'] ?? '[]', true) ?: []);

if ($contentType === 'movie') {
    // Film : logique existante
    $movieId = $tmdbId;
    $movie   = get_movie_smart($movieId);

    if ($userId) {
        try {
            require_once 'functions/notifications_logic.php';
            mark_read_for_movie($userId, 'M-' . $movieId);
        } catch (Exception $e) { /* non critique */ }
    }

    if (!$movie) {
        die("Erreur critique : Impossible de charger les données de ce film.");
    }

    $stats     = get_movie_stats($movieId);
    $voteCount = (int)$stats['count'];

    $isLiked           = false;
    $isWishlisted      = false;
    $existingLikeScore = null;
    if ($userId) {
        $rating  = db_fetch_one(
            'SELECT is_liked, like_score FROM ratings WHERE user_id = ? AND movie_id = ?',
            [$userId, $movieId]
        );
        $isLiked           = $rating && $rating['is_liked'];
        $existingLikeScore = $rating ? $rating['like_score'] : null;

        $wRow = db_fetch_one(
            'SELECT 1 FROM user_wishlist WHERE user_id = ? AND movie_id = ?',
            [$userId, $movieId]
        );
        $isWishlisted = (bool)$wRow;
    }

    $backdropImage = $movie['poster'];
    $pageTitle     = h($movie['title']) . ' - MOOVIE';

} elseif ($contentType === 'tv') {
    $content = fetch_tmdb_series($tmdbId);
    if (!$content) { header('Location: home.php'); exit; }

    $seriesStats = get_series_stats_by_tmdb($tmdbId);

    // Note & wishlist de l'utilisateur connecté pour cette série
    $seriesIsLiked      = false;
    $seriesIsWishlisted = false;
    $seriesExistingScores = null;
    if ($userId) {
        // Résoudre l'id local de la série (si elle existe déjà en BDD)
        $seriesRow = db_fetch_one('SELECT id FROM series WHERE tmdb_id = ?', [$tmdbId]);
        if ($seriesRow) {
            $seriesLocalId = (int)$seriesRow['id'];
            $seriesRating  = db_fetch_one(
                'SELECT scores, is_liked FROM series_ratings WHERE user_id = ? AND series_id = ?',
                [$userId, $seriesLocalId]
            );
            if ($seriesRating) {
                $seriesIsLiked        = (bool)$seriesRating['is_liked'];
                $seriesExistingScores = json_decode($seriesRating['scores'], true);
            }
        }
    }
// Wishlist série
$seriesIsWishlisted = false;
if ($userId && isset($seriesLocalId)) {
    $wRow = db_fetch_one(
        'SELECT 1 FROM user_wishlist WHERE user_id = ? AND series_id = ?',
        [$userId, $seriesLocalId]
    );
    $seriesIsWishlisted = (bool)$wRow;
}
    $backdropImage = $content['poster'];
    $pageTitle     = h($content['title']) . ' - MOOVIE';

} elseif ($contentType === 'season') {
    $content = fetch_tmdb_season($tmdbId, $season);
    if (!$content) { header('Location: home.php'); exit; }

    // Fetch series title for back navigation
    $seriesData    = fetch_tmdb_series($tmdbId);
    $seriesTitle   = $seriesData ? $seriesData['title'] : '';

    // Note de saison de l'utilisateur connecté
    $seasonIsLiked         = false;
    $seasonExistingScores  = null;
    $seasonSeriesLocalId   = null;
    if ($userId) {
        $seriesRow = db_fetch_one('SELECT id FROM series WHERE tmdb_id = ?', [$tmdbId]);
        if ($seriesRow) {
            $seasonSeriesLocalId = (int)$seriesRow['id'];
            $snRating = db_fetch_one(
                'SELECT scores, is_liked FROM season_ratings
                 WHERE user_id = ? AND series_id = ? AND season_number = ?',
                [$userId, $seasonSeriesLocalId, $season]
            );
            if ($snRating) {
                $seasonIsLiked        = (bool)$snRating['is_liked'];
                $seasonExistingScores = json_decode($snRating['scores'], true);
            }
        }
    }

    $backdropImage = $content['poster'];
    $pageTitle     = 'Saison ' . $season . ($seriesTitle ? ' — ' . h($seriesTitle) : '') . ' - MOOVIE';

} elseif ($contentType === 'episode') {
    $content = fetch_tmdb_episode($tmdbId, $season, $episode);
    if (!$content) { header('Location: home.php'); exit; }

    // Fetch series title for back navigation
    $seriesData  = fetch_tmdb_series($tmdbId);
    $seriesTitle = $seriesData ? $seriesData['title'] : '';

    // Note d'épisode de l'utilisateur connecté
    $episodeIsLiked        = false;
    $episodeExistingScores = null;
    $episodeSeriesLocalId  = null;
    $episodeTotalEpisodes  = (int)($content['episode_count'] ?? 0);
    if ($userId) {
        $seriesRow = db_fetch_one('SELECT id FROM series WHERE tmdb_id = ?', [$tmdbId]);
        if ($seriesRow) {
            $episodeSeriesLocalId = (int)$seriesRow['id'];
            $epRating = db_fetch_one(
                'SELECT scores, is_liked, like_score FROM episode_ratings
                 WHERE user_id = ? AND series_id = ? AND season_number = ? AND episode_number = ?',
                [$userId, $episodeSeriesLocalId, $season, $episode]
            );
            if ($epRating) {
                $episodeIsLiked        = (bool)$epRating['is_liked'];
                $episodeExistingScores = json_decode($epRating['scores'], true);
            }
        }
    }

    $backdropImage = $content['still'];
    $pageTitle     = h($content['name']) . ' — S' . str_pad($season, 2, '0', STR_PAD_LEFT) . 'E' . str_pad($episode, 2, '0', STR_PAD_LEFT) . ' - MOOVIE';
}

// ── SEO ───────────────────────────────────────────────────────────────────────
$ogRawDesc = match($contentType) {
    'movie'  => $movie['synopsis']   ?? '',
    default  => $content['synopsis'] ?? '',
};
$ogDescription = h(mb_substr(strip_tags($ogRawDesc), 0, 155));
$ogImage       = (str_starts_with($backdropImage, 'http'))
    ? h($backdropImage)
    : 'https://adnmovie.fr/assets/Icons/Named_logo1.png';
$ogTitle       = h(strip_tags($pageTitle));
$canonicalUrl  = 'https://adnmovie.fr/fiche.php?id=' . $tmdbId
    . ($type !== 'movie' ? '&type=' . $type : '')
    . ($season  ? '&season='  . $season  : '')
    . ($episode ? '&episode=' . $episode : '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <link rel="canonical" href="<?= $canonicalUrl ?>">
    <link rel="icon" href="/assets/Icons/Logo2.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/assets/Icons/Logo3.png">
    <meta name="description" content="<?= $ogDescription ?>">
    <meta property="og:site_name" content="ADN Movie">
    <meta property="og:type" content="video.movie">
    <meta property="og:title" content="<?= $ogTitle ?>">
    <meta property="og:description" content="<?= $ogDescription ?>">
    <meta property="og:image" content="<?= $ogImage ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $ogTitle ?>">
    <meta name="twitter:description" content="<?= $ogDescription ?>">
    <meta name="twitter:image" content="<?= $ogImage ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime('assets/style.css') ?>">
    <?php if (function_exists('renderScripts')) renderScripts(); else { echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="assets/js/radars.js" defer></script><script src="assets/js/ui.js" defer></script>'; } ?>
    <meta name="theme-color" content="#050505">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="page-fiche">
    <div class="movie-backdrop-blur" style="background-image:url('<?= h($backdropImage) ?>');"></div>

    <div class="container">
        <?php renderHeader($currentUser); ?>
        <?php renderNav('none'); ?>

        <main class="movie-detail-layout">

<?php if ($contentType === 'movie'): ?>
            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- FILM                                                           -->
            <!-- ══════════════════════════════════════════════════════════════ -->

            <header class="movie-mobile-header">
                <span class="director-tag">Un film de <?= h($movie['director'] ?? 'Inconnu') ?></span>
                <h1><?= h($movie['title']) ?></h1>
                <span style="font-size:0.62rem;color:var(--text-dim);font-family:monospace;opacity:0.6;">Archive : #<?= (int)$movieId ?></span>
            </header>

            <aside class="movie-sidebar">
                <div class="poster-main">
                    <img src="<?= h($movie['poster']) ?>" alt="<?= h($movie['title']) ?>">
                </div>
                <div class="radar-block">
                    <?php renderRadarChart(
                        $stats['averages'],
                        $stats['user_note'],
                        $stats['polarization'] ?? 0,
                        'movieChart',
                        true,
                        $voteCount
                    ); ?>
                    <?php if ($currentUser): ?>
                        <div style="margin-top:15px; display:flex; flex-direction:column; gap:8px;">
                            <button class="btn-base active" onclick="openRateModal()" style="width:100%;">
                                <?= $stats['user_note'] ? 'Modifier mon analyse' : 'Analyser ce film' ?>
                            </button>
                            <button id="btnWishlist"
                                    class="btn-base <?= $isWishlisted ? 'btn-wishlisted' : '' ?>"
                                    onclick="toggleWishlist(<?= (int)$movieId ?>)"
                                    style="width:100%;">
                                <?= $isWishlisted ? '🔖 Dans ma wishlist' : '+ Ajouter à ma watchlist' ?>
                            </button>
                        </div>
                        <button onclick="openSignal('movie', <?= (int)$movieId ?>)"
                                style="background:none;border:none;color:var(--text-dim);font-size:0.62rem;cursor:pointer;margin-top:8px;opacity:0.5;width:100%;text-align:center;transition:opacity 0.2s;"
                                onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.5'">
                            Signaler ce film
                        </button>
                    <?php endif; ?>
                </div>
            </aside>

            <section class="movie-info-content">
                <header class="movie-header">
                    <span class="director-tag">Un film de <?= h($movie['director'] ?? 'Inconnu') ?></span>
                    <h1><?= h($movie['title']) ?></h1>
                    <span style="font-size:0.62rem;color:var(--text-dim);font-family:monospace;opacity:0.6;display:block;margin-top:2px;">Archive : #<?= (int)$movieId ?></span>
                    <div class="meta-row" style="display:flex; gap:10px; margin-top:10px;">
                        <span class="badge-meta">📅 <?= h($movie['year']) ?></span>
                        <?php if (!empty($movie['runtime'])): ?>
                            <span class="badge-meta">⏱️ <?= $movie['runtime'] ?> min</span>
                        <?php endif; ?>
                    </div>
                    <div class="genre-container" style="display:flex; gap:8px; margin-top:15px;">
                        <?php
                        $genres = is_array($movie['genres']) ? $movie['genres'] : json_decode($movie['genres'] ?? '[]', true);
                        foreach ($genres as $g):
                        ?>
                            <span class="genre-badge"><?= h(is_array($g) ? ($g['name'] ?? '') : $g) ?></span>
                        <?php endforeach; ?>
                    </div>
                </header>

                <div class="movie-synopsis" style="margin-top:30px;">
                    <h3 style="font-size:0.8rem; letter-spacing:2px; color:var(--text-dim); margin-bottom:10px;">SYNOPSIS</h3>
                    <p style="line-height:1.6; color:var(--text-muted);"><?= h($movie['synopsis'] ?? 'Aucun résumé disponible.') ?></p>
                </div>

                <div class="movie-streaming">
                    <?php renderWatchProviders($movieId, '', $userPlatformIds); ?>
                </div>

                <?php
                $cast = is_array($movie['cast']) ? $movie['cast'] : json_decode($movie['cast_data'] ?? '[]', true);
                if (!empty($cast)):
                ?>
                <div class="movie-cast-section" style="margin-top:40px;">
                    <h3 style="font-size:0.8rem; letter-spacing:2px; color:var(--text-dim);">DISTRIBUTION</h3>
                    <div class="cast-grid">
                        <?php foreach (array_slice($cast, 0, 6) as $actor) renderCastCard($actor); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php renderCommentSection('M-' . $movieId, $currentUser, $userId); ?>

                <?php if (has_role('admin')): ?>
                <div style="margin-top:40px; text-align:right;">
                    <button onclick="window.purgeFilm(<?= $movieId ?>)" style="background:none; border:none; color:rgba(255,77,77,0.4); font-size:0.6rem; cursor:pointer; text-transform:uppercase; letter-spacing:1px;">
                        ⌫ Purger l'archive
                    </button>
                </div>
                <?php endif; ?>

                <div style="margin-top:30px; opacity:0.3;">
                    <?php renderTMDBAttribution(); ?>
                </div>
            </section>

<?php elseif ($contentType === 'tv'): ?>
            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- SÉRIE                                                          -->
            <!-- ══════════════════════════════════════════════════════════════ -->

            <header class="movie-mobile-header">
                <span class="director-tag">Une série de <?= h($content['creator']) ?></span>
                <h1><?= h($content['title']) ?></h1>
            </header>

            <aside class="movie-sidebar">
                <div class="poster-main">
                    <img src="<?= h($content['poster']) ?>" alt="<?= h($content['title']) ?>">
                </div>
                <div class="radar-block">
                    <?php renderRadarChart(
                        $seriesStats['averages'],
                        $seriesStats['user_note'],
                        $seriesStats['polarization'] ?? 0,
                        'seriesChart',
                        true,
                        $seriesStats['count']
                    ); ?>
                    <?php if ($currentUser): ?>
                        <?php if ($seriesExistingScores): ?>
                            <p style="margin-top:12px; font-size:0.68rem; color:var(--text-dim); text-align:center; line-height:1.4;">
                                Moyenne de vos analyses de saisons
                            </p>
                        <?php else: ?>
                            <p style="margin-top:12px; font-size:0.68rem; color:var(--text-dim); text-align:center; line-height:1.4;">
                                Notez chaque saison pour construire votre analyse
                            </p>
                        <?php endif; ?>
                        <div style="margin-top:10px; display:flex; flex-direction:column; gap:8px;">
                            <?php if (!empty($seriesLocalId)): ?>
                            <button class="btn-base active" onclick="openRateModal()" style="width:100%;">
                                <?= $seriesExistingScores ? 'Modifier mon analyse de la série' : 'Analyser la série complète' ?>
                            </button>
                            <?php endif; ?>
                            <button id="btnSeriesWishlist"
                                    class="btn-base <?= $seriesIsWishlisted ? 'btn-wishlisted' : '' ?>"
                                    onclick="toggleSeriesWishlist(<?= (int)($seriesLocalId ?? 0) ?>)"
                                    style="width:100%;">
                                <?= $seriesIsWishlisted ? '🔖 Dans ma wishlist' : '+ Ajouter à ma watchlist' ?>
                            </button>
                        </div>
                        <button onclick="openSignal('tv', <?= (int)$tmdbId ?>)"
                                style="background:none;border:none;color:var(--text-dim);font-size:0.62rem;cursor:pointer;margin-top:8px;opacity:0.5;width:100%;text-align:center;transition:opacity 0.2s;"
                                onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.5'">
                            Signaler cette série
                        </button>
                    <?php endif; ?>
                </div>
            </aside>

            <section class="movie-info-content">
                <header class="movie-header">
                    <span class="director-tag">Une série de <?= h($content['creator']) ?></span>
                    <h1><?= h($content['title']) ?></h1>
                    <div class="meta-row" style="display:flex; gap:10px; margin-top:10px; flex-wrap:wrap; align-items:center;">
                        <?php if ($content['year']): ?>
                            <span class="badge-meta">📅 <?= (int)$content['year'] ?></span>
                        <?php endif; ?>
                        <?php if ($content['total_seasons']): ?>
                            <span class="badge-meta"><?= (int)$content['total_seasons'] ?> saison<?= $content['total_seasons'] > 1 ? 's' : '' ?></span>
                        <?php endif; ?>
                        <?php
                        $statusMap = [
                            'Returning Series' => ['label' => 'En cours',       'color' => '#4CAF82'],
                            'Ended'            => ['label' => 'Terminée',        'color' => '#888'],
                            'Canceled'         => ['label' => 'Annulée',         'color' => '#FA6B6B'],
                            'Cancelled'        => ['label' => 'Annulée',         'color' => '#FA6B6B'],
                            'In Production'    => ['label' => 'En production',   'color' => '#F4A94A'],
                            'Planned'          => ['label' => 'Planifiée',       'color' => '#7EB8F7'],
                            'Pilot'            => ['label' => 'Pilote',          'color' => '#B98FE0'],
                        ];
                        $s = $statusMap[$content['status'] ?? ''] ?? null;
                        if ($s):
                        ?>
                            <span style="font-size:0.6rem; font-weight:900; padding:3px 8px; border-radius:20px; text-transform:uppercase; letter-spacing:.5px; background:<?= $s['color'] ?>22; color:<?= $s['color'] ?>; border:1px solid <?= $s['color'] ?>55;">
                                <?= $s['label'] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($content['genres'])): ?>
                    <div class="genre-container" style="display:flex; gap:8px; flex-wrap:wrap; margin-top:15px;">
                        <?php foreach ($content['genres'] as $g): ?>
                            <span class="genre-badge"><?= h($g) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </header>

                <div class="movie-synopsis" style="margin-top:30px;">
                    <h3 style="font-size:0.8rem; letter-spacing:2px; color:var(--text-dim); margin-bottom:10px;">SYNOPSIS</h3>
                    <p style="line-height:1.6; color:var(--text-muted);"><?= h($content['synopsis'] ?? 'Aucun résumé disponible.') ?></p>
                </div>

                <?php
                // Providers pour la série
                _ensure_provider_columns('series');
                $tvProviders = fetch_tmdb_series_watch_providers($tmdbId);
                $tvTitle     = $content['title'] ?? '';
                $tvYear      = (int)($content['year'] ?? 0);

                // YouTube
                $tvAllIds    = array_map(fn($p) => (int)$p['provider_id'],
                    array_merge($tvProviders['flatrate'] ?? [], $tvProviders['buy'] ?? []));
                $tvRow       = db_fetch_one('SELECT youtube_id FROM series WHERE tmdb_id = ?', [$tmdbId]);
                $tvYoutubeId = 'NONE';
                if (!empty(array_intersect($tvAllIds, [188, 192]))) {
                    $tvYoutubeId = ($tvRow['youtube_id'] !== null)
                        ? $tvRow['youtube_id']
                        : _fetch_and_cache_youtube_id($tmdbId, $tvTitle, $tvYear, 'series');
                }

                // JustWatch URL spécifique à la série
                $tvJwUrl = _justwatch_url($tvTitle, 'tv');

                // Trier flatrate : possédés en premier
                if (!empty($tvProviders['flatrate']) && !empty($userPlatformIds)) {
                    usort($tvProviders['flatrate'], fn($a, $b) =>
                        in_array((int)$b['provider_id'], $userPlatformIds) <=> in_array((int)$a['provider_id'], $userPlatformIds)
                    );
                }

                if (!empty($tvProviders['flatrate']) || !empty($tvProviders['buy'])):
                ?>
                <div class="movie-streaming" style="margin-top:30px;">
                    <div class="watch-providers">
                        <h3>OÙ REGARDER ?</h3>
                        <?php if (!empty($tvProviders['flatrate'])): ?>
                            <div class="provider-section">
                                <span class="provider-type">Streaming</span>
                                <div class="provider-logos">
                                    <?php foreach ($tvProviders['flatrate'] as $p):
                                        _render_provider_link($p, $tvTitle, $tvYoutubeId, $tvJwUrl, 'tv', $userPlatformIds, true);
                                    endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($tvProviders['buy'])): ?>
                            <div class="provider-section">
                                <span class="provider-type">Achat / Location</span>
                                <div class="provider-logos">
                                    <?php foreach ($tvProviders['buy'] as $p):
                                        _render_provider_link($p, $tvTitle, $tvYoutubeId, $tvJwUrl, 'tv', [], false);
                                    endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="justwatch-attribution">
                            <small>Données fournies par JustWatch via TMDB</small>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="movie-streaming" style="margin-top:30px;">
                    <p class="no-providers">Non disponible en streaming actuellement.</p>
                </div>
                <?php endif; ?>

                <?php if (!empty($content['seasons'])): ?>
                <?php
                // Pré-charger les notes de saison de l'utilisateur pour cette série
                $userSeasonNotes = [];
                if ($userId && isset($seriesLocalId)) {
                    $snRows = db_fetch_all(
                        'SELECT season_number, scores FROM season_ratings
                         WHERE user_id = ? AND series_id = ?',
                        [$userId, $seriesLocalId]
                    );
                    foreach ($snRows as $snRow) {
                        $userSeasonNotes[(int)$snRow['season_number']] = json_decode($snRow['scores'], true);
                    }
                }
                ?>
                <div class="seasons-section" style="margin-top:30px;">
                    <h3 style="font-size:0.8rem; letter-spacing:2px; color:var(--text-dim); margin-bottom:15px;">SAISONS</h3>
                    <div class="seasons-grid" style="display:flex; flex-wrap:wrap; gap:12px;">
                        <?php foreach ($content['seasons'] as $s):
                            $sNum   = (int)$s['season_number'];
                            $sRated = isset($userSeasonNotes[$sNum]);
                        ?>
                        <a href="fiche.php?id=<?= (int)$tmdbId ?>&type=tv&season=<?= $sNum ?>"
                           class="season-card"
                           style="display:flex; flex-direction:column; align-items:center; gap:6px; text-decoration:none; color:var(--text-main); background:var(--card-bg, rgba(255,255,255,0.04)); border-radius:10px; padding:10px; width:100px; text-align:center; transition:background 0.2s; position:relative;"
                           onmouseover="this.style.background='rgba(255,255,255,0.08)'"
                           onmouseout="this.style.background='var(--card-bg, rgba(255,255,255,0.04))'">
                            <?php if ($sRated): ?>
                                <span style="position:absolute; top:6px; right:6px; font-size:0.6rem; background:#4CAF8222; color:#4CAF82; border:1px solid #4CAF8255; border-radius:20px; padding:1px 5px;">✓</span>
                            <?php endif; ?>
                            <img src="<?= h($s['poster_path']) ?>"
                                 alt="<?= h($s['name']) ?>"
                                 style="width:80px; border-radius:8px; aspect-ratio:2/3; object-fit:cover;"
                                 onerror="this.src='assets/no-poster.jpg'">
                            <span style="font-size:0.78rem; font-weight:600;"><?= h($s['name']) ?></span>
                            <span style="font-size:0.68rem; color:var(--text-dim);"><?= (int)$s['episode_count'] ?> épisodes</span>
                            <?php if ($userId): ?>
                                <span style="font-size:0.62rem; color:var(--text-dim); margin-top:2px;">
                                    <?= $sRated ? '✏️ Modifier' : '+ Analyser' ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($content['cast'])): ?>
                <div class="movie-cast-section" style="margin-top:40px;">
                    <h3 style="font-size:0.8rem; letter-spacing:2px; color:var(--text-dim);">DISTRIBUTION</h3>
                    <div class="cast-grid">
                        <?php foreach (array_slice($content['cast'], 0, 6) as $actor) renderCastCard($actor); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php renderCommentSection('TV-' . $tmdbId, $currentUser, $userId); ?>

                <div style="margin-top:30px; opacity:0.3;">
                    <?php renderTMDBAttribution(); ?>
                </div>
            </section>

<?php elseif ($contentType === 'season'): ?>
            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- SAISON                                                         -->
            <!-- ══════════════════════════════════════════════════════════════ -->

            <header class="movie-mobile-header">
                <a href="fiche.php?id=<?= (int)$tmdbId ?>&type=tv"
                   style="font-size:0.75rem; color:var(--text-dim); text-decoration:none; display:inline-block; margin-bottom:8px;">
                    ← Retour à la série
                </a>
                <h1>Saison <?= (int)$season ?><?= $seriesTitle ? ' — ' . h($seriesTitle) : '' ?></h1>
            </header>

            <aside class="movie-sidebar">
                <div class="poster-main">
                    <img src="<?= h($content['poster']) ?>"
                         alt="<?= h($content['name']) ?>"
                         onerror="this.src='assets/no-poster.jpg'">
                </div>
                <?php if ($currentUser && $seasonSeriesLocalId): ?>
                <div class="radar-block">
                    <?php
                    // Radar de la saison : ADN collectif depuis season_ratings pour cette saison
                    $seasonStats = get_season_stats($seasonSeriesLocalId, $season);
                    renderRadarChart(
                        $seasonStats['averages'] ?? [],
                        $seasonExistingScores,
                        $seasonStats['polarization'] ?? 0,
                        'seasonChart',
                        true,
                        $seasonStats['count'] ?? 0
                    );
                    ?>
                    <div style="margin-top:15px; display:flex; flex-direction:column; gap:8px;">
                        <button class="btn-base active" onclick="openRateModal()" style="width:100%;">
                            <?= $seasonExistingScores ? 'Modifier mon analyse' : 'Analyser cette saison' ?>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </aside>

            <section class="movie-info-content">
                <header class="movie-header">
                    <a href="fiche.php?id=<?= (int)$tmdbId ?>&type=tv"
                       style="font-size:0.75rem; color:var(--text-dim); text-decoration:none; display:inline-block; margin-bottom:8px;">
                        ← Retour à la série
                    </a>
                    <h1>Saison <?= (int)$season ?><?= $seriesTitle ? ' — ' . h($seriesTitle) : '' ?></h1>
                </header>

                <?php if (!empty($content['synopsis'])): ?>
                <div class="movie-synopsis" style="margin-top:30px;">
                    <h3 style="font-size:0.8rem; letter-spacing:2px; color:var(--text-dim); margin-bottom:10px;">SYNOPSIS</h3>
                    <p style="line-height:1.6; color:var(--text-muted);"><?= h($content['synopsis']) ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($content['episodes'])): ?>
                <div class="episodes-list" style="margin-top:30px;">
                    <h3 style="font-size:0.8rem; letter-spacing:2px; color:var(--text-dim); margin-bottom:15px;">ÉPISODES</h3>
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <?php foreach ($content['episodes'] as $ep): ?>
                        <a href="fiche.php?id=<?= (int)$tmdbId ?>&type=tv&season=<?= (int)$season ?>&episode=<?= (int)$ep['episode_number'] ?>"
                           style="display:flex; gap:14px; align-items:flex-start; text-decoration:none; color:var(--text-main); background:var(--card-bg, rgba(255,255,255,0.04)); border-radius:10px; padding:12px; transition:background 0.2s;"
                           onmouseover="this.style.background='rgba(255,255,255,0.08)'"
                           onmouseout="this.style.background='var(--card-bg, rgba(255,255,255,0.04))'">
                            <img src="<?= h($ep['still_path']) ?>"
                                 alt="Épisode <?= (int)$ep['episode_number'] ?>"
                                 style="width:120px; border-radius:8px; aspect-ratio:16/9; object-fit:cover; flex-shrink:0;"
                                 onerror="this.src='assets/no-poster.jpg'">
                            <div style="flex:1; min-width:0;">
                                <div style="font-size:0.72rem; color:var(--text-dim); margin-bottom:4px; letter-spacing:1px;">
                                    ÉPISODE <?= (int)$ep['episode_number'] ?>
                                    <?php if ($ep['air_date']): ?>
                                        · <?= h($ep['air_date']) ?>
                                    <?php endif; ?>
                                    <?php if ($ep['runtime']): ?>
                                        · <?= (int)$ep['runtime'] ?> min
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:0.9rem; font-weight:600; margin-bottom:6px;"><?= h($ep['name']) ?></div>
                                <?php if (!empty($ep['overview'])): ?>
                                    <p style="font-size:0.78rem; color:var(--text-muted); line-height:1.5; margin:0;
                                              display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;">
                                        <?= h($ep['overview']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php renderCommentSection('S-' . $tmdbId . '-' . $season, $currentUser, $userId); ?>

                <div style="margin-top:30px; opacity:0.3;">
                    <?php renderTMDBAttribution(); ?>
                </div>
            </section>

<?php elseif ($contentType === 'episode'): ?>
            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- ÉPISODE                                                        -->
            <!-- ══════════════════════════════════════════════════════════════ -->

            <?php
            $episodeLabel = 'S' . str_pad($season, 2, '0', STR_PAD_LEFT) . 'E' . str_pad($episode, 2, '0', STR_PAD_LEFT);
            ?>

            <header class="movie-mobile-header">
                <div style="display:flex; gap:16px; margin-bottom:8px;">
                    <a href="fiche.php?id=<?= (int)$tmdbId ?>&type=tv&season=<?= (int)$season ?>"
                       style="font-size:0.75rem; color:var(--text-dim); text-decoration:none;">
                        ← Saison <?= (int)$season ?>
                    </a>
                    <a href="fiche.php?id=<?= (int)$tmdbId ?>&type=tv"
                       style="font-size:0.75rem; color:var(--text-dim); text-decoration:none;">
                        ← Série<?= $seriesTitle ? ' : ' . h($seriesTitle) : '' ?>
                    </a>
                </div>
                <span class="director-tag"><?= $episodeLabel ?></span>
                <h1><?= h($content['name']) ?></h1>
            </header>

            <aside class="movie-sidebar">
                <div class="poster-main">
                    <img src="<?= h($content['still']) ?>"
                         alt="<?= h($content['name']) ?>"
                         style="border-radius:10px; width:100%; aspect-ratio:16/9; object-fit:cover;"
                         onerror="this.src='assets/no-poster.jpg'">
                </div>
                <?php if ($content['runtime']): ?>
                <div style="margin-top:10px; text-align:center; font-size:0.78rem; color:var(--text-dim);">
                    ⏱️ <?= (int)$content['runtime'] ?> min
                </div>
                <?php endif; ?>
                <?php if ($currentUser && $episodeSeriesLocalId): ?>
                <div class="radar-block">
                    <?php
                    $episodeStats = get_episode_stats($episodeSeriesLocalId, $season, $episode);
                    renderRadarChart(
                        $episodeStats['averages'] ?? [],
                        $episodeExistingScores,
                        $episodeStats['polarization'] ?? 0,
                        'episodeChart',
                        true,
                        $episodeStats['count'] ?? 0
                    );
                    ?>
                    <div style="margin-top:15px; display:flex; flex-direction:column; gap:8px;">
                        <button class="btn-base active" onclick="openRateModal()" style="width:100%;">
                            <?= $episodeExistingScores ? 'Modifier mon analyse' : 'Analyser cet épisode' ?>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </aside>

            <section class="movie-info-content">
                <header class="movie-header">
                    <div style="display:flex; gap:16px; margin-bottom:8px;">
                        <a href="fiche.php?id=<?= (int)$tmdbId ?>&type=tv&season=<?= (int)$season ?>"
                           style="font-size:0.75rem; color:var(--text-dim); text-decoration:none;">
                            ← Saison <?= (int)$season ?>
                        </a>
                        <a href="fiche.php?id=<?= (int)$tmdbId ?>&type=tv"
                           style="font-size:0.75rem; color:var(--text-dim); text-decoration:none;">
                            ← Série<?= $seriesTitle ? ' : ' . h($seriesTitle) : '' ?>
                        </a>
                    </div>
                    <span class="director-tag"><?= $episodeLabel ?></span>
                    <h1><?= h($content['name']) ?></h1>
                    <div class="meta-row" style="display:flex; gap:10px; margin-top:10px;">
                        <?php if ($content['air_date']): ?>
                            <span class="badge-meta">📅 <?= h($content['air_date']) ?></span>
                        <?php endif; ?>
                        <?php if ($content['runtime']): ?>
                            <span class="badge-meta">⏱️ <?= (int)$content['runtime'] ?> min</span>
                        <?php endif; ?>
                    </div>
                </header>

                <?php if (!empty($content['synopsis'])): ?>
                <div class="movie-synopsis" style="margin-top:30px;">
                    <h3 style="font-size:0.8rem; letter-spacing:2px; color:var(--text-dim); margin-bottom:10px;">SYNOPSIS</h3>
                    <p style="line-height:1.6; color:var(--text-muted);"><?= h($content['synopsis']) ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($content['cast'])): ?>
                <div class="movie-cast-section" style="margin-top:40px;">
                    <h3 style="font-size:0.8rem; letter-spacing:2px; color:var(--text-dim);">DISTRIBUTION</h3>
                    <div class="cast-grid">
                        <?php foreach ($content['cast'] as $actor) renderCastCard($actor); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php renderCommentSection('E-' . $tmdbId . '-' . $season . '-' . $episode, $currentUser, $userId); ?>

                <div style="margin-top:30px; opacity:0.3;">
                    <?php renderTMDBAttribution(); ?>
                </div>
            </section>

<?php endif; ?>

        </main>
    </div>

    <div class="flex-spacer"></div>
    <?php renderFooter(); ?>

    <?php if ($contentType === 'movie' && $currentUser): ?>
        <?php renderRateModal($movieId, $stats['user_note_assoc'], $isLiked, $existingLikeScore); ?>
        <?php renderSignalModal(); ?>
        <script src="/assets/js/fiche.js?v=1" defer></script>
    <?php elseif ($contentType === 'tv' && $currentUser && !empty($seriesLocalId)): ?>
        <?php renderRateModal(
            $seriesLocalId,
            $seriesExistingScores,
            $seriesIsLiked ?? false,
            null,
            'tv_full'
        ); ?>
        <?php renderSignalModal(); ?>
        <script>
            window.__seriesLocalId = <?= (int)$seriesLocalId ?>;
        </script>
        <script src="/assets/js/fiche.js?v=1" defer></script>
    <?php elseif ($contentType === 'season' && $currentUser && $seasonSeriesLocalId): ?>
        <?php renderRateModal(
            $seasonSeriesLocalId,
            $seasonExistingScores,
            $seasonIsLiked,
            null,
            'tv',
            $season
        ); ?>
        <script src="/assets/js/fiche.js?v=1" defer></script>
    <?php elseif ($contentType === 'episode' && $currentUser && $episodeSeriesLocalId): ?>
        <?php renderRateModal(
            $episodeSeriesLocalId,
            $episodeExistingScores,
            $episodeIsLiked,
            null,
            'episode',
            $season
        ); ?>
        <script>
            window.__episodeSeriesLocalId = <?= (int)$episodeSeriesLocalId ?>;
            window.__episodeSeasonNumber  = <?= (int)$season ?>;
            window.__episodeNumber        = <?= (int)$episode ?>;
            window.__episodeTotalEpisodes = <?= (int)$episodeTotalEpisodes ?>;
        </script>
        <script src="/assets/js/fiche.js?v=1" defer></script>
    <?php endif; ?>
</body>
</html>