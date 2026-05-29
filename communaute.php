<?php
/**
 * COMMUNAUTE.PHP — Spécimens actifs & recherche de membres
 */
require_once 'functions/utils.php';
require_once 'functions/auth.php';
require_once 'functions/core_db.php';
require_once 'components/header.php';
require_once 'components/nav.php';
require_once 'components/footer.php';

require_once 'components/dna-bars.php';

// ── Calcul des résonances ADN (80%+, cosinus) ──────────────────────────────
// $type = 'movie' → user_dna + dna_match_reveals
// $type = 'series' → user_series_dna + series_dna_match_reveals
function computeDnaMatches(string $userId, string $type = 'movie'): array {
    $criteria = ['complexite','previsibilite','intensite','malaise',
                 'stylisation','dynamique','depaysement','coherence'];

    $dnaTable    = $type === 'series' ? 'user_series_dna'         : 'user_dna';
    $revealTable = $type === 'series' ? 'series_dna_match_reveals' : 'dna_match_reveals';

    // Vérifie que les tables requises existent avant toute requête
    $tablesOk = db_fetch_all(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?)",
        [$dnaTable, $revealTable]
    );
    if (count($tablesOk) < 2) return [];

    $myRows = db_fetch_all("SELECT criterio, avg_score FROM $dnaTable WHERE user_id = ?", [$userId]);
    if (empty($myRows)) return [];
    $myDna = array_column($myRows, 'avg_score', 'criterio');

    $normMe = 0;
    foreach ($criteria as $c) { $v = (float)($myDna[$c] ?? 0); $normMe += $v * $v; }
    if ($normMe == 0) return [];

    $allRows = db_fetch_all(
        "SELECT ud.user_id, ud.criterio, ud.avg_score
         FROM $dnaTable ud
         JOIN users u ON u.id = ud.user_id COLLATE utf8mb4_unicode_ci
         WHERE ud.user_id COLLATE utf8mb4_unicode_ci != ? AND u.is_anonymized = 0",
        [$userId]
    );

    $byUser = [];
    foreach ($allRows as $r) {
        $byUser[$r['user_id']][$r['criterio']] = (float)$r['avg_score'];
    }

    $myReveals = array_column(
        db_fetch_all("SELECT match_user_id FROM $revealTable WHERE user_id = ?", [$userId]),
        'match_user_id'
    );
    $revealedMe = array_column(
        db_fetch_all("SELECT user_id FROM $revealTable WHERE match_user_id = ?", [$userId]),
        'user_id'
    );

    $matches = [];
    foreach ($byUser as $uid => $theirDna) {
        $dot = $normB = 0;
        foreach ($criteria as $c) {
            $a = (float)($myDna[$c] ?? 0);
            $b = (float)($theirDna[$c] ?? 0);
            $dot  += $a * $b;
            $normB += $b * $b;
        }
        if ($normB == 0) continue;
        $cosine = $dot / (sqrt($normMe) * sqrt($normB));
        $pct    = ($cosine + 1) / 2 * 100;
        if ($pct < 80) continue;

        $uidStr       = (string)$uid;
        $iRevealed    = in_array($uidStr, $myReveals, true);
        $theyRevealed = in_array($uidStr, $revealedMe, true);
        $mutual       = $iRevealed && $theyRevealed;

        $userData = null;
        if ($mutual) {
            $userData = db_fetch_one('SELECT id, username, avatar FROM users WHERE id = ?', [$uidStr]);
        }

        $matches[] = [
            'user_id'       => $uidStr,
            'match_pct'     => round($pct, 1),
            'their_dna'     => $theirDna,
            'i_revealed'    => $iRevealed,
            'they_revealed' => $theyRevealed,
            'mutual'        => $mutual,
            'user'          => $userData,
        ];
    }

    usort($matches, fn($a, $b) => $b['match_pct'] <=> $a['match_pct']);
    return $matches;
}

