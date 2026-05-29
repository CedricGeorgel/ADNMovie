<?php
/**
 * ADN-CHART.PHP — V6
 *
 * Layout : [colonne Série] | [hélice] | [colonne Films]
 *
 * Ordre des calques hélice (bas → haut) :
 *   1. fond.png
 *   2. reflets film (mix-blend-mode: hue)
 *   3. reflets série (mix-blend-mode: hue)
 *   4. barres film
 *   5. barres série
 *   Exception Cohérence : ordre film/série inversé sur les barres ET les reflets.
 *
 * Score affiché : abs(avg_score) × 10 arrondi à 1 décimale, en "%"
 * "No data"     : critère absent de $stats_film / $stats_serie (rouge)
 *
 * @param array       $data_film    Scores film  — indexé [0..7] ou associatif
 * @param array       $data_serie   Scores série — même format
 * @param float       $polarization Indice de polarisation (non affiché, conservé pour API)
 * @param string      $id           Identifiant HTML unique
 * @param bool        $showBadge    (conservé pour API, non affiché)
 * @param int         $voteCount    (conservé pour API, non affiché)
 * @param array|null  $stats_film   Détecte les critères sans données film
 * @param array|null  $stats_serie  Détecte les critères sans données série
 */
function renderAdnChart(
    array  $data_film,
    array  $data_serie   = [],
    float  $polarization = 0,
    string $id           = 'adnChart',
    bool   $showBadge    = false,
    int    $voteCount    = 0,
    ?array $stats_film   = null,
    ?array $stats_serie  = null,
    bool   $showReflets  = true,
    string $wrapperStyle = ''
): void {

    $KEYS = ['complexite', 'previsibilite', 'intensite', 'malaise',
             'stylisation', 'dynamique', 'depaysement', 'coherence'];

    $LABELS = [
        'complexite'    => 'Complexité',
        'previsibilite' => 'Prévisibilité',
        'intensite'     => 'Intensité',
        'malaise'       => 'Malaise',
        'stylisation'   => 'Stylisation',
        'dynamique'     => 'Dynamique',
        'depaysement'   => 'Dépaysement',
        'coherence'     => 'Cohérence',
    ];

    // ── Normalisation ─────────────────────────────────────────────────────────
    $normalize = function (array $raw) use ($KEYS): array {
        $out = [];
        foreach ($KEYS as $i => $key) {
            $v = array_is_list($raw) ? ($raw[$i] ?? null) : ($raw[$key] ?? null);
            $out[$key] = ($v !== null && $v !== '') ? (float) $v : null;
        }
        return $out;
    };

    $film  = $normalize($data_film);
    $serie = $normalize($data_serie);

    // ── Variante PNG ──────────────────────────────────────────────────────────
    $getVariant = function (string $key, ?float $val, ?array $stats): string {
        if (!isset($stats[$key])) return 'null';
        if ($val !== null && $val < 0) return 'negatif';
        return 'positif';
    };

    // ── URLs assets ───────────────────────────────────────────────────────────
    $barreUrl = function (string $variant, string $key, string $type): string {
        return '/assets/ADN/Barres/' . "{$variant}_{$key}_{$type}.png";
    };
    $refletUrl = function (string $variant, string $key, string $type): string {
        return '/assets/ADN/Reflets/' . "{$variant}_{$type}_{$key}.png";
    };

    // ── Calcul des pourcentages relatifs ──────────────────────────────────────
    // % = abs(score) / somme(abs(scores ayant des données)) × 100
    // → chaque colonne totalise 100 %, les "No data" sont exclus du total.
    $computePcts = function (array $scores, ?array $stats) use ($KEYS): array {
        $total = 0.0;
        foreach ($KEYS as $key) {
            if (isset($stats[$key]) && $scores[$key] !== null) {
                $total += abs($scores[$key]);
            }
        }
        $pcts = [];
        foreach ($KEYS as $key) {
            if (!isset($stats[$key]) || $scores[$key] === null) {
                $pcts[$key] = null; // No data
            } else {
                $pcts[$key] = $total > 0 ? abs($scores[$key]) / $total * 100 : 0.0;
            }
        }
        return $pcts;
    };

    $filmPcts  = $computePcts($film,  $stats_film);
    $seriePcts = $computePcts($serie, $stats_serie);

    // ── Couleur du score : lavande = positif, ambre = négatif ────────────────
    $scoreColor = function (?float $val, bool $hasData): string {
        if (!$hasData)                        return '#FF6B6B'; // rouge = no data
        if ($val !== null && $val < 0)        return '#E8C07A'; // ambre = négatif
        return '#B8A7E7';                                       // lavande = positif
    };

    $fmtPct = function (?float $pct): string {
        if ($pct === null) return 'No data';
        return number_format($pct, 1) . ' %';
    };

    $safe_id       = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    $KEY_EXCEPTION = 'coherence';
    $KEYS_NORMAL   = array_values(array_filter($KEYS, fn($k) => $k !== $KEY_EXCEPTION));
    $imgStyle      = 'position:absolute;inset:0;width:100%;height:100%;display:block;';
    $imgStyleHue   = $imgStyle . 'mix-blend-mode:hue;';
    ?>

    <div class="adn-chart-wrapper" id="<?= $safe_id ?>"<?= $wrapperStyle ? ' style="' . htmlspecialchars($wrapperStyle, ENT_QUOTES) . '"' : '' ?>>
        <div class="adn-cols">

            <!-- ── Colonne Série (gauche) ─────────────────────────────── -->
            <div class="adn-col adn-col--left">
                <div class="adn-col-title">Série</div>
                <?php foreach ($KEYS as $key):
                    $hasData = isset($stats_serie[$key]);
                    $val     = $serie[$key] ?? null;
                    $color   = $scoreColor($val, $hasData);
                ?>
                <div class="adn-row">
                    <span class="adn-row-name"><?= htmlspecialchars($LABELS[$key], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="adn-row-score" style="color:<?= $color ?>">
                        <?= $fmtPct($seriePcts[$key]) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ── Hélice (centre) ───────────────────────────────────── -->
            <div class="adn-helix-col">
                <div class="adn-svg-container" id="adnSvgWrap_<?= $safe_id ?>">
                    <div style="position:relative;width:100%;aspect-ratio:492/1324;">

                        <!-- 1. Fond -->
                        <img src="/assets/fond.png" alt="" loading="lazy"
                             style="<?= $imgStyle ?>object-fit:cover;">

                        <!-- 2 & 3. Reflets (blend:hue) — désactivés si $showReflets = false -->
                        <?php if ($showReflets): ?>

                        <?php foreach ($KEYS_NORMAL as $key):
                            $v = $getVariant($key, $film[$key] ?? null, $stats_film); ?>
                        <img src="<?= $refletUrl($v, $key, 'film') ?>" alt="" loading="lazy"
                             onerror="this.style.display='none'" style="<?= $imgStyleHue ?>">
                        <?php endforeach; ?>

                        <?php foreach ($KEYS_NORMAL as $key):
                            $v = $getVariant($key, $serie[$key] ?? null, $stats_serie); ?>
                        <img src="<?= $refletUrl($v, $key, 'serie') ?>" alt="" loading="lazy"
                             onerror="this.style.display='none'" style="<?= $imgStyleHue ?>">
                        <?php endforeach; ?>

                        <?php $v = $getVariant($KEY_EXCEPTION, $serie[$KEY_EXCEPTION] ?? null, $stats_serie); ?>
                        <img src="<?= $refletUrl($v, $KEY_EXCEPTION, 'serie') ?>" alt="" loading="lazy"
                             onerror="this.style.display='none'" style="<?= $imgStyleHue ?>">
                        <?php $v = $getVariant($KEY_EXCEPTION, $film[$KEY_EXCEPTION] ?? null, $stats_film); ?>
                        <img src="<?= $refletUrl($v, $KEY_EXCEPTION, 'film') ?>" alt="" loading="lazy"
                             onerror="this.style.display='none'" style="<?= $imgStyleHue ?>">

                        <?php endif; ?>

                        <!-- 4. Barres film — critères normaux -->
                        <?php foreach ($KEYS_NORMAL as $key):
                            $v = $getVariant($key, $film[$key] ?? null, $stats_film); ?>
                        <img src="<?= $barreUrl($v, $key, 'film') ?>" alt="" loading="lazy"
                             onerror="this.style.display='none'" style="<?= $imgStyle ?>">
                        <?php endforeach; ?>

                        <!-- 5. Barres série — critères normaux -->
                        <?php foreach ($KEYS_NORMAL as $key):
                            $v = $getVariant($key, $serie[$key] ?? null, $stats_serie); ?>
                        <img src="<?= $barreUrl($v, $key, 'serie') ?>" alt="" loading="lazy"
                             onerror="this.style.display='none'" style="<?= $imgStyle ?>">
                        <?php endforeach; ?>

                        <!-- Cohérence barres : série → film (film par-dessus) -->
                        <?php $v = $getVariant($KEY_EXCEPTION, $serie[$KEY_EXCEPTION] ?? null, $stats_serie); ?>
                        <img src="<?= $barreUrl($v, $KEY_EXCEPTION, 'serie') ?>" alt="" loading="lazy"
                             onerror="this.style.display='none'" style="<?= $imgStyle ?>">
                        <?php $v = $getVariant($KEY_EXCEPTION, $film[$KEY_EXCEPTION] ?? null, $stats_film); ?>
                        <img src="<?= $barreUrl($v, $KEY_EXCEPTION, 'film') ?>" alt="" loading="lazy"
                             onerror="this.style.display='none'" style="<?= $imgStyle ?>">

                    </div>
                </div>
            </div>

            <!-- ── Colonne Films (droite) ─────────────────────────────── -->
            <div class="adn-col adn-col--right">
                <div class="adn-col-title">Films</div>
                <?php foreach ($KEYS as $key):
                    $hasData = isset($stats_film[$key]);
                    $val     = $film[$key] ?? null;
                    $color   = $scoreColor($val, $hasData);
                ?>
                <div class="adn-row">
                    <span class="adn-row-name"><?= htmlspecialchars($LABELS[$key], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="adn-row-score" style="color:<?= $color ?>">
                        <?= $fmtPct($filmPcts[$key]) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
    <?php
}
