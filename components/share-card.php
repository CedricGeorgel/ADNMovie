<?php
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
require_once 'functions/utils.php'; // démarre la session persistante (30j)
require_once 'functions/auth.php';
require_once 'functions/ratings_logic.php';
require_once 'functions/snapshot_logic.php';

$targetId = $_GET['id'] ?? $_SESSION['user_id'] ?? null;
if (!$targetId) { header('Location: index.php'); exit; }

$targetUser = get_user_by_id($targetId);
if (!$targetUser || !empty($targetUser['is_anonymized'])) { header('Location: index.php'); exit; }

$period  = in_array($_GET['period'] ?? '', ['monthly','quarterly','yearly']) ? $_GET['period'] : 'monthly';
$isAdmin = has_role('admin');
$preview = isset($_GET['preview']) && $isAdmin;

if (!$preview && !is_card_visible($period, 25)) {
    $daysLeft = 25 - (int)date('d');
?><!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Bientôt</title>
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime('assets/style.css') ?>">
</head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;flex-direction:column;gap:16px;text-align:center;padding:40px;">
<p style="font-size:0.6rem;letter-spacing:3px;text-transform:uppercase;color:var(--text-dim);">Séquence ADN · Récapitulatif</p>
<h1 style="font-size:2rem;font-weight:900;">Dans <?= $daysLeft ?> jour<?= $daysLeft > 1 ? 's' : '' ?></h1>
<p style="color:var(--text-dim);font-size:0.8rem;">Disponible le 25 <?= get_period_label($period) ?></p>
<a href="adn.php?id=<?= h($targetId) ?>" class="btn-base" style="margin-top:8px;">Voir mon ADN</a>
</body></html><?php
    exit;
}

$adnData  = get_user_adn($targetId) ?: ['count'=>0,'scores'=>[],'meta'=>[],'stats'=>[]];
$scores   = $adnData['scores'] ?? [];
$primary  = $adnData['meta']['primary_dna'] ?? [];
$count    = (int)($adnData['count'] ?? 0);
$cardData = get_period_card_data($targetId, $period);

// Mapping critères : key, label, pôle négatif, pôle positif, % Y dans SVG, côté
$criteriaMap = [
    ['key'=>'complexite',    'label'=>'Complexité',    'neg'=>'Récit évident',      'pos'=>'Récit alambiqué',    'pct_y'=>15.3, 'side'=>'left'],
    ['key'=>'previsibilite', 'label'=>'Prévisibilité', 'neg'=>'Imprévisible',        'pos'=>'Récit prévisible',   'pct_y'=>26.0, 'side'=>'right'],
    ['key'=>'intensite',     'label'=>'Intensité',     'neg'=>'Détaché d\'émotions', 'pos'=>'Très chargé',        'pct_y'=>30.7, 'side'=>'left'],
    ['key'=>'malaise',       'label'=>'Malaise',       'neg'=>'Réconfortant',        'pos'=>'Dérangeant',         'pct_y'=>35.4, 'side'=>'right'],
    ['key'=>'stylisation',   'label'=>'Stylisation',   'neg'=>'Brut / naturaliste',  'pos'=>'Très stylisé',       'pct_y'=>60.4, 'side'=>'right'],
    ['key'=>'dynamique',     'label'=>'Dynamique',     'neg'=>'Récit lent',          'pos'=>'Intense & prenant',  'pct_y'=>65.2, 'side'=>'left'],
    ['key'=>'depaysement',   'label'=>'Dépaysement',   'neg'=>'Aucun ailleurs',      'pos'=>'Dépaysant',          'pct_y'=>74.7, 'side'=>'left'],
    ['key'=>'coherence',     'label'=>'Cohérence',     'neg'=>'Incohérent',          'pos'=>'Très cohérent',      'pct_y'=>90.1, 'side'=>'right'],
];

$keys = array_column($criteriaMap, 'key');