// Rendu SVG d'un mini radar (110×110, sans labels)
function renderMiniRadarSvg(array $dna, string $color = 'rgba(167,199,231,0.85)'): string {
    $keys = ['complexite','previsibilite','intensite','malaise',
             'stylisation','dynamique','depaysement','coherence'];
    $n = 8; $cx = 55; $cy = 55; $R = 38;
    $toR  = fn(float $v): float => ($v + 10) / 20 * $R;
    $pt   = function(int $i, float $v) use ($n, $cx, $cy, $toR): array {
        $angle = (2 * M_PI * $i / $n) - M_PI / 2;
        $r = $toR($v);
        return ['x' => $cx + $r * cos($angle), 'y' => $cy + $r * sin($angle)];
    };

    // Grille
    $grid = '';
    foreach ([0.25, 0.5, 0.75, 1.0] as $f) {
        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            $angle = (2 * M_PI * $i / $n) - M_PI / 2;
            $pts[] = round($cx + $R * $f * cos($angle), 2) . ',' . round($cy + $R * $f * sin($angle), 2);
        }
        $grid .= '<polygon points="' . implode(' ', $pts) . '" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="0.5"/>';
    }

    // Axes
    $axes = '';
    for ($i = 0; $i < $n; $i++) {
        $e = $pt($i, 10);
        $axes .= '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . round($e['x'], 2) . '" y2="' . round($e['y'], 2) . '" stroke="rgba(255,255,255,0.07)" stroke-width="0.5"/>';
    }

    // Polygone données
    $pts = [];
    foreach ($keys as $i => $k) {
        $p    = $pt($i, (float)($dna[$k] ?? 0));
        $pts[] = round($p['x'], 2) . ',' . round($p['y'], 2);
    }
    $bgColor = str_replace('0.85)', '0.12)', $color);
    $poly    = '<polygon points="' . implode(' ', $pts) . '" fill="' . $bgColor . '" stroke="' . $color . '" stroke-width="1.5"/>';

    return '<svg viewBox="0 0 110 110" width="110" height="110" style="display:block;">'
         . $grid . $axes . $poly . '</svg>';
}

$currentUserId = $_SESSION['user_id'] ?? null;
$currentUser   = $currentUserId ? get_user_by_id($currentUserId) : null;

// Seuil de 10 votes minimum pour afficher les correspondances
$filmVotes = $seriesVotes = 0;
if ($currentUserId) {
    $r = db_fetch_one('SELECT COALESCE(MAX(vote_count), 0) as n FROM user_dna WHERE user_id = ?', [$currentUserId]);
    $filmVotes = (int)($r['n'] ?? 0);

    $seriesExists = db_fetch_one(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_series_dna'",
        []
    );
    if ($seriesExists) {
        $r = db_fetch_one('SELECT COALESCE(MAX(vote_count), 0) as n FROM user_series_dna WHERE user_id = ?', [$currentUserId]);
        $seriesVotes = (int)($r['n'] ?? 0);
    }
}
$dnaMatches       = ($currentUserId && $filmVotes   >= 10) ? computeDnaMatches($currentUserId, 'movie')  : [];
$seriesDnaMatches = ($currentUserId && $seriesVotes >= 10) ? computeDnaMatches($currentUserId, 'series') : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Communauté — ADN Movie</title>
    <link rel="icon" href="/assets/Icons/Logo2.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/assets/Icons/Logo3.png">
    <meta property="og:site_name" content="ADN Movie">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Communauté · ADN Movie">
    <meta property="og:description" content="Découvrez les spécimens les plus actifs de la communauté ADN Movie.">
    <meta property="og:image" content="https://adnmovie.fr/assets/Icons/Named_logo1.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime('assets/style.css') ?>">
    <?php if (function_exists('renderScripts')) renderScripts(); ?>
    <meta name="theme-color" content="#050505">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="page-communaute">
