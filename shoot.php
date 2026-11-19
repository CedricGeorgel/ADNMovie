<?php
/**
 * SHOOT.PHP — LE film et LA série à regarder ce soir depuis tes abonnements.
 * Score = moyenne communautaire des like_score, sur les contenus non encore vus.
 */
require_once 'config/settings.php';
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/movie-card.php';

check_auth();
$userId = $_SESSION['user_id'];
$user   = get_user_by_id($userId);

$subscribedIds = array_map('intval', json_decode($user['user_platforms'] ?? '[]', true) ?: []);
$hasPlatforms  = !empty($subscribedIds);

// ── Films déjà vus (hors Oracle) ─────────────────────────────────────────────
$seenMovieIds = array_column(
    db_fetch_all("SELECT movie_id FROM ratings WHERE user_id = ? AND user_id != 'IA-ORACLE-001'", [$userId]),
    'movie_id'
) ?: [0];

// ── Séries déjà vues (hors Oracle) ───────────────────────────────────────────
$seenSeriesIds = array_column(
    db_fetch_all("SELECT series_id FROM series_ratings WHERE user_id = ? AND user_id != 'IA-ORACLE-001'", [$userId]),
    'series_id'
) ?: [0];

// ── Film : meilleur score communautaire disponible sur abonnements ─────────────
$topMovie   = null;
$topMovieScore = null;

if ($hasPlatforms) {
    // Récupérer les tmdb_id disponibles sur les abos de l'utilisateur
    $allMoviesWithProviders = db_fetch_all(
        'SELECT tmdb_id, providers_data FROM movies WHERE providers_data IS NOT NULL AND providers_data != "[]"'
    );
    $platformMovieIds = [];
    foreach ($allMoviesWithProviders as $m) {
        $data     = json_decode($m['providers_data'], true) ?: [];
        $flatIds  = array_column($data['flatrate'] ?? [], 'provider_id');
        if (array_intersect($subscribedIds, $flatIds)) {
            $platformMovieIds[] = (int)$m['tmdb_id'];
        }
    }
    $platformMovieIds = array_values(array_diff($platformMovieIds, $seenMovieIds));
} else {
    $platformMovieIds = [];
}

if (!empty($platformMovieIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($platformMovieIds), '?'));
    $topMovieRow = db_fetch_one(
        "SELECT m.tmdb_id AS id, m.title, m.poster, m.year, m.total_votes,
                ROUND(AVG(r.like_score), 1) AS community_score,
                COUNT(r.like_score)         AS vote_count
         FROM movies m
         JOIN ratings r ON r.movie_id = m.tmdb_id AND r.like_score IS NOT NULL
         WHERE m.tmdb_id IN ($inPlaceholders)
         GROUP BY m.tmdb_id
         HAVING vote_count >= 1
         ORDER BY community_score DESC, vote_count DESC
         LIMIT 1",
        $platformMovieIds
    );
    if ($topMovieRow) {
        $topMovie      = $topMovieRow;
        $topMovieScore = (float)$topMovieRow['community_score'];
    }
}

// ── Série : meilleur score communautaire, filtrée par abonnements si dispo ────
$topSeries      = null;
$topSeriesScore = null;

$seenIn = implode(',', $seenSeriesIds);

// Filtrage par plateforme si providers_data existe sur la table series
$platformSeriesIds = null;
if ($hasPlatforms) {
    $allSeriesWithProviders = db_fetch_all(
        'SELECT tmdb_id, providers_data FROM series WHERE providers_data IS NOT NULL AND providers_data != "[]"'
    );
    if (!empty($allSeriesWithProviders)) {
        $platformSeriesIds = [];
        foreach ($allSeriesWithProviders as $s) {
            $data    = json_decode($s['providers_data'], true) ?: [];
            $flatIds = array_column($data['flatrate'] ?? [], 'provider_id');
            if (array_intersect($subscribedIds, $flatIds)) {
                $platformSeriesIds[] = (int)$s['tmdb_id'];
            }
        }
    }
}

$seriesWhereExtra = '';
$seriesParams     = [];
if ($platformSeriesIds !== null) {
    if (empty($platformSeriesIds)) {
        // Abonnements connus mais aucune série disponible dessus
        $topSeries = null;
    } else {
        $inPH = implode(',', array_fill(0, count($platformSeriesIds), '?'));
        $seriesWhereExtra = "AND s.tmdb_id IN ($inPH)";
        $seriesParams     = $platformSeriesIds;
    }
}

if ($platformSeriesIds === null || !empty($platformSeriesIds)) {
    $topSeriesRow = db_fetch_one(
        "SELECT s.tmdb_id AS id, s.title, s.poster, s.year, s.total_votes,
                ROUND(AVG(sr.like_score), 1) AS community_score,
                COUNT(sr.like_score)         AS vote_count
         FROM series s
         JOIN series_ratings sr ON sr.series_id = s.id AND sr.like_score IS NOT NULL
         WHERE s.id NOT IN ($seenIn)
           $seriesWhereExtra
         GROUP BY s.id
         HAVING vote_count >= 1
         ORDER BY community_score DESC, vote_count DESC
         LIMIT 1",
        $seriesParams
    );
    if ($topSeriesRow) {
        $topSeries      = $topSeriesRow;
        $topSeriesScore = (float)$topSeriesRow['community_score'];
    }
}

