<?php
/**
 * RADAR-CHART.PHP
 * Supporte deux modes : 'radar' (spider recentré) et 'sliders' (curseurs bipolaires)
 * Plage : -10 à +10, neutre à 0
 */
function renderRadarChart($data_commu, $data_user = null, $polarization = 0, $id = 'movieChart', $showBadge = false, $voteCount = 0) {
    // V2 : 8 critères
    $criteria     = ['Complexité', 'Prévisibilité', 'Intensité', 'Malaise', 'Stylisation', 'Dynamique', 'Dépaysement', 'Cohérence'];
    $labels_left  = ['Récit évident', 'Imprévisible', 'Détaché', 'Réconfortant', 'Brut / naturaliste', 'Lent & dispersé', 'Aucun ailleurs', 'Totalement irréel'];
    $labels_right = ['Récit alambiqué', 'Prévisible', 'Très chargé', 'Profondément dérangeant', 'Très stylisé', 'Intense & prenant', 'Envie de partir', 'Totalement crédible'];

    $labels_json = json_encode($criteria);
    // ... le reste de la fonction reste inchangé
    $commu_json  = json_encode(array_values($data_commu));
    $user_json   = $data_user ? json_encode(array_values($data_user)) : 'null';

    $has_data = !empty(array_filter($data_commu, fn($v) => $v != 0));
    ?>
    <div class="radar-wrapper" style="position: relative; z-index: 1;">

        <div class="radar-badges">
            <?php if ($has_data && $showBadge && (int)$voteCount >= 10): ?>
                <span class="radar-badge radar-badge--polar <?= $polarization > 1.5 ? 'radar-badge--polar-high' : '' ?>">
                    <?= $polarization > 1.5 ? '⚡ Polarisant' : '🤝 Consensus' ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="chart-view-toggle" id="toggle-<?= h($id) ?>">
            <button class="chart-tab active" onclick="switchChartView('<?= h($id) ?>', 'radar', this)" type="button">
                Radar
            </button>
            <button class="chart-tab" onclick="switchChartView('<?= h($id) ?>', 'sliders', this)" type="button">
                Curseurs
            </button>
        </div>

        <div class="chart-view chart-view--radar" id="view-radar-<?= h($id) ?>">
            <canvas id="<?= h($id) ?>"
                    class="radar-canvas"
                    data-labels='<?= $labels_json ?>'
                    data-commu='<?= $commu_json ?>'
                    data-user='<?= $user_json ?>'>
            </canvas>
            <button onclick="exportRadarToJPG('<?= h($id) ?>')" class="btn-export-chart" title="Exporter en image">
                📸
            </button>
        </div>

        <div class="chart-view chart-view--sliders" id="view-sliders-<?= h($id) ?>" style="display: none;">
            <div class="bipolar-sliders">
                <?php foreach ($criteria as $i => $label):
                    $commu_raw = $data_commu[$i] ?? null;
                    $commu_val = $commu_raw !== null ? (float)$commu_raw : null;
                    $user_raw  = $data_user ? ($data_user[$i] ?? null) : null;
                    $user_val  = $user_raw !== null ? (float)$user_raw : null;

                    // Plage -10/+10 → pourcentage pour left: X%. 50 si null.
                    $commu_pct = $commu_val !== null ? (($commu_val + 10) / 20) * 100 : 50;
                    $user_pct  = $user_val !== null ? (($user_val + 10) / 20) * 100 : null;
                ?>
                <div class="bipolar-row" title="<?= h($label) ?>">
                    <div class="bipolar-labels">
                        <span class="bipolar-label-left"><?= h($labels_left[$i]) ?></span>
                        <span class="bipolar-label-key"><?= h($label) ?></span>
                        <span class="bipolar-label-right"><?= h($labels_right[$i]) ?></span>
                    </div>
                    <div class="bipolar-track-wrapper">
                        <div class="bipolar-track">
                            <div class="bipolar-center-tick"></div>
                            <div class="bipolar-dot bipolar-dot--commu"
                                 style="left: <?= round($commu_pct, 2) ?>%;"
                                 data-val="<?= $commu_val === null ? 'null' : $commu_val ?>"
                                 title="Communauté : <?= $commu_val === null ? 'Non évalué' : ($commu_val >= 0 ? '+' : '') . $commu_val ?>"></div>
                            <?php if ($user_pct !== null): ?>
                            <div class="bipolar-dot bipolar-dot--user"
                                 style="left: <?= round($user_pct, 2) ?>%;"
                                 title="Mon analyse : <?= ($user_val >= 0 ? '+' : '') . $user_val ?>"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="bipolar-legend">
                <span class="bipolar-legend-item">
                    <span class="bipolar-legend-dot bipolar-legend-dot--commu"></span>
                    Moyenne Moovie
                </span>
                <?php if ($data_user): ?>
                <span class="bipolar-legend-item">
                    <span class="bipolar-legend-dot bipolar-legend-dot--user"></span>
                    Mon analyse
                </span>
                <?php endif; ?>
                <span class="bipolar-legend-item bipolar-legend-item--neutral">
                    <span class="bipolar-legend-tick"></span>
                    Neutre
                </span>
            </div>
        </div>

    </div>
    <?php
}