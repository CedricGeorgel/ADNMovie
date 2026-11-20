<?php
/**
 * SHOOT.PHP — LE film et LA série à regarder ce soir depuis tes abonnements.
 * Série : meilleur match ADN utilisateur ↔ série disponible sur abonnements.
 * Film  : meilleur score communautaire disponible sur abonnements.
 */
require_once 'config/settings.php';
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'functions/ratings_logic.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/movie-card.php';

check_auth();
$userId = $_SESSION['user_id'];
$user   = get_user_by_id($userId);

// ── Skip : on ajoute l'id rejeté en session, reset possible ──────────────────
$skipKey = 'shoot_skip_' . $userId;
if (isset($_GET['reset_skip'])) {
    $_SESSION[$skipKey] = [];
}
if (isset($_GET['skip_series'])) {
    $_SESSION[$skipKey]['series'] = array_merge(
        $_SESSION[$skipKey]['series'] ?? [],
        [(int)$_GET['skip_series']]
    );
}
if (isset($_GET['skip_movie'])) {
    $_SESSION[$skipKey]['movies'] = array_merge(
        $_SESSION[$skipKey]['movies'] ?? [],
        [(int)$_GET['skip_movie']]
    );
}
$skippedSeriesTmdbIds = $_SESSION[$skipKey]['series'] ?? [];
$skippedMovieTmdbIds  = $_SESSION[$skipKey]['movies'] ?? [];

$subscribedIds = array_map('intval', json_decode($user['user_platforms'] ?? '[]', true) ?: []);
$hasPlatforms  = !empty($subscribedIds);

// ── Films déjà vus (hors Oracle) ─────────────────────────────────────────────
$seenMovieIds = array_column(
    db_fetch_all("SELECT movie_id FROM ratings WHERE user_id = ? AND user_id != 'IA-ORACLE-001'", [$userId]),
    'movie_id'
) ?: [0];
$seenMovieIds = array_values(array_unique(array_merge($seenMovieIds, $skippedMovieTmdbIds))) ?: [0];

// ── Séries déjà vues (hors Oracle) ───────────────────────────────────────────
$seenSeriesIds = array_column(
    db_fetch_all("SELECT series_id FROM series_ratings WHERE user_id = ? AND user_id != 'IA-ORACLE-001'", [$userId]),
    'series_id'
) ?: [0];

// ── ADN utilisateur films (avec variance — algorithme Gaussien V3) ───────────
$userMovieDna    = buildDnaFromTable($userId, 'user_dna'); // [criterio => [avg,var,min,max]]
$hasUserMovieDna = count($userMovieDna) >= 4;

// ── ADN utilisateur séries (fallback sur ADN films si rien) ──────────────────
$userSeriesDna = buildDnaFromTable($userId, 'user_series_dna');
if (empty($userSeriesDna)) $userSeriesDna = $userMovieDna;
// Pour la compatibilité avec le bloc séries (avg seulement)
$userDnaMap = array_map(fn($d) => $d['avg'], $userSeriesDna);
$hasUserDna = count($userDnaMap) >= 4;

// ── Films disponibles sur abonnements ─────────────────────────────────────────
$topMovie      = null;
$topMovieScore = null;
$topMovieMode  = 'dna'; // 'dna' | 'community'

$platformMovieIds = [];
if ($hasPlatforms) {
    $allMoviesWithProviders = db_fetch_all(
        'SELECT tmdb_id, providers_data FROM movies WHERE providers_data IS NOT NULL AND providers_data != "[]"'
    );
    foreach ($allMoviesWithProviders as $m) {
        $data    = json_decode($m['providers_data'], true) ?: [];
        $flatIds = array_column($data['flatrate'] ?? [], 'provider_id');
        if (array_intersect($subscribedIds, $flatIds)) {
            $platformMovieIds[] = (int)$m['tmdb_id'];
        }
    }
    $platformMovieIds = array_values(array_diff($platformMovieIds, $seenMovieIds));
}

