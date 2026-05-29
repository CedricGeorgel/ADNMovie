<?php

require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'functions/ratings_logic.php';
require_once 'functions/recommendation_logic.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';
require_once 'components/movie-card.php';

check_auth();
$userId = $_SESSION['user_id'];
$user   = get_user_by_id($userId);

$adn       = get_user_adn($userId)        ?: [];
$seriesAdn = get_user_series_adn($userId) ?: [];

$filmVotes   = (int)($adn['count']       ?? 0);
$seriesVotes = (int)($seriesAdn['count'] ?? 0);
$minRequired = 10;
$showRecos   = ($filmVotes >= $minRequired || $seriesVotes >= $minRequired);

// Résolution de la source ADN pour chaque type
// Films : ADN film si >= 10, sinon fallback sur ADN série
// Séries : ADN série si >= 10, sinon fallback sur ADN film
$filmDnaSource   = ($filmVotes   >= $minRequired) ? 'film'   : 'series';
$seriesDnaSource = ($seriesVotes >= $minRequired) ? 'series' : 'film';

// ── Cache check AVANT génération (pour le loader) ────────────────────────────
$filmHasCached   = $showRecos && db_fetch_one(
    "SELECT 1 FROM recommendation_feedback WHERE user_id = ? AND movie_id > 0 AND is_active = 1 AND outcome = 'pending' LIMIT 1",
    [$userId]
) !== null;
$seriesHasCached = $showRecos && db_fetch_one(
    "SELECT 1 FROM recommendation_feedback WHERE user_id = ? AND movie_id < 0 AND is_active = 1 AND outcome = 'pending' LIMIT 1",
    [$userId]
) !== null;
$filmNeedsLoading   = $showRecos && !$filmHasCached;
$seriesNeedsLoading = $showRecos && !$seriesHasCached;

$recos       = [];
$seriesRecos = [];

if ($showRecos) {
    $filmOverrideDna = ($filmDnaSource === 'series')
        ? buildDnaFromTable($userId, 'user_series_dna') : null;

    $seriesUserDna = ($seriesDnaSource === 'series')
        ? buildDnaFromTable($userId, 'user_series_dna')
        : buildDnaFromTable($userId, 'user_dna');

    $recos       = getRecommendations($userId, RECO_LIMIT, $filmOverrideDna);
    $seriesRecos = getSeriesRecommendations($userId, $seriesUserDna, RECO_LIMIT);
}

// Combien manque-t-il pour débloquer (le plus proche des deux)
$missing = max(0, $minRequired - max($filmVotes, $seriesVotes));

// Progression des prélèvements films en attente
$pendingCount = $showRecos ? (int)db_fetch_one(
    "SELECT COUNT(*) AS cnt FROM recommendation_feedback
     WHERE user_id = ? AND is_active = 1 AND outcome = 'pending'",
    [$userId]
)['cnt'] : 0;

