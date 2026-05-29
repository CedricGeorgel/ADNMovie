<?php
/**
 * DNA-BARS.PHP — Rendu des deux barres ADN côte à côte
 * Partagé entre communaute.php et adn.php
 */

// ── Sélection déterministe de barres (sans polluer srand global) ───────────
function _dnaBarsPickIndices(int $seed, int $count, array $exclude): array {
    $available = array_values(array_diff(range(0, 37), $exclude));
    $result = [];
    for ($i = 0; $i < $count && !empty($available); $i++) {
        $idx  = abs(crc32($seed . '_' . $i)) % count($available);
        $result[] = $available[$idx];
        array_splice($available, $idx, 1);
    }
    return $result;
}

// ── Rendu des deux barres ADN (Votre ADN / ADN Génome Inconnu) ────────────
// 80–89.99% → 2 barres rouges · 90–99.99% → 1 · 100% → 0
// Barres bleues identiques dans les deux affichages · Rouges différentes
function renderDnaBarsPair(string $myUserId, string $theirUserId, float $matchPct,
                           string $labelMe = 'Votre ADN', string $labelThem = 'ADN Génome Inconnu',
                           bool $sideBySide = false): string {
    $xs = [
        667.751, 649.085, 630.418, 611.752, 593.085, 574.418, 555.752, 537.085,
        518.418, 499.752, 481.085, 462.419, 443.752, 425.085, 406.419, 387.752,
        369.085, 350.419, 331.752, 313.085, 294.419, 275.752, 257.086, 238.419,
        219.752, 201.086, 182.419, 163.752, 145.086, 126.419, 107.753,  89.086,
         70.419,  51.753,  33.086,  14.419,  -4.247, -22.914,
    ];

    $seed = abs(crc32(min($myUserId, $theirUserId) . '|' . max($myUserId, $theirUserId)));

    $redCount   = $matchPct >= 100 ? 0 : (int) ceil((100 - $matchPct) / 10);
    $blueCount  = 10 - $redCount;

    $blueIndices = _dnaBarsPickIndices($seed, $blueCount, []);
    $blueSet     = array_flip($blueIndices);

    $mineRedSet  = $redCount ? array_flip(_dnaBarsPickIndices($seed + 1, $redCount, $blueIndices)) : [];
    $theirRedSet = $redCount ? array_flip(_dnaBarsPickIndices($seed + 2, $redCount, $blueIndices)) : [];

    $filterId = 'glow-' . $seed;
    $defs = '<defs><filter id="' . $filterId . '" x="-30%" y="-80%" width="160%" height="260%">'
          . '<feGaussianBlur stdDeviation="2.8" result="blur"/>'
          . '<feColorMatrix in="blur" type="matrix" values="1 0 0 0 0.1  0 1 0 0 0.1  0 0 1 0 0.1  0 0 0 4 0" result="brightBlur"/>'
          . '<feMerge><feMergeNode in="brightBlur"/><feMergeNode in="SourceGraphic"/></feMerge>'
          . '</filter></defs>';

    $fond = '<rect x="322.412" y="-322.412" width="74" height="718.825"'
          . ' transform="translate(322.412 396.412) rotate(-90)" fill="#a7c7e7" opacity=".1"/>';
    $svgMine = $svgTheir = '';

    foreach ($xs as $i => $x) {
        $y2  = round($x + 74, 3);
        $trf = "translate($x $y2) rotate(-90)";
        $bar = "<rect x=\"$x\" y=\"32.333\" width=\"74\" height=\"9.333\" transform=\"$trf\" filter=\"url(#$filterId)\"";

        if (isset($blueSet[$i])) {
            $op = round(0.70 + (abs(crc32($seed . '_op_' . $i)) % 31) / 100, 2);
            $svgMine  .= $bar . " fill=\"#a7c7e7\" opacity=\"$op\"/>";
            $svgTheir .= $bar . " fill=\"#a7c7e7\" opacity=\"$op\"/>";
        } else {
            if (isset($mineRedSet[$i]))  $svgMine  .= $bar . ' fill="#FA6B6B"/>';
            if (isset($theirRedSet[$i])) $svgTheir .= $bar . ' fill="#FA6B6B"/>';
        }
    }

    $mkSvg = fn(string $bars) =>
        '<svg viewBox="0 0 718.825 74" width="100%" style="display:block;">' . $defs . $fond . $bars . '</svg>';

    $lbl = 'font-size:0.5rem;color:var(--text-dim);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;';

    if ($sideBySide) {
        $barGroup = fn(string $label, string $svg) =>
            '<div style="flex:1;min-width:0;">'
            . "<div style=\"$lbl\">$label</div>"
            . $svg
            . '</div>';
        return '<div style="display:flex;gap:12px;align-items:flex-end;">'
             . $barGroup($labelMe,   $mkSvg($svgMine))
             . $barGroup($labelThem, $mkSvg($svgTheir))
             . '</div>';
    }

    return '<div style="margin-bottom:14px;">'
         . "<div style=\"$lbl\">$labelMe</div>"    . $mkSvg($svgMine)
         . "<div style=\"{$lbl}margin-top:8px;\">$labelThem</div>" . $mkSvg($svgTheir)
         . '</div>';
}