<div class="container">
    <?php renderHeader($currentUser); ?>
    <?php renderNav('communaute'); ?>

    <main>
        <section class="rooms-header">
            <h1>COMMUNAUTÉ</h1>
        </section>

        <!-- Search -->
        <input id="searchInput" type="text" class="search-input"
               placeholder="Rechercher un spécimen par pseudo…"
               autocomplete="off" autocorrect="off" autocapitalize="off">

        <!-- Spécimens les plus actifs -->
        <p class="communaute-section-label">Spécimens les plus actifs</p>
        <div class="community-grid" id="communityGrid"></div>

        <?php if ($currentUserId && (!empty($dnaMatches) || !empty($seriesDnaMatches))): ?>
        <!-- ── Résonances ADN ─────────────────────────────────────── -->
        <div class="dna-section">
            <h2 class="dna-section-title">RÉSONANCES ADN</h2>
            <p class="dna-section-desc">Ces séquences génomiques présentent une convergence supérieure à 80% avec la tienne. Un accord bilatéral est requis pour lever l'anonymat.</p>

            <?php if (!empty($dnaMatches)): ?>
            <p class="dna-type-label">Génomes ayant le même ADN de film</p>
            <div class="dna-match-grid dna-match-grid--film">
                <?php foreach (array_slice($dnaMatches, 0, 3) as $m): ?>
                <div class="dna-match-card" data-match-id="<?= h($m['user_id']) ?>" data-match-pct="<?= $m['match_pct'] ?>">
                    <div class="dna-card-header">
                    <?php if ($m['mutual'] && !empty($m['user'])): ?>
                        <div class="dna-card-user">
                            <a href="adn.php?id=<?= urlencode($m['user']['id']) ?>">
                                <img src="<?= h($m['user']['avatar'] ?? 'assets/default-avatar.png') ?>"
                                     onerror="this.src='assets/default-avatar.png'"
                                     class="dna-card-avatar">
                            </a>
                            <div class="dna-card-info">
                                <a href="adn.php?id=<?= urlencode($m['user']['id']) ?>" class="dna-card-username"><?= h($m['user']['username']) ?></a>
                                <div class="dna-card-resonance">Lien génomique tissé · <?= $m['match_pct'] ?>% de résonance</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="dna-card-user">
                            <img src="assets/avatar-glitch.gif" class="dna-card-avatar--anon">
                            <div class="dna-card-info">
                                <span class="label-glitch dna-card-genome-label" data-text="GÉNOME_INCONNU">GÉNOME_INCONNU</span>
                                <div class="dna-card-pct"><?= $m['match_pct'] ?>% de résonance génomique</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    </div>
                    <?= renderDnaBarsPair($currentUserId, $m['user_id'], $m['match_pct']) ?>
                    <div class="dna-card-cta">
                    <?php if ($m['mutual']): ?>
                        <div class="dna-card-established">Résonance ADN établie</div>
                    <?php elseif ($m['i_revealed'] && !$m['they_revealed']): ?>
                        <button class="btn-dna-cancel dna-sync-btn" data-match-id="<?= h($m['user_id']) ?>" data-state="pending">Annuler la synchronisation</button>
                    <?php elseif ($m['they_revealed'] && !$m['i_revealed']): ?>
                        <button class="btn-base active dna-sync-btn" data-match-id="<?= h($m['user_id']) ?>" data-state="validate">Valider la synchronisation</button>
                    <?php else: ?>
                        <button class="btn-dna-sync dna-sync-btn" data-match-id="<?= h($m['user_id']) ?>" data-state="none">Synchroniser les ADN</button>
                    <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($seriesDnaMatches)): ?>
            <p class="dna-type-label">Génomes ayant le même ADN de série</p>
            <div class="dna-match-grid">
                <?php foreach (array_slice($seriesDnaMatches, 0, 3) as $m): ?>
                <div class="dna-match-card" data-match-id="<?= h($m['user_id']) ?>" data-match-pct="<?= $m['match_pct'] ?>" data-dna-type="series">
                    <div class="dna-card-header">
                    <?php if ($m['mutual'] && !empty($m['user'])): ?>
                        <div class="dna-card-user">
                            <a href="adn.php?id=<?= urlencode($m['user']['id']) ?>">
                                <img src="<?= h($m['user']['avatar'] ?? 'assets/default-avatar.png') ?>"
                                     onerror="this.src='assets/default-avatar.png'"
                                     class="dna-card-avatar">
                            </a>
                            <div class="dna-card-info">
                                <a href="adn.php?id=<?= urlencode($m['user']['id']) ?>" class="dna-card-username"><?= h($m['user']['username']) ?></a>
                                <div class="dna-card-resonance">Lien génomique tissé · <?= $m['match_pct'] ?>% de résonance</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="dna-card-user">
                            <img src="assets/avatar-glitch.gif" class="dna-card-avatar--anon">
                            <div class="dna-card-info">
                                <span class="label-glitch dna-card-genome-label" data-text="GÉNOME_INCONNU">GÉNOME_INCONNU</span>
                                <div class="dna-card-pct"><?= $m['match_pct'] ?>% de résonance génomique</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    </div>
                    <?= renderDnaBarsPair($currentUserId, $m['user_id'], $m['match_pct']) ?>
                    <div class="dna-card-cta">
                    <?php if ($m['mutual']): ?>
                        <div class="dna-card-established">Résonance ADN établie</div>
                    <?php elseif ($m['i_revealed'] && !$m['they_revealed']): ?>
                        <button class="btn-dna-cancel dna-sync-btn" data-match-id="<?= h($m['user_id']) ?>" data-state="pending" data-dna-type="series">Annuler la synchronisation</button>
                    <?php elseif ($m['they_revealed'] && !$m['i_revealed']): ?>
                        <button class="btn-base active dna-sync-btn" data-match-id="<?= h($m['user_id']) ?>" data-state="validate" data-dna-type="series">Valider la synchronisation</button>
                    <?php else: ?>
                        <button class="btn-dna-sync dna-sync-btn" data-match-id="<?= h($m['user_id']) ?>" data-state="none" data-dna-type="series">Synchroniser les ADN</button>
                    <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>

    </main>
</div>

<div class="flex-spacer"></div>
<?php renderFooter(); ?>

<script>
const IS_LOGGED = <?= $currentUser ? 'true' : 'false' ?>;
</script>
<script src="/assets/js/communaute.js?v=<?= filemtime('assets/js/communaute.js') ?>" defer></script>
</body>
</html>