// Calcul scores et pourcentages
$totalAbs = 0;
foreach ($scores as $v) $totalAbs += abs((float)$v);

$criteriaData = [];
foreach ($criteriaMap as $i => $cm) {
    $val  = (float)($scores[$i] ?? 0);
    $pct  = $totalAbs > 0 ? round(abs($val) / $totalAbs * 100, 1) : 0;
    $pole = $val > 0 ? $cm['pos'] : ($val < 0 ? $cm['neg'] : $cm['label']);
    $col  = $val > 0 ? '#B8A7E7' : ($val < 0 ? '#E8C07A' : 'rgba(255,255,255,0.25)');
    $criteriaData[] = array_merge($cm, ['val'=>$val, 'pct'=>$pct, 'pole'=>$pole, 'color'=>$col]);
}

// Archétype
function generateArchetype(array $primary, int $count): string {
    if ($count < 3) return "Séquence en cours d'initialisation";
    if (empty($primary)) return "Profil inclassifiable";
    $topKey = array_key_first($primary);
    $topVal = reset($primary);
    $pool = [
        'complexite'    => ['pos'=>["Refuse le cinéma qui ne demande rien","Le puzzle avant le plaisir"],
                            'neg'=>["Préfère la clarté","Méfiant envers l'obscurantisme"]],
        'previsibilite' => ['pos'=>["Aime anticiper chaque retournement","Confort dans la structure"],
                            'neg'=>["Allergique au récit prévisible","Aime être pris par surprise"]],
        'intensite'     => ['pos'=>["Le cinéma comme expérience émotionnelle totale","Cherche à être ému"],
                            'neg'=>["Observe sans s'impliquer","Garde ses distances"]],
        'malaise'       => ['pos'=>["L'inconfort comme critère","Attire le cinéma qui dérange"],
                            'neg'=>["Le cinéma comme refuge","Cherche le réconfort"]],
        'stylisation'   => ['pos'=>["Voit chaque plan comme une peinture","L'esthétique d'abord"],
                            'neg'=>["La vérité brute avant tout","Méfiant envers les films trop beaux"]],
        'dynamique'     => ['pos'=>["Veut être tenu en haleine","Le rythme prime"],
                            'neg'=>["Apprécie la lenteur","Le temps suspendu comme plaisir"]],
        'depaysement'   => ['pos'=>["Le cinéma comme passeport","Cherche à fuir dans chaque film"],
                            'neg'=>["Préfère le familier","Reste ancré dans le réel"]],
        'coherence'     => ['pos'=>["Exige la vraisemblance","L'incohérence le sort du film"],
                            'neg'=>["Accepte l'irréel sans conditions","L'impossible ne le dérange pas"]],
    ];
    $side = $topVal > 0 ? 'pos' : 'neg';
    $opts = $pool[$topKey][$side] ?? ["Profil singulier"];
    return $opts[array_rand($opts)];
}

$archetype     = generateArchetype($primary, $count);
$mutationRate  = $cardData['mutation_rate'];
$mutationColor = $mutationRate > 30 ? '#E8C07A' : ($mutationRate > 10 ? '#B8A7E7' : '#A7C7E7');
$mutationLabel = $mutationRate > 30 ? 'Mutation forte' : ($mutationRate > 10 ? 'Évolution modérée' : 'Profil stable');

$dominantVal = 0; $dominantLabel = '';
foreach ($criteriaData as $c) {
    if (abs($c['val']) > abs($dominantVal)) { $dominantVal = $c['val']; $dominantLabel = $c['label']; }
}
$dominantColor = $dominantVal > 0 ? '#B8A7E7' : '#E8C07A';

$username    = h($targetUser['username']);
$avatar      = h($targetUser['avatar'] ?? 'assets/default-avatar.png');
$seq         = strtoupper(substr(md5($targetId), 0, 8));
$periodLabel = h(get_period_label($period));
$moviesCount = (int)$cardData['movies_count'];