// ── État des boutons de recalibrage ───────────────────────────────────────────
function get_refresh_state(string $userId, string $type): array {
    // Crée la table si elle n'existe pas encore
    try {
        getPDO()->exec("CREATE TABLE IF NOT EXISTS reco_refresh_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(64) NOT NULL, type ENUM('film','series') NOT NULL,
            refreshed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            adn_hash VARCHAR(32) NOT NULL, INDEX idx_user_type (user_id, type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}

    $table      = ($type === 'series') ? 'user_series_dna' : 'user_dna';
    $adnRows    = db_fetch_all(
        "SELECT criterio, avg_score FROM {$table} WHERE user_id = ? ORDER BY criterio",
        [$userId]
    );
    $currentHash = md5(json_encode($adnRows));

    $last = db_fetch_one(
        "SELECT refreshed_at, adn_hash FROM reco_refresh_log
         WHERE user_id = ? AND type = ? ORDER BY refreshed_at DESC LIMIT 1",
        [$userId, $type]
    );

    $cooldownOk = !$last || (strtotime($last['refreshed_at']) + 7 * 24 * 3600) <= time();
    $adnChanged = !$last || $last['adn_hash'] !== $currentHash;
    $daysLeft   = 0;

    if ($last && !$cooldownOk) {
        $daysLeft = (int)ceil((strtotime($last['refreshed_at']) + 7 * 24 * 3600 - time()) / 86400);
    }

    $canRefresh = $cooldownOk && $adnChanged;
    $reason     = '';
    if (!$canRefresh) {
        $reason = !$cooldownOk ? "Dispo dans {$daysLeft}j" : "ADN inchangé";
    }

    return ['can_refresh' => $canRefresh, 'reason' => $reason];
}

$filmRefreshState   = $showRecos ? get_refresh_state($userId, 'film')   : ['can_refresh' => false, 'reason' => ''];
$seriesRefreshState = $showRecos ? get_refresh_state($userId, 'series') : ['can_refresh' => false, 'reason' => ''];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>DNA Match</title>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/radars.js" defer></script>
    <meta name="theme-color" content="#050505">

<meta name="apple-mobile-web-app-capable" content="yes">

<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="page-recommendations">
<div class="container">
    <?php renderHeader($user); ?>
    <?php renderNav('recommendations'); ?>

    <main>
        <section class="rooms-header">
            <h1>DNA MATCH</h1>
        </section>

        <?php if (!empty($adn['meta']['primary_dna'])): ?>
        <div class="genre-chips" style="margin-bottom:24px;">
            <?php foreach ($adn['meta']['primary_dna'] as $trait => $val): ?>
                <span class="criteria-chip active" style="text-transform:uppercase; cursor:default; pointer-events:none;">
                    <?= h($trait) ?> <?= $val > 0 ? '+' : '' ?><?= round($val, 2) ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div style="position:relative; margin-bottom:40px;">

        <!-- Helix animation — background layer -->
        <div style="position:absolute; top:0; left:0; width:100%; height:100%; z-index:0; overflow:hidden; pointer-events:none; opacity:0.45; mask-image:linear-gradient(to bottom, black 65%, transparent 100%); -webkit-mask-image:linear-gradient(to bottom, black 65%, transparent 100%);">
            <?php if (file_exists('components/dna-animation.php')) include 'components/dna-animation.php'; ?>
        </div>

        <!-- Foreground content — on top of helix -->
        <div style="position:relative; z-index:1; padding-bottom:60px;">

        <section class="reco-results">
            <?php if (!$showRecos): ?>
                <div style="text-align:center; padding:60px 20px; background:var(--card-bg); border-radius:20px; border:1px solid var(--border);">
                    <div style="font-size:3rem; margin-bottom:20px;">🔒</div>
                    <h3 style="color:var(--pastel-blue); margin-bottom:12px;">SÉQUENÇAGE INCOMPLET</h3>
                    <p style="color:var(--text-dim); max-width:450px; margin:0 auto 20px; font-size:0.9rem;">
                        Atteignez <strong><?= $minRequired ?> analyses</strong> sur les films <em>ou</em> les séries pour débloquer les résonances.
                    </p>
                    <div style="display:flex; flex-direction:column; gap:10px; max-width:320px; margin:0 auto 28px;">
                        <?php foreach ([['Films', $filmVotes], ['Séries', $seriesVotes]] as [$label, $votes]): ?>
                        <div>
                            <div style="display:flex; justify-content:space-between; font-size:0.65rem; color:var(--text-dim); margin-bottom:4px; text-transform:uppercase; letter-spacing:1px;">
                                <span><?= $label ?></span><span><?= $votes ?> / <?= $minRequired ?></span>
                            </div>
                            <div style="background:var(--border); border-radius:4px; height:5px; overflow:hidden;">
                                <div style="background:var(--pastel-blue); height:100%; width:<?= min(100, round($votes / $minRequired * 100)) ?>%; transition:width .4s;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="home.php" class="btn-base active">complétez votre ADN</a>
                </div>

            <?php else: ?>

                <?php /* ── FILMS ─────────────────────────────────────────────── */ ?>
                <?php if ($filmNeedsLoading): ?>
                <div id="reco-loading-film" style="padding:40px 0;text-align:center;">
                    <div style="display:inline-flex;flex-direction:column;align-items:center;gap:14px;">
                        <div class="reco-loader-dna"></div>
                        <span style="font-size:0.6rem;letter-spacing:3px;color:var(--text-dim);font-family:monospace;text-transform:uppercase;">Analyse films…</span>
                    </div>
                </div>
                <div id="reco-content-film" style="display:none;">
                <?php endif; ?>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:12px;">
                    <div>
                        <?php if ($filmDnaSource === 'series'): ?>
                            <h3 class="h-section__label label-glitch" data-text="RÉSONANCES FILMS" style="font-size:0.75rem;letter-spacing:2px;">RÉSONANCES FILMS</h3>
                            <p style="font-size:0.6rem;color:#FA6B6B;opacity:.85;margin:3px 0 0;letter-spacing:.5px;">
                                ADN insuffisant — basé sur votre profil séries
                            </p>
                        <?php else: ?>
                            <h3 class="h-section__label" style="font-size:0.75rem;letter-spacing:2px;">RÉSONANCES FILMS</h3>
                        <?php endif; ?>
                    </div>
                    <button onclick="recoRefresh('film')"
                            <?= $filmRefreshState['can_refresh'] ? '' : 'disabled' ?>
                            title="<?= $filmRefreshState['can_refresh'] ? 'Recalibrer les résonances films' : h($filmRefreshState['reason']) ?>"
                            class="reco-refresh-btn <?= $filmRefreshState['can_refresh'] ? '' : 'reco-refresh-btn--disabled' ?>">
                        ↺ <?= $filmRefreshState['can_refresh'] ? 'Recalibrer' : h($filmRefreshState['reason']) ?>
                    </button>
                </div>

                <?php if (empty($recos)): ?>
                    <p style="color:var(--text-dim); font-size:0.85rem; margin-bottom:40px;">Aucune résonance film détectée.</p>
                <?php else: ?>
                <div class="movie-grid" style="margin-bottom:48px;">
                    <?php foreach ($recos as $reco):
                        $mObj = get_movie_smart((int)$reco['id']);
                        if (!$mObj) continue;
                        $scoreDisplay = null;
                        if ($reco['score'] > 0) {
                            $normalized   = min(99, max(50, round(50 + ($reco['score'] * 49))));
                            $scoreDisplay = $normalized . '% MATCH';
                        }
                    ?>
                    <div class="reco-item-wrapper" style="display:flex; flex-direction:column; gap:8px;">
                        <?php renderMovieCard($mObj, [
                            'extra_label'    => $scoreDisplay,
                            'is_reco'        => true,
                            'is_exploration' => $reco['is_exploration'] ?? false,
                            'show_meta'      => true,
                            'glitch'         => ($filmDnaSource === 'series'),
                        ]); ?>
                        <?php if (!empty($reco['reason'])): ?>
                        <p style="font-size:0.65rem; color:var(--text-dim); font-style:italic; line-height:1.4; padding:0 5px;">
                            <?= $reco['is_exploration'] ? '🔭' : '🧬' ?> <?= h($reco['reason']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($filmNeedsLoading): ?></div><!-- /reco-content-film --><?php endif; ?>

                <?php /* ── SÉRIES ────────────────────────────────────────────── */ ?>
                <?php if ($seriesNeedsLoading): ?>
                <div id="reco-loading-series" style="padding:40px 0;text-align:center;">
                    <div style="display:inline-flex;flex-direction:column;align-items:center;gap:14px;">
                        <div class="reco-loader-dna"></div>
                        <span style="font-size:0.6rem;letter-spacing:3px;color:var(--text-dim);font-family:monospace;text-transform:uppercase;">Analyse séries…</span>
                    </div>
                </div>
                <div id="reco-content-series" style="display:none;">
                <?php endif; ?>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:12px;">
                    <div>
                        <?php if ($seriesDnaSource === 'film'): ?>
                            <h3 class="h-section__label label-glitch" data-text="RÉSONANCES SÉRIES" style="font-size:0.75rem;letter-spacing:2px;">RÉSONANCES SÉRIES</h3>
                            <p style="font-size:0.6rem;color:#FA6B6B;opacity:.85;margin:3px 0 0;letter-spacing:.5px;">
                                ADN insuffisant — basé sur votre profil films
                            </p>
                        <?php else: ?>
                            <h3 class="h-section__label" style="font-size:0.75rem;letter-spacing:2px;">RÉSONANCES SÉRIES</h3>
                        <?php endif; ?>
                    </div>
                    <button onclick="recoRefresh('series')"
                            <?= $seriesRefreshState['can_refresh'] ? '' : 'disabled' ?>
                            title="<?= $seriesRefreshState['can_refresh'] ? 'Recalibrer les résonances séries' : h($seriesRefreshState['reason']) ?>"
                            class="reco-refresh-btn <?= $seriesRefreshState['can_refresh'] ? '' : 'reco-refresh-btn--disabled' ?>">
                        ↺ <?= $seriesRefreshState['can_refresh'] ? 'Recalibrer' : h($seriesRefreshState['reason']) ?>
                    </button>
                </div>

                <?php if (empty($seriesRecos)): ?>
                    <p style="color:var(--text-dim); font-size:0.85rem;">Aucune résonance série détectée.</p>
                <?php else: ?>
                <div class="movie-grid">
                    <?php foreach ($seriesRecos as $reco):
                        $scoreDisplay = null;
                        if ($reco['score'] > 0) {
                            $normalized   = min(99, max(50, round(50 + ($reco['score'] * 49))));
                            $scoreDisplay = $normalized . '% MATCH';
                        }
                    ?>
                    <div class="reco-item-wrapper" style="display:flex; flex-direction:column; gap:8px;">
                        <?php renderMovieCard([
                            'id'      => $reco['id'],
                            'tmdb_id' => $reco['id'],
                            'title'   => $reco['title'],
                            'poster'  => $reco['poster'],
                            'year'    => $reco['year'] ?? null,
                        ], [
                            'extra_label'    => $scoreDisplay,
                            'content_type'   => 'tv',
                            'is_reco'        => true,
                            'is_exploration' => $reco['is_exploration'] ?? false,
                            'show_meta'      => true,
                            'glitch'         => ($seriesDnaSource === 'film'),
                        ]); ?>
                        <?php if (!empty($reco['reason'])): ?>
                        <p style="font-size:0.65rem; color:var(--text-dim); font-style:italic; line-height:1.4; padding:0 5px;">
                            <?= ($reco['is_exploration'] ?? false) ? '🔭' : '🧬' ?> <?= h($reco['reason']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($seriesNeedsLoading): ?></div><!-- /reco-content-series --><?php endif; ?>

            <?php endif; ?>
        </section>


        </div><!-- /foreground -->
        </div><!-- /layered container -->
    </main>
</div>

<div class="flex-spacer"></div>
<?php renderFooter(); ?>

<script src="/assets/js/recommendations.js?v=1" defer></script>
</body>
</html>