<?php
/**
 * ADN-RADAR.PHP
 * Radar chart (spider) 8 axes avec bande de dispersion min/max.
 *
 * @param array      $data_avg    Scores moyens — indexé [0..7] ou associatif
 * @param array|null $data_min    Scores minimum — même format (optionnel)
 * @param array|null $data_max    Scores maximum — même format (optionnel)
 * @param string     $id          Identifiant HTML unique
 * @param int        $voteCount   Nombre de votes (badge)
 */
function renderAdnRadar(
    array  $data_avg,
    ?array $data_min  = null,
    ?array $data_max  = null,
    string $id        = 'adnRadar',
    int    $voteCount = 0
): void {

    $KEYS = ['complexite', 'previsibilite', 'intensite', 'malaise',
             'stylisation', 'dynamique', 'depaysement', 'coherence'];

    $LABELS = [
        'complexite'    => 'Complexité',
        'previsibilite' => 'Prévis.',
        'intensite'     => 'Intensité',
        'malaise'       => 'Malaise',
        'stylisation'   => 'Stylisation',
        'dynamique'     => 'Dynamique',
        'depaysement'   => 'Dépaysement',
        'coherence'     => 'Cohérence',
    ];

    $n  = count($KEYS); // 8
    $cx = 110; $cy = 110;
    $R  = 75; // rayon max (valeur 10)

    // Valeur [-10..+10] → rayon en px
    $toR = fn(float $v): float => ($v + 10) / 20 * $R;

    // Coordonnées d'un point pour l'axe $i et la valeur $v
    $pt = function (int $i, float $v) use ($n, $cx, $cy, $toR): array {
        $angle = (2 * M_PI * $i / $n) - M_PI / 2; // part du haut, sens horaire
        $r     = $toR($v);
        return ['x' => $cx + $r * cos($angle), 'y' => $cy + $r * sin($angle)];
    };

    // Extrémité de l'axe (valeur = +10)
    $axisEnd = function (int $i) use ($n, $cx, $cy, $R): array {
        $angle = (2 * M_PI * $i / $n) - M_PI / 2;
        return ['x' => $cx + $R * cos($angle), 'y' => $cy + $R * sin($angle)];
    };

    // Normalise un tableau indexé ou associatif
    $normalize = function (array $raw) use ($KEYS): array {
        $out = [];
        foreach ($KEYS as $i => $key) {
            $v = array_is_list($raw) ? ($raw[$i] ?? 0) : ($raw[$key] ?? 0);
            $out[$key] = (float) $v;
        }
        return $out;
    };

    $avg = $normalize($data_avg);
    $min = $data_min ? $normalize($data_min) : null;
    $max = $data_max ? $normalize($data_max) : null;

    // Série de points SVG pour un tableau de valeurs
    $polyPts = function (array $vals) use ($KEYS, $pt): string {
        $pts = [];
        foreach ($KEYS as $i => $key) {
            $p    = $pt($i, $vals[$key]);
            $pts[] = round($p['x'], 2) . ',' . round($p['y'], 2);
        }
        return implode(' ', $pts);
    };

    // Couleur principale selon la polarité dominante
    $totalPos = array_sum(array_filter($avg, fn($v) => $v > 0));
    $totalNeg = array_sum(array_map('abs', array_filter($avg, fn($v) => $v < 0)));
    $mainColor = $totalPos >= $totalNeg ? '#B8A7E7' : '#E8C07A'; // lavande ou ambre

    $safe_id  = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    $mask_id  = 'bandMask_' . $safe_id;
    $hasBand  = $min && $max;

    // Grille : cercles à -10, -5, 0, +5, +10
    $gridLevels = [-10, -5, 0, 5, 10];
    ?>

    <div class="adn-radar-wrapper" id="<?= $safe_id ?>">

        <?php if ($voteCount > 0): ?>
        <div class="radar-badges" style="margin-bottom:8px;">
            <span class="radar-badge radar-badge--votes">
                <?= $voteCount ?> analyse<?= $voteCount > 1 ? 's' : '' ?>
            </span>
            <?php if ($hasBand): ?>
            <span class="radar-badge" style="background:rgba(255,255,255,0.04);color:var(--text-dim);border:1px solid var(--border);">
                Dispersion min/max
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <svg viewBox="0 0 220 220" width="100%" style="max-width:280px;overflow:visible;display:block;margin:0 auto;">
            <defs>
                <?php if ($hasBand): ?>
                <!-- Masque qui "découpe" l'intérieur du min pour ne garder que la bande -->
                <mask id="<?= $mask_id ?>">
                    <rect width="220" height="220" fill="white"/>
                    <polygon points="<?= $polyPts($min) ?>" fill="black"/>
                </mask>
                <?php endif; ?>
            </defs>

            <!-- ── Grille ── -->
            <?php foreach ($gridLevels as $gv):
                $gr  = ($gv + 10) / 20 * $R;
                $isZ = ($gv === 0);
            ?>
            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= round($gr, 2) ?>"
                    fill="none"
                    stroke="<?= $isZ ? 'rgba(255,255,255,0.18)' : 'rgba(255,255,255,0.06)' ?>"
                    stroke-width="<?= $isZ ? '1' : '0.75' ?>"
                    <?= $isZ ? 'stroke-dasharray="4,3"' : '' ?>/>
            <?php endforeach; ?>

            <!-- Labels grille (+5 / -5) -->
            <?php
            $lGrid = [
                [5,  '+5'],
                [-5, '-5'],
            ];
            foreach ($lGrid as [$gv, $lbl]):
                $gy = $cy - (($gv + 10) / 20 * $R);
            ?>
            <text x="<?= $cx + 3 ?>" y="<?= round($gy + 3, 2) ?>"
                  fill="rgba(255,255,255,0.18)" font-size="6" text-anchor="start"
                  font-family="inherit"><?= $lbl ?></text>
            <?php endforeach; ?>

            <!-- ── Axes ── -->
            <?php for ($i = 0; $i < $n; $i++):
                $end = $axisEnd($i); ?>
            <line x1="<?= $cx ?>" y1="<?= $cy ?>"
                  x2="<?= round($end['x'], 2) ?>" y2="<?= round($end['y'], 2) ?>"
                  stroke="rgba(255,255,255,0.1)" stroke-width="1"/>
            <?php endfor; ?>

            <!-- ── Bande min/max (masquée) ── -->
            <?php if ($hasBand): ?>
            <polygon points="<?= $polyPts($max) ?>"
                     fill="<?= $mainColor ?>" fill-opacity="0.18"
                     stroke="<?= $mainColor ?>" stroke-width="0.75" stroke-opacity="0.3"
                     stroke-linejoin="round"
                     mask="url(#<?= $mask_id ?>)"/>
            <!-- Contour min (référence basse) -->
            <polygon points="<?= $polyPts($min) ?>"
                     fill="none"
                     stroke="<?= $mainColor ?>" stroke-width="0.75" stroke-opacity="0.3"
                     stroke-dasharray="3,2" stroke-linejoin="round"/>
            <?php endif; ?>

            <!-- ── Polygone moyen (principal) ── -->
            <polygon points="<?= $polyPts($avg) ?>"
                     fill="<?= $mainColor ?>" fill-opacity="0.22"
                     stroke="<?= $mainColor ?>" stroke-width="1.8"
                     stroke-linejoin="round"/>

            <!-- ── Points sur la moyenne ── -->
            <?php foreach ($KEYS as $i => $key):
                $p = $pt($i, $avg[$key]); ?>
            <circle cx="<?= round($p['x'], 2) ?>" cy="<?= round($p['y'], 2) ?>" r="3"
                    fill="<?= $mainColor ?>" stroke="#050505" stroke-width="1.5"/>
            <?php endforeach; ?>

            <!-- ── Labels des axes ── -->
            <?php foreach ($KEYS as $i => $key):
                $angle  = (2 * M_PI * $i / $n) - M_PI / 2;
                $labelR = $R + 20;
                $lx     = $cx + $labelR * cos($angle);
                $ly     = $cy + $labelR * sin($angle);

                // Alignement selon position
                if ($lx < $cx - 8)     $anchor = 'end';
                elseif ($lx > $cx + 8) $anchor = 'start';
                else                   $anchor = 'middle';

                $valFmt = ($avg[$key] > 0 ? '+' : '') . round($avg[$key], 1);

                // Écart min/max si disponible
                $spread = '';
                if ($hasBand) {
                    $delta = round($max[$key] - $min[$key], 1);
                    if ($delta > 0) $spread = '±' . round($delta / 2, 1);
                }
            ?>
            <text x="<?= round($lx, 2) ?>" y="<?= round($ly - 4, 2) ?>"
                  text-anchor="<?= $anchor ?>" dominant-baseline="middle"
                  fill="rgba(255,255,255,0.6)" font-size="7.5" font-family="inherit">
                <?= htmlspecialchars($LABELS[$key], ENT_QUOTES, 'UTF-8') ?>
            </text>
            <text x="<?= round($lx, 2) ?>" y="<?= round($ly + 5, 2) ?>"
                  text-anchor="<?= $anchor ?>" dominant-baseline="middle"
                  font-size="7" font-family="inherit">
                <tspan fill="<?= $mainColor ?>" font-weight="700"><?= $valFmt ?></tspan>
                <?php if ($spread): ?>
                <tspan fill="rgba(255,255,255,0.3)" dx="2"><?= $spread ?></tspan>
                <?php endif; ?>
            </text>
            <?php endforeach; ?>

        </svg>
    </div>
    <?php
}