function formatCommunityScore(float $score): string {
    $sign = $score >= 0 ? '+' : '';
    return $sign . number_format($score, 1) . ' / 10';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Shoot · ADN Movie</title>
    <link rel="icon" href="/assets/Icons/Logo2.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/assets/Icons/Logo3.png">
    <meta property="og:site_name" content="ADN Movie">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Shoot · ADN Movie">
    <meta property="og:description" content="Le film et la série avec le meilleur score du catalogue.">
    <meta property="og:image" content="https://adnmovie.fr/assets/Icons/Named_logo1.png">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:image" content="https://adnmovie.fr/assets/Icons/Named_logo1.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime('assets/style.css') ?>">
    <meta name="theme-color" content="#050505">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <style>
        .shoot-hero {
            text-align: center;
            margin: 40px 0 32px;
        }
        .shoot-hero h1 {
            font-size: 2.5em;
            letter-spacing: -2px;
            margin: 0 0 8px;
        }
        .shoot-hero p {
            color: var(--text-dim);
            font-size: 0.9em;
            margin: 0;
        }

        .shoot-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            max-width: 700px;
            margin: 0 auto 60px;
        }

        .shoot-slot {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .shoot-slot-label {
            font-size: 0.65rem;
            font-weight: 900;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--text-dim);
        }

        .shoot-score {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--accent, #e5c07b);
            letter-spacing: -0.5px;
        }

        .shoot-score-label {
            font-size: 0.65rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: -14px;
        }

        .shoot-empty {
            text-align: center;
            color: var(--text-dim);
            font-size: 0.85em;
            padding: 40px 20px;
        }

        .shoot-no-platforms {
            text-align: center;
            padding: 60px 20px;
        }
        .shoot-no-platforms p {
            color: var(--text-dim);
            margin-bottom: 16px;
        }
        .shoot-no-platforms a {
            color: var(--accent, #e5c07b);
            text-decoration: underline;
        }

        @media (max-width: 540px) {
            .shoot-grid {
                grid-template-columns: 1fr 1fr;
                gap: 16px;
                padding: 0 8px;
            }
            .shoot-hero h1 { font-size: 1.8em; }
        }
    </style>
</head>
<body class="page-shoot">
<div class="container">
    <?php renderHeader($user); ?>
    <?php renderNav('shoot'); ?>

    <main>

        <section class="shoot-hero">
            <h1>SHOOT</h1>
            <p>Le film et la série avec le score le plus haut — que tu n'as pas encore vus.</p>
        </section>

        <?php if (!$hasPlatforms): ?>
            <div class="shoot-no-platforms">
                <p>Tu n'as pas encore renseigné tes abonnements.</p>
                <p><a href="user.php">Configure tes plateformes</a> pour voir les recommandations disponibles sur tes abos.</p>
            </div>

        <?php elseif (!$topMovie && !$topSeries): ?>
            <div class="shoot-empty">
                <p>Aucun contenu disponible pour le moment.</p>
                <p>Reviens quand la communauté aura noté davantage de films et séries.</p>
            </div>

        <?php else: ?>
            <div class="shoot-grid">

                <!-- Film -->
                <div class="shoot-slot">
                    <span class="shoot-slot-label">Film</span>
                    <?php if ($topMovie): ?>
                        <div class="shoot-score"><?= h(formatCommunityScore($topMovieScore)) ?></div>
                        <div class="shoot-score-label">score communauté</div>
                        <?php renderMovieCard($topMovie, [
                            'content_type' => 'movie',
                            'extra_label'  => formatCommunityScore($topMovieScore),
                        ]); ?>
                    <?php else: ?>
                        <p class="shoot-empty">Aucun film trouvé sur tes abonnements.</p>
                    <?php endif; ?>
                </div>

                <!-- Série -->
                <div class="shoot-slot">
                    <span class="shoot-slot-label">Série</span>
                    <?php if ($topSeries): ?>
                        <div class="shoot-score"><?= h(formatCommunityScore($topSeriesScore)) ?></div>
                        <div class="shoot-score-label">score communauté</div>
                        <?php renderMovieCard($topSeries, [
                            'content_type' => 'tv',
                            'extra_label'  => formatCommunityScore($topSeriesScore),
                        ]); ?>
                    <?php else: ?>
                        <p class="shoot-empty">Aucune série trouvée.</p>
                    <?php endif; ?>
                </div>

            </div>
        <?php endif; ?>

    </main>

    <?php renderFooter(); ?>
</div>
</body>
</html>
