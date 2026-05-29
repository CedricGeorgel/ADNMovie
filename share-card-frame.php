<?php
/**
 * SHARE-CARD-FRAME.PHP
 * Page autonome et publique — src de l'iframe de partage.
 * Pas d'authentification, pas de chrome.
 */
header('X-Frame-Options: ALLOWALL');
header('Content-Security-Policy: frame-ancestors *');

require_once __DIR__ . '/functions/utils.php';
require_once __DIR__ . '/functions/ratings_logic.php';
require_once __DIR__ . '/functions/snapshot_logic.php';

// ── Paramètres ────────────────────────────────────────────────────────────────
$userId = trim($_GET['id'] ?? '');
$format = in_array($_GET['format'] ?? '', ['story','square','discord']) ? $_GET['format'] : 'story';

if (!$userId) { http_response_code(404); exit; }
$user = get_user_by_id($userId);
if (!$user || !empty($user['is_anonymized'])) { http_response_code(404); exit; }

// ── Proxy image (élimine CORS pour html2canvas si besoin) ────────────────────
function frame_proxy_img(string $url): string {
    if (empty($url)) return '';
    static $cache = [];
    if (isset($cache[$url])) return $cache[$url];
    $data = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6,
                                CURLOPT_FOLLOWLOCATION => true, CURLOPT_SSL_VERIFYPEER => true,
                                CURLOPT_USERAGENT => 'Mozilla/5.0']);
        $data = curl_exec($ch);
        if (curl_errno($ch)) $data = null;
        curl_close($ch);
    }
    if (!$data) $data = @file_get_contents($url, false,
        stream_context_create(['http' => ['timeout' => 5]]));
    if (!$data) return $cache[$url] = $url;
    $ext  = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    $mime = match($ext) { 'png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp',default=>'image/jpeg' };
    return $cache[$url] = 'data:' . $mime . ';base64,' . base64_encode($data);
}

// ── ADN ───────────────────────────────────────────────────────────────────────
$adnData       = get_user_adn($userId) ?: ['count'=>0,'scores'=>array_fill(0,8,0),'stats'=>[]];
$seriesAdnData = get_user_series_adn($userId);
$hasSeriesAdn  = ($seriesAdnData['count'] >= 1);
$filmScores    = $adnData['scores']       ?? array_fill(0, 8, 0);
$serieScores   = $seriesAdnData['scores'] ?? [];
$statsFilm     = $adnData['stats']        ?? [];
$statsSerie    = $seriesAdnData['stats']  ?? [];