// ── Mode Gaussien films (algorithme recos V3) ─────────────────────────────────
if ($hasUserMovieDna && !empty($platformMovieIds)) {
    $userProfile   = db_fetch_one('SELECT exploration_rate FROM users WHERE id = ?', [$userId]);
    $epsilon       = (float)($userProfile['exploration_rate'] ?? 0.15);

    $inPH = implode(',', array_fill(0, count($platformMovieIds), '?'));
    $movieDnaRows = db_fetch_all(
        "SELECT m.tmdb_id AS id, m.title, m.poster, m.year, m.polarization_index,
                md.criterio, md.avg_score, md.sample_size
         FROM movies m
         JOIN movie_dna md ON md.movie_id = m.tmdb_id
         WHERE m.tmdb_id IN ($inPH) AND m.total_votes > 0",
        $platformMovieIds
    );

    $films = [];
    foreach ($movieDnaRows as $row) {
        $mid = (int)$row['id'];
        if (!isset($films[$mid])) {
            $films[$mid] = [
                'id'          => $mid,
                'title'       => $row['title'],
                'poster'      => $row['poster'],
                'year'        => $row['year'],
                'polarization'=> (float)$row['polarization_index'],
                'dna'         => [],
            ];
        }
        $films[$mid]['dna'][$row['criterio']] = [
            'avg'         => (float)$row['avg_score'],
            'sample_size' => (int)$row['sample_size'],
        ];
    }

    $bestScore = -1.0;
    $bestMovie = null;
    foreach ($films as $film) {
        if (count($film['dna']) < 4) continue;
        $result    = calculateSimilarityWithReasons($userMovieDna, $film['dna']);
        $polarNorm = min(1.0, $film['polarization'] / 20.0);
        $score     = $result['score'] * (1.0 - $polarNorm * (1.0 - $epsilon));
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMovie = $film;
        }
    }

    if ($bestMovie) {
        $topMovie      = $bestMovie;
        $topMovieScore = (int)round($bestScore * 100); // converti en %
        $topMovieMode  = 'dna';
    }
}