// SVG inline — on garde le SVG original intact, juste on fixe width/height
$svgRaw = file_get_contents(__DIR__ . '/assets/ADN_fond.svg');
$svgRaw = preg_replace('/<\?xml[^>]+\?>/', '', $svgRaw);
// Remplacer l'ouverture SVG pour forcer les dimensions
$svgRaw = preg_replace('/<svg\s[^>]*>/', 
    '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 60.444 191.407" preserveAspectRatio="xMidYMid meet" style="width:100%;height:100%;display:block;">',
    $svgRaw);
$svgRaw = trim($svgRaw);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Séquence ADN · <?= $username ?> · MOOVIE</title>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>

<p class="page-label">Séquence ADN · Récapitulatif <?= $periodLabel ?></p>
<?php if ($preview): ?><span class="preview-badge">⚡ Mode prévisualisation admin</span><?php endif; ?>

<div class="controls">
    <button class="format-btn active" id="btnSquare" onclick="setFormat('square')">Carré 1:1</button>
    <button class="format-btn" id="btnStory"  onclick="setFormat('story')">Story 9:16</button>
</div>

<div id="shareCard">
    <div class="card-glow"></div>

    <div class="card-layout">

        <!-- Header -->
        <div class="c-header">
            <div class="c-logo">MOOVIE<span>.</span></div>
            <div class="c-period"><?= $periodLabel ?></div>
        </div>

        <!-- Corps -->
        <div class="c-body">

            <!-- Infos -->
            <div class="c-left">
                <div class="c-user">
                    <img class="c-avatar" src="<?= $avatar ?>" crossorigin="anonymous" onerror="this.src='assets/default-avatar.png'">
                    <div>
                        <div class="c-username"><?= $username ?></div>
                        <div class="c-seen"><?= $moviesCount ?> film<?= $moviesCount > 1 ? 's' : '' ?> ce mois</div>
                    </div>
                </div>
                <div class="c-hsep"></div>
                <div class="c-archetype">"<?= h($archetype) ?>"</div>

                <?php if ($cardData['most_loved'] || $cardData['most_hated']): ?>
                <div class="c-films">
                    <?php if ($cardData['most_loved']): ?>
                    <div class="c-film">
                        <?php if ($cardData['most_loved']['poster']): ?>
                        <img src="<?= h($cardData['most_loved']['poster']) ?>" crossorigin="anonymous" alt="">
                        <?php endif; ?>
                        <span class="c-film-tag" style="color:#6ee7b7">❤ Aimé</span>
                        <span class="c-film-name"><?= h($cardData['most_loved']['title']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($cardData['most_hated']): ?>
                    <div class="c-film">
                        <?php if ($cardData['most_hated']['poster']): ?>
                        <img src="<?= h($cardData['most_hated']['poster']) ?>" crossorigin="anonymous" alt="">
                        <?php endif; ?>
                        <span class="c-film-tag" style="color:#f87171">✕ Détesté</span>
                        <span class="c-film-name"><?= h($cardData['most_hated']['title']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="c-stats">
                    <div class="c-stat">
                        <span class="c-stat-val" style="color:<?= $mutationColor ?>"><?= $mutationRate ?>%</span>
                        <span class="c-stat-lbl"><?= $mutationLabel ?></span>
                    </div>
                    <?php if ($dominantLabel): ?>
                    <div class="c-stat">
                        <span class="c-stat-val" style="color:<?= $dominantColor ?>"><?= h($dominantLabel) ?></span>
                        <span class="c-stat-lbl">Trait dominant</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hélice + annotations -->
            <div class="c-right" id="cRight">
                <div class="helix-zone" id="helixZone">

                    <!-- SVG inline -->
                    <div class="helix-svg-wrap" id="helixWrap">
                        <?= $svgRaw ?>
                    </div>

                    <!-- Annotations : positionnées en JS une fois l'hélice rendue -->
                    <?php foreach ($criteriaData as $c): ?>
                    <div class="annot annot--<?= $c['side'] ?>"
                         data-pct="<?= $c['pct_y'] ?>"
                         data-side="<?= $c['side'] ?>"
                         style="top:<?= $c['pct_y'] ?>%;">
                        <span class="annot-pole"><?= h($c['pole']) ?></span>
                        <span class="annot-pct" style="color:<?= $c['color'] ?>"><?= $c['pct'] ?>%</span>
                    </div>
                    <?php endforeach; ?>

                </div>
            </div>

        </div><!-- .c-body -->

        <!-- Footer -->
        <div class="c-footer">
            <div class="c-url">adnmovie.fr</div>
            <div class="c-seq"># <?= $seq ?></div>
        </div>

    </div><!-- .card-layout -->
</div><!-- #shareCard -->

<button class="btn-export" id="btnExport" onclick="exportCard()">Télécharger ma séquence</button>

<script>
// Positionner les annotations en absolu par rapport à l'hélice
function positionAnnotations() {
    const zone  = document.getElementById('helixZone');
    const wrap  = document.getElementById('helixWrap');
    if (!zone || !wrap) return;

    const zoneRect = zone.getBoundingClientRect();
    const wrapRect = wrap.getBoundingClientRect();

    // Centre X de l'hélice dans la zone
    const helixCenterX = wrapRect.left - zoneRect.left + wrapRect.width / 2;
    const helixHalfW   = wrapRect.width / 2;
    const gap = 8; // pixels entre bord hélice et texte

    document.querySelectorAll('.annot').forEach(el => {
        const side = el.dataset.side;
        if (side === 'left') {
            el.style.right = (zoneRect.width - helixCenterX + helixHalfW + gap) + 'px';
            el.style.left  = 'auto';
        } else {
            el.style.left  = (helixCenterX + helixHalfW + gap) + 'px';
            el.style.right = 'auto';
        }
    });
}

// Attendre que le SVG soit rendu
window.addEventListener('load', positionAnnotations);
window.addEventListener('resize', positionAnnotations);

// Format
let currentFormat = 'square';
function setFormat(fmt) {
    currentFormat = fmt;
    const card = document.getElementById('shareCard');
    if (fmt === 'story') {
        card.style.width  = '304px';
        card.style.height = '540px';
        card.classList.add('story');
    } else {
        card.style.width  = '540px';
        card.style.height = '540px';
        card.classList.remove('story');
    }
    document.getElementById('btnSquare').classList.toggle('active', fmt === 'square');
    document.getElementById('btnStory').classList.toggle('active', fmt === 'story');
    setTimeout(positionAnnotations, 50);
}

// Export
async function exportCard() {
    const btn = document.getElementById('btnExport');
    btn.disabled = true;
    btn.textContent = 'Génération...';
    try {
        const card  = document.getElementById('shareCard');
        const scale = currentFormat === 'square' ? 2.0 : 3.55;

        // Forcer toutes les CSS vars du SVG avant capture
        const svgEl = card.querySelector('svg');
        if (svgEl) {
            const style = window.getComputedStyle(svgEl);
            // Inline les couleurs des noeuds
            svgEl.querySelectorAll('[fill^="var("]').forEach(el => {
                const f = el.getAttribute('fill');
                const computed = getComputedStyle(svgEl).getPropertyValue(
                    f.replace('var(','').replace(')','').trim()
                ).trim();
                if (computed) el.setAttribute('fill', computed);
            });
        }

        const canvas = await html2canvas(card, {
            scale,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#07080f',
            logging: false,
            ignoreElements: el => false,
        });

        const a = document.createElement('a');
        a.download = 'moovie-adn-<?= $username ?>-' + currentFormat + '.png';
        a.href = canvas.toDataURL('image/png', 1.0);
        a.click();
    } catch(e) {
        console.error(e);
        alert('Erreur : ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Télécharger ma séquence';
    }
}
</script>
</body>
</html>