// ── Films ─────────────────────────────────────────────────────────────────────
$lovedRow = db_fetch_one(
    "SELECT r.movie_id, m.title, m.poster, m.year
     FROM ratings r JOIN movies m ON m.tmdb_id = r.movie_id
     WHERE r.user_id = ? AND r.like_score IS NOT NULL
     ORDER BY r.like_score DESC LIMIT 1", [$userId]);
$hatedRow = db_fetch_one(
    "SELECT r.movie_id, m.title, m.poster, m.year
     FROM ratings r JOIN movies m ON m.tmdb_id = r.movie_id
     WHERE r.user_id = ? AND r.like_score IS NOT NULL
     ORDER BY r.like_score ASC LIMIT 1", [$userId]);
if ($hatedRow && $lovedRow && $hatedRow['movie_id'] === $lovedRow['movie_id']) $hatedRow = null;

$avatarUri = frame_proxy_img($user['avatar'] ?? '');
$lovedUri  = $lovedRow ? frame_proxy_img($lovedRow['poster'] ?? '') : '';
$hatedUri  = $hatedRow ? frame_proxy_img($hatedRow['poster'] ?? '') : '';

// ── Trait dominant ────────────────────────────────────────────────────────────
$CRITERIA_KEYS = ['complexite','previsibilite','intensite','malaise','stylisation','dynamique','depaysement','coherence'];
$CRIT_FR       = ['complexite'=>'Complexité','previsibilite'=>'Prévisibilité','intensite'=>'Intensité',
                  'malaise'=>'Malaise','stylisation'=>'Stylisation','dynamique'=>'Dynamique',
                  'depaysement'=>'Dépaysement','coherence'=>'Cohérence'];
$TRAIT_PHRASES = [
    'complexite'    => [true=>'Un esprit qui cherche le sens là où les autres voient le chaos.',false=>'La clarté narrative comme condition du plaisir cinématographique.'],
    'previsibilite' => [true=>'Le cinéma comme rituel rassurant, la surprise n\'est pas bienvenue.',false=>'Le cinéma comme expérience émotionnelle totale.'],
    'intensite'     => [true=>'Un spectateur qui veut être traversé, pas simplement distrait.',false=>'Le cinéma comme espace de contemplation et de silence.'],
    'malaise'       => [true=>'Attiré par les zones d\'inconfort que seul l\'écran peut créer.',false=>'Le cinéma comme refuge, jamais comme épreuve.'],
    'stylisation'   => [true=>'La forme comme langage, l\'image comme pensée visible.',false=>'Le réel brut, sans filtre ni artifice esthétique.'],
    'dynamique'     => [true=>'L\'adrénaline narrative comme seul carburant.',false=>'Le temps suspendu, la lenteur comme révélation.'],
    'depaysement'   => [true=>'Un voyageur des mondes que seul l\'écran peut ouvrir.',false=>'Le familier comme terrain de l\'exploration universelle.'],
    'coherence'     => [true=>'La logique interne du récit comme fondement du plaisir.',false=>'L\'irréel assumé, la cohérence n\'est qu\'une convention.'],
];
$dominantCrit = null; $dominantDir = true; $dominantAbs = 0.0;
foreach ($CRITERIA_KEYS as $i => $c) {
    if (!isset($statsFilm[$c])) continue;
    $abs = abs((float)($filmScores[$i] ?? 0));
    if ($abs > $dominantAbs) { $dominantAbs = $abs; $dominantCrit = $c; $dominantDir = (float)($filmScores[$i] ?? 0) >= 0; }
}
$dominantLabel  = $dominantCrit ? $CRIT_FR[$dominantCrit]                           : '—';
$dominantPhrase = $dominantCrit ? ($TRAIT_PHRASES[$dominantCrit][$dominantDir] ?? '') : '';

// ── Mutation rate ─────────────────────────────────────────────────────────────
$prevPeriodKey  = get_previous_period_key('monthly');
$mutationRate   = get_dna_mutation_rate($userId, $prevPeriodKey, 'monthly');
$mutationPct    = number_format($mutationRate, 0) . '%';
$stabilityLabel = $mutationRate < 10 ? 'PROFIL STABLE' : ($mutationRate < 30 ? 'EN ÉVOLUTION' : 'EN MUTATION');

// ── Méta ──────────────────────────────────────────────────────────────────────
$username    = h($user['username']);
$memberSince = isset($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : '';
$totalRated  = (int)($adnData['count'] ?? 0);

// ── Helpers ───────────────────────────────────────────────────────────────────
function frame_helix(array $fScores, array $sScores, ?array $sf, ?array $ss, int $w): void {
    $KEYS  = ['complexite','previsibilite','intensite','malaise','stylisation','dynamique','depaysement','coherence'];
    $KEXC  = 'coherence';
    $KNORM = array_values(array_filter($KEYS, fn($k) => $k !== $KEXC));
    $norm  = function(array $raw) use ($KEYS): array {
        $o = [];
        foreach ($KEYS as $i => $k) {
            $v = array_is_list($raw) ? ($raw[$i] ?? null) : ($raw[$k] ?? null);
            $o[$k] = ($v !== null && $v !== '') ? (float)$v : null;
        }
        return $o;
    };
    $f  = $norm($fScores);
    $s  = $norm($sScores ?: []);
    $ok = !empty($sScores);
    $vr = fn($k,$v,$st) => !isset($st[$k]) ? 'null' : ($v!==null&&$v<0 ? 'negatif' : 'positif');
    $b  = fn($v,$k,$t)  => SITE_URL."/assets/ADN/Barres/{$v}_{$k}_{$t}.png";
    $st = 'position:absolute;inset:0;width:100%;height:100%;display:block;';
    ?>
    <div style="width:<?= $w ?>px;flex-shrink:0;">
        <div style="position:relative;width:100%;aspect-ratio:492/1324;">
            <img src="<?= SITE_URL ?>/assets/fond.png" alt="" style="<?= $st ?>object-fit:cover;" loading="eager">
            <?php foreach ($KNORM as $k): $v=$vr($k,$f[$k]??null,$sf); ?>
            <img src="<?= $b($v,$k,'film') ?>" alt="" loading="eager" onerror="this.style.display='none'" style="<?= $st ?>">
            <?php endforeach; ?>
            <?php $v=$vr($KEXC,$f[$KEXC]??null,$sf); ?>
            <img src="<?= $b($v,$KEXC,'film') ?>" alt="" loading="eager" onerror="this.style.display='none'" style="<?= $st ?>">
            <?php if ($ok): ?>
            <?php foreach ($KNORM as $k): $v=$vr($k,$s[$k]??null,$ss); ?>
            <img src="<?= $b($v,$k,'serie') ?>" alt="" loading="eager" onerror="this.style.display='none'" style="<?= $st ?>">
            <?php endforeach; ?>
            <?php $v=$vr($KEXC,$s[$KEXC]??null,$ss); ?>
            <img src="<?= $b($v,$KEXC,'serie') ?>" alt="" loading="eager" onerror="this.style.display='none'" style="<?= $st ?>">
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function frame_film(string $uri, string $title, string $year, string $label, string $col, string $posterH = '130px', bool $grow = true): void { ?>
    <div style="display:flex;flex-direction:column;gap:5px;min-width:0;<?= $grow ? 'flex:1;' : 'flex:0 0 auto;' ?>">
        <span style="font-size:0.52rem;letter-spacing:2px;text-transform:uppercase;color:<?= $col ?>;font-family:'Courier New',monospace;flex-shrink:0;"><?= $label ?></span>
        <div style="border-radius:5px;overflow:hidden;background:#111;height:<?= $posterH ?>;flex-shrink:0;">
            <?php if ($uri): ?>
                <img src="<?= $uri ?>" alt="" loading="eager" style="width:100%;height:100%;object-fit:cover;display:block;">
            <?php else: ?>
                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#444;font-size:0.6rem;font-family:'Courier New',monospace;padding:8px;box-sizing:border-box;text-align:center;"><?= h($title) ?></div>
            <?php endif; ?>
        </div>
        <span style="font-size:0.68rem;font-weight:bold;color:#e0e0e0;font-family:'Courier New',monospace;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;line-height:1.4;padding-bottom:4px;flex-shrink:0;"><?= h($title) ?></span>
        <?php if ($year): ?>
        <span style="font-size:0.58rem;color:#555;font-family:'Courier New',monospace;flex-shrink:0;"><?= h($year) ?></span>
        <?php endif; ?>
    </div>
<?php }

function frame_bg(): void { ?>
    <div style="position:absolute;inset:0;overflow:hidden;pointer-events:none;">
        <div style="position:absolute;inset:0;background:radial-gradient(ellipse 65% 75% at 32% 52%,rgba(184,167,231,0.09) 0%,rgba(5,5,5,0.55) 100%);"></div>
    </div>
<?php }

function frame_bottom(string $pct, string $stability, string $trait, string $phrase, bool $showPhrase = true): void { ?>
    <div style="flex-shrink:0;padding-top:6px;">
        <div style="display:flex;align-items:baseline;gap:16px;margin-bottom:<?= $showPhrase ? '5px' : '0' ?>;">
            <div style="flex-shrink:0;">
                <span style="font-size:0.85rem;font-weight:bold;color:#e0e0e0;font-family:'Courier New',monospace;"><?= h($pct) ?></span><br>
                <span style="font-size:0.48rem;letter-spacing:1.5px;text-transform:uppercase;color:#444;font-family:'Courier New',monospace;"><?= h($stability) ?></span>
            </div>
            <div style="flex-shrink:0;">
                <span style="font-size:0.95rem;font-weight:bold;color:#E8C07A;font-family:'Courier New',monospace;"><?= h($trait) ?></span><br>
                <span style="font-size:0.48rem;letter-spacing:1.5px;text-transform:uppercase;color:#444;font-family:'Courier New',monospace;">TRAIT DOMINANT</span>
            </div>
        </div>
        <?php if ($showPhrase && $phrase): ?>
        <p style="margin:0;font-size:0.78rem;line-height:1.3;color:#7EC8E3;font-family:'Courier New',monospace;font-weight:bold;"><?= h($phrase) ?></p>
        <?php endif; ?>
    </div>
<?php }

// ── Dimensions par format ─────────────────────────────────────────────────────
$dims = match($format) {
    'square'  => ['w' => 500, 'h' => 500],
    'discord' => ['w' => 600, 'h' => 380],
    default   => ['w' => 360, 'h' => 640],
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= $username ?> · ADN Movie</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        html, body { width:<?= $dims['w'] ?>px; height:<?= $dims['h'] ?>px; overflow:hidden; background:#050505; }
    </style>
</head>
<body>

<?php if ($format === 'story'): ?>
<!-- STORY 360×640 -->
<div style="width:360px;height:640px;position:relative;background:#050505;overflow:hidden;">
    <?php frame_bg(); ?>
    <div style="position:relative;z-index:1;display:flex;flex-direction:column;height:100%;padding:18px 18px 16px;box-sizing:border-box;">
        <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
            <img src="<?= $avatarUri ?: SITE_URL.'/assets/default-avatar.png' ?>" loading="eager"
                 style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:1px solid #1e1e1e;flex-shrink:0;">
            <div style="flex:1;min-width:0;">
                <p style="margin:0 0 2px;font-size:0.85rem;font-weight:bold;letter-spacing:1px;color:#e0e0e0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding-bottom:4px;"><?= $username ?></p>
                <p style="margin:0;font-size:0.55rem;color:#555;"><?= $totalRated ?> analyse<?= $totalRated>1?'s':'' ?><?php if ($memberSince): ?> depuis le <?= $memberSince ?><?php endif; ?></p>
            </div>
            <img src="<?= SITE_URL ?>/assets/Icons/Named_logo1.png" alt="ADN Movie" loading="eager"
                 style="height:20px;object-fit:contain;flex-shrink:0;opacity:0.8;">
        </div>
        <hr style="border:none;border-top:1px solid #1a1a1a;margin:10px 0;flex-shrink:0;">
        <div style="flex:1;display:flex;gap:14px;min-height:0;overflow:hidden;">
            <?php frame_helix($filmScores, $hasSeriesAdn?$serieScores:[], $statsFilm, $hasSeriesAdn?$statsSerie:null, 155); ?>
            <div style="flex:1;display:flex;flex-direction:column;gap:10px;min-width:0;overflow:hidden;">
                <?php if ($lovedRow) frame_film($lovedUri, $lovedRow['title'], $lovedRow['year']??'', '❤ Plus aimé',  '#9dffb0', '130px', false); ?>
                <?php if ($hatedRow) frame_film($hatedUri, $hatedRow['title'], $hatedRow['year']??'', '☠ Moins aimé', '#FF6B6B', '130px', false); ?>
            </div>
        </div>
        <hr style="border:none;border-top:1px solid #1a1a1a;margin:8px 0 0;flex-shrink:0;">
        <?php frame_bottom($mutationPct, $stabilityLabel, $dominantLabel, $dominantPhrase, true); ?>
    </div>
</div>

<?php elseif ($format === 'square'): ?>
<!-- CARRÉ 500×500 -->
<div style="width:500px;height:500px;position:relative;background:#050505;overflow:hidden;">
    <?php frame_bg(); ?>
    <div style="position:relative;z-index:1;display:flex;flex-direction:column;height:100%;padding:18px 18px 14px;box-sizing:border-box;">
        <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
            <img src="<?= $avatarUri ?: SITE_URL.'/assets/default-avatar.png' ?>" loading="eager"
                 style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1px solid #1e1e1e;flex-shrink:0;">
            <div style="flex:1;min-width:0;">
                <p style="margin:0 0 2px;font-size:0.82rem;font-weight:bold;letter-spacing:1px;color:#e0e0e0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding-bottom:4px;"><?= $username ?></p>
                <p style="margin:0;font-size:0.52rem;color:#555;"><?= $totalRated ?> analyse<?= $totalRated>1?'s':'' ?><?php if ($memberSince): ?> depuis le <?= $memberSince ?><?php endif; ?></p>
            </div>
            <img src="<?= SITE_URL ?>/assets/Icons/Named_logo1.png" alt="ADN Movie" loading="eager"
                 style="height:20px;object-fit:contain;flex-shrink:0;opacity:0.8;">
        </div>
        <hr style="border:none;border-top:1px solid #1a1a1a;margin:10px 0;flex-shrink:0;">
        <div style="flex:1;display:flex;gap:16px;min-height:0;overflow:hidden;">
            <?php frame_helix($filmScores, $hasSeriesAdn?$serieScores:[], $statsFilm, $hasSeriesAdn?$statsSerie:null, 120); ?>
            <div style="flex:1;display:flex;flex-direction:row;gap:10px;min-width:0;overflow:hidden;">
                <?php if ($lovedRow) frame_film($lovedUri, $lovedRow['title'], $lovedRow['year']??'', '❤ Plus aimé',  '#9dffb0', '180px'); ?>
                <?php if ($hatedRow) frame_film($hatedUri, $hatedRow['title'], $hatedRow['year']??'', '☠ Moins aimé', '#FF6B6B', '180px'); ?>
            </div>
        </div>
        <hr style="border:none;border-top:1px solid #1a1a1a;margin:8px 0 0;flex-shrink:0;">
        <?php frame_bottom($mutationPct, $stabilityLabel, $dominantLabel, $dominantPhrase, true); ?>
    </div>
</div>

<?php else: ?>
<!-- DISCORD 600×380 -->
<div style="width:600px;height:380px;position:relative;background:#050505;overflow:hidden;">
    <?php frame_bg(); ?>
    <div style="position:relative;z-index:1;display:flex;flex-direction:column;height:100%;padding:18px 20px 14px;box-sizing:border-box;">
        <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
            <img src="<?= $avatarUri ?: SITE_URL.'/assets/default-avatar.png' ?>" loading="eager"
                 style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:1px solid #1e1e1e;flex-shrink:0;">
            <div style="flex:1;min-width:0;">
                <p style="margin:0 0 2px;font-size:0.8rem;font-weight:bold;letter-spacing:1px;color:#e0e0e0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding-bottom:4px;"><?= $username ?></p>
                <p style="margin:0;font-size:0.5rem;color:#555;"><?= $totalRated ?> analyse<?= $totalRated>1?'s':'' ?><?php if ($memberSince): ?> depuis le <?= $memberSince ?><?php endif; ?></p>
            </div>
            <img src="<?= SITE_URL ?>/assets/Icons/Named_logo1.png" alt="ADN Movie" loading="eager"
                 style="height:20px;object-fit:contain;flex-shrink:0;opacity:0.8;">
        </div>
        <hr style="border:none;border-top:1px solid #1a1a1a;margin:10px 0;flex-shrink:0;">
        <div style="flex:1;display:flex;gap:18px;min-height:0;overflow:hidden;">
            <?php frame_helix($filmScores, $hasSeriesAdn?$serieScores:[], $statsFilm, $hasSeriesAdn?$statsSerie:null, 100); ?>
            <div style="flex:1;display:flex;gap:14px;min-width:0;overflow:hidden;">
                <?php if ($lovedRow) frame_film($lovedUri, $lovedRow['title'], $lovedRow['year']??'', '❤ Plus aimé',  '#9dffb0', '160px'); ?>
                <?php if ($hatedRow) frame_film($hatedUri, $hatedRow['title'], $hatedRow['year']??'', '☠ Moins aimé', '#FF6B6B', '160px'); ?>
            </div>
        </div>
        <hr style="border:none;border-top:1px solid #1a1a1a;margin:8px 0 0;flex-shrink:0;">
        <div style="flex-shrink:0;padding-top:6px;display:flex;align-items:baseline;gap:20px;flex-wrap:wrap;">
            <div>
                <span style="font-size:0.9rem;font-weight:bold;color:#e0e0e0;font-family:'Courier New',monospace;"><?= h($mutationPct) ?></span>
                <span style="font-size:0.45rem;letter-spacing:1.5px;text-transform:uppercase;color:#444;font-family:'Courier New',monospace;margin-left:4px;"><?= h($stabilityLabel) ?></span>
            </div>
            <div>
                <span style="font-size:0.9rem;font-weight:bold;color:#E8C07A;font-family:'Courier New',monospace;"><?= h($dominantLabel) ?></span>
                <span style="font-size:0.45rem;letter-spacing:1.5px;text-transform:uppercase;color:#444;font-family:'Courier New',monospace;margin-left:4px;">TRAIT DOMINANT</span>
            </div>
            <?php if ($dominantPhrase): ?>
            <p style="margin:0;font-size:0.68rem;color:#7EC8E3;font-family:'Courier New',monospace;font-style:italic;flex:1;min-width:200px;line-height:1.3;"><?= h($dominantPhrase) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

</body>
</html>