// ── Fallback : meilleur score communautaire films ─────────────────────────────
if (!$topMovie && !empty($platformMovieIds)) {
    $topMovieMode = 'community';
    $inPH = implode(',', array_fill(0, count($platformMovieIds), '?'));
    $topMovieRow = db_fetch_one(
        "SELECT m.tmdb_id AS id, m.title, m.poster, m.year, m.total_votes,
                ROUND(AVG(r.like_score), 1) AS community_score,
                COUNT(r.like_score)         AS vote_count
         FROM movies m
         JOIN ratings r ON r.movie_id = m.tmdb_id AND r.like_score IS NOT NULL
         WHERE m.tmdb_id IN ($inPH)
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

// ── Séries disponibles sur abonnements ────────────────────────────────────────
$topSeries      = null;
$topSeriesScore = null;
$topSeriesMode  = 'dna'; // 'dna' | 'community'

// Convertir les tmdb_ids skippés en ids locaux
$skippedSeriesLocalIds = [];
if (!empty($skippedSeriesTmdbIds)) {
    $inPH = implode(',', array_fill(0, count($skippedSeriesTmdbIds), '?'));
    $skippedSeriesLocalIds = array_column(
        db_fetch_all("SELECT id FROM series WHERE tmdb_id IN ($inPH)", $skippedSeriesTmdbIds),
        'id'
    );
}
$allExcludedSeriesLocalIds = array_values(array_unique(array_merge($seenSeriesIds, $skippedSeriesLocalIds))) ?: [0];
$seenIn = implode(',', $allExcludedSeriesLocalIds);

$platformSeriesLocalIds = null; // ids locaux (series.id)
if ($hasPlatforms) {
    $allSeriesWithProviders = db_fetch_all(
        'SELECT id, tmdb_id, providers_data FROM series WHERE providers_data IS NOT NULL AND providers_data != "[]"'
    );
    if (!empty($allSeriesWithProviders)) {
        $platformSeriesLocalIds = [];
        foreach ($allSeriesWithProviders as $s) {
            $data    = json_decode($s['providers_data'], true) ?: [];
            $flatIds = array_column($data['flatrate'] ?? [], 'provider_id');
            if (array_intersect($subscribedIds, $flatIds)) {
                $platformSeriesLocalIds[] = (int)$s['id'];
            }
        }
    }
}

$providerFilter = '';
$providerParams = [];
if ($platformSeriesLocalIds !== null) {
    if (empty($platformSeriesLocalIds)) {
        // Aucune série sur les abonnements → rien à afficher
        $topSeries = null;
        goto shoot_series_done;
    }
    $inPH = implode(',', array_fill(0, count($platformSeriesLocalIds), '?'));
    $providerFilter = "AND s.id IN ($inPH)";
    $providerParams = $platformSeriesLocalIds;
}

// ── Mode Gaussien séries (algorithme recos V3) ───────────────────────────────
if ($hasUserDna) {
    if (!isset($userProfile)) {
        $userProfile = db_fetch_one('SELECT exploration_rate FROM users WHERE id = ?', [$userId]);
    }
    $epsilonSeries = (float)($userProfile['exploration_rate'] ?? 0.15);

    // Candidats : séries non vues avec ADN + polarisation
    $candidates = db_fetch_all(
        "SELECT s.id, s.tmdb_id, s.title, s.poster, s.year, s.total_votes,
                COALESCE(s.polarization_index, 0) AS polarization_index
         FROM series s
         WHERE s.id NOT IN ($seenIn)
           $providerFilter
           AND EXISTS (SELECT 1 FROM series_dna sd WHERE sd.series_id = s.id)",
        $providerParams
    );

    if (!empty($candidates)) {
        $candidateIds = array_column($candidates, 'id');
        $idPH = implode(',', array_fill(0, count($candidateIds), '?'));
        $dnaRows = db_fetch_all(
            "SELECT series_id, criterio, avg_score, sample_size FROM series_dna WHERE series_id IN ($idPH)",
            $candidateIds
        );

        $dnaBySeries = [];
        foreach ($dnaRows as $row) {
            $dnaBySeries[(int)$row['series_id']][$row['criterio']] = [
                'avg'         => (float)$row['avg_score'],
                'sample_size' => (int)$row['sample_size'],
            ];
        }

        $bestScore  = -1.0;
        $bestSeries = null;
        foreach ($candidates as $s) {
            $sid      = (int)$s['id'];
            $seriesDna = $dnaBySeries[$sid] ?? [];
            if (count($seriesDna) < 4) continue;
            $result    = calculateSimilarityWithReasons($userSeriesDna, $seriesDna);
            $polarNorm = min(1.0, (float)$s['polarization_index'] / 20.0);
            $score     = $result['score'] * (1.0 - $polarNorm * (1.0 - $epsilonSeries));
            if ($score > $bestScore) {
                $bestScore  = $score;
                $bestSeries = $s;
            }
        }

        if ($bestSeries) {
            $topSeries           = $bestSeries;
            $topSeries['id']     = (int)$bestSeries['tmdb_id'];
            $topSeriesScore      = (int)round($bestScore * 100); // converti en %
            $topSeriesMode       = 'dna';
        }
    }
}

// ── Fallback : meilleur score communautaire ───────────────────────────────────
if (!$topSeries) {
    $topSeriesMode = 'community';
    $topSeriesRow  = db_fetch_one(
        "SELECT s.tmdb_id AS id, s.tmdb_id, s.title, s.poster, s.year, s.total_votes,
                ROUND(AVG(sr.like_score), 1) AS community_score,
                COUNT(sr.like_score)         AS vote_count
         FROM series s
         JOIN series_ratings sr ON sr.series_id = s.id AND sr.like_score IS NOT NULL
         WHERE s.id NOT IN ($seenIn)
           $providerFilter
         GROUP BY s.id
         HAVING vote_count >= 1
         ORDER BY community_score DESC, vote_count DESC
         LIMIT 1",
        $providerParams
    );
    if ($topSeriesRow) {
        $topSeries      = $topSeriesRow;
        $topSeriesScore = (float)$topSeriesRow['community_score'];
    }
}

shoot_series_done:

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

        .shoot-skip-btn {
            display: block;
            text-align: center;
            font-size: 0.72rem;
            color: var(--text-dim);
            text-decoration: none;
            margin-top: 8px;
            padding: 6px;
            border-radius: 6px;
            transition: color 0.15s;
        }
        .shoot-skip-btn:hover { color: var(--text); }

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
            <p>Le meilleur match ADN parmi les séries de tes abonnements et que tu n'as pas encore vus.</p>
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
                        <?php if ($topMovieMode === 'dna'): ?>
                            <div class="shoot-score" style="color:#9dffb0;"><?= (int)$topMovieScore ?>%</div>
                            <div class="shoot-score-label">match ADN</div>
                        <?php else: ?>
                            <div class="shoot-score"><?= h(formatCommunityScore($topMovieScore)) ?></div>
                            <div class="shoot-score-label">score communauté</div>
                        <?php endif; ?>
                        <?php renderMovieCard($topMovie, [
                            'content_type' => 'movie',
                            'extra_label'  => $topMovieMode === 'dna'
                                ? ((int)$topMovieScore . '% match ADN')
                                : formatCommunityScore($topMovieScore),
                        ]); ?>
                        <a href="?skip_movie=<?= (int)$topMovie['id'] ?>" class="shoot-skip-btn">Pas ce soir →</a>
                    <?php else: ?>
                        <p class="shoot-empty">Aucun film trouvé sur tes abonnements.</p>
                    <?php endif; ?>
                </div>

                <!-- Série -->
                <div class="shoot-slot">
                    <span class="shoot-slot-label">Série</span>
                    <?php if ($topSeries): ?>
                        <?php if ($topSeriesMode === 'dna'): ?>
                            <div class="shoot-score" style="color:#9dffb0;"><?= (int)$topSeriesScore ?>%</div>
                            <div class="shoot-score-label">match ADN</div>
                        <?php else: ?>
                            <div class="shoot-score"><?= h(formatCommunityScore($topSeriesScore)) ?></div>
                            <div class="shoot-score-label">score communauté</div>
                        <?php endif; ?>
                        <?php renderMovieCard($topSeries, [
                            'content_type' => 'tv',
                            'extra_label'  => $topSeriesMode === 'dna'
                                ? ((int)$topSeriesScore . '% match ADN')
                                : formatCommunityScore($topSeriesScore),
                        ]); ?>
                        <a href="?skip_series=<?= (int)$topSeries['tmdb_id'] ?>" class="shoot-skip-btn">Pas ce soir →</a>
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
