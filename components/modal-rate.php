<?php
function renderRateModal($movieId, $existingScores = null, $isLiked = false, $existingLikeScore = null, $contentType = 'movie', $seasonNumber = 0) {
    $isSeriesFull = ($contentType === 'tv_full');
    $isEpisode     = ($contentType === 'episode');
    if ($isSeriesFull || $isEpisode) $contentType = 'tv'; // normalise pour la suite
    $isSeries = ($contentType === 'tv' || $isSeriesFull || $isEpisode);
    if (!function_exists('db_fetch_all')) {
        require_once __DIR__ . '/../functions/core_db.php';
    }

    $criteria = [
        'complexite'    => ['label' => 'Complexité',    'left' => 'Récit évident',      'right' => 'Récit alambiqué',           'desc' => 'Évalue l\'effort de compréhension. Narration limpide ou puzzle mental exigeant ?'],
        'previsibilite' => ['label' => 'Prévisibilité', 'left' => 'Imprévisible',       'right' => 'Prévisible',                'desc' => 'Mesure de l\'anticipation. Surprise totale ou récit cousu de fil blanc ?'],
        'intensite'     => ['label' => 'Intensité',     'left' => 'Détaché',            'right' => 'Très chargé',               'desc' => 'Connexion émotionnelle. Mécanique des faits ou empathie profonde ?'],
        'malaise'       => ['label' => 'Malaise',       'left' => 'Réconfortant',       'right' => 'Profondément dérangeant',   'desc' => 'Tension et inconfort viscéral. Cocon rassurant ou expérience abrasive ?'],
        'stylisation'   => ['label' => 'Stylisation',   'left' => 'Brut / naturaliste', 'right' => 'Très stylisé',              'desc' => 'Degré de recherche visuelle. Capture brute du réel ou composition plastique ?'],
        'dynamique'     => ['label' => 'Dynamique',     'left' => 'Lent & dispersé',    'right' => 'Intense & prenant',         'desc' => 'Pulsation de l\'œuvre. Contemplation lente ou cadence effrénée ?'],
        'depaysement'   => ['label' => 'Dépaysement',   'left' => 'Aucun ailleurs',     'right' => 'Envie de partir',           'desc' => 'Capacité à stimuler votre soif d\'évasion. Confinement ou invitation au voyage ?'],
        'coherence'     => ['label' => 'Cohérence',     'left' => 'Totalement irréel',  'right' => 'Totalement crédible',       'desc' => 'Mesure votre sentiment d\'immersion. Le récit s\'appuie-t-il sur la logique du monde réel ?'],
    ];

    $likeVal = null;
    if ($existingLikeScore !== null && $existingLikeScore !== '') {
        $likeVal = (int)$existingLikeScore;
    } elseif ($isLiked === true) {
        $likeVal = 5;
    } elseif ($isLiked === false && $existingScores !== null) {
        $likeVal = 0; 
    }
    
    $likeDisplayVal = $likeVal !== null ? $likeVal : 0;

    $activeCriteria = [];
    if ($existingScores) {
        foreach ($criteria as $key => $_) {
            if (isset($existingScores[$key]) && $existingScores[$key] !== null && $existingScores[$key] !== '') {
                $activeCriteria[] = $key;
            }
        }
    }
?>
<div id="rateModal" class="modal">
    <div class="modal-content modal-content--rate" style="max-width:480px;padding:0;overflow-y:auto;overflow-x:hidden;max-height:90vh;">

        <form id="rateForm" onsubmit="return false;">
            <input type="hidden" name="movie_id" value="<?= h($movieId) ?>">
            <input type="hidden" name="content_type" value="<?= $isSeries ? 'tv' : 'movie' ?>">
            <input type="hidden" name="content_kind" value="<?= $isSeriesFull ? 'tv_full' : ($isEpisode ? 'episode' : ($isSeries ? 'tv' : 'movie')) ?>">
            <?php if ($isSeries): ?>
            <input type="hidden" name="series_id"     value="<?= (int)$movieId ?>">
            <input type="hidden" name="season_number" value="<?= (int)$seasonNumber ?>">
            <?php endif; ?>
            <input type="hidden" name="action" value="rate">
            <input type="hidden" name="like_active" value="1">

            <div id="switch-like" style="display:none;" class="slider-switch slider-switch--on"></div>

            <div id="rateStep-1" style="display:block;">
                <div style="padding:24px 24px 0;">
                    <button class="modal-close" onclick="closeRateModal()" type="button">&times;</button>
                    <h3 class="modal-title" style="margin-bottom:4px;">
                        <?php if ($isSeriesFull): ?>
                            ANALYSE DE LA SÉRIE COMPLÈTE (1/2)
                        <?php elseif ($isEpisode): ?>
                            ANALYSE DE L'ÉPISODE (1/2)
                        <?php elseif ($isSeries && $seasonNumber > 0): ?>
                            ANALYSE — SAISON <?= (int)$seasonNumber ?> (1/2)
                        <?php elseif ($isSeries): ?>
                            ANALYSE DE LA SÉRIE (1/2)
                        <?php else: ?>
                            ANALYSE DU FILM (1/2)
                        <?php endif; ?>
                    </h3>
                    <p style="font-size:0.65rem;color:var(--text-dim);margin-bottom:20px;">
                        Sélectionnez jusqu'à <strong>3 axes</strong> qui définissent <?= $isSeriesFull ? 'cette série dans son ensemble' : ($isEpisode ? 'cet épisode' : (($isSeries && $seasonNumber > 0) ? 'cette saison' : ($isSeries ? 'cette série' : 'ce film'))) ?>. Seuls vos convictions comptent. Vous pouvez passer cette étape.
                    </p>
                </div>

                <div style="padding:0 24px 24px;">
                    <div style="margin-bottom:16px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <span style="font-size:0.7rem;font-weight:800;letter-spacing:1px;text-transform:uppercase;">
                                <?php if ($isSeriesFull): ?>ADN de la série complète
                                <?php elseif ($isEpisode): ?>ADN de l'épisode
                                <?php elseif ($isSeries && $seasonNumber > 0): ?>ADN de la saison
                                <?php elseif ($isSeries): ?>ADN de la série
                                <?php else: ?>ADN du film<?php endif; ?>
                            </span>
                            <span id="criteriaCounter" style="font-size:0.65rem;font-family:monospace;padding:2px 10px;border-radius:20px;background:rgba(167,199,231,0.1);border:1px solid rgba(167,199,231,0.3);color:var(--pastel-blue);">
                                <span id="criteriaCount"><?= count($activeCriteria) ?></span>/3
                            </span>
                        </div>

                        <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:16px;">
                            <?php foreach ($criteria as $key => $c):
                                $isActive = in_array($key, $activeCriteria);
                            ?>
                            <button type="button"
                                    class="criteria-chip <?= $isActive ? 'criteria-chip--active' : '' ?>"
                                    id="chip-<?= $key ?>"
                                    onclick="window.toggleCriteriaChip('<?= $key ?>')"
                                    data-key="<?= $key ?>"
                                    title="<?= h($c['desc']) ?>">
                                <?= h($c['label']) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>

                        <div id="activeSlidersContainer">
                            <?php foreach ($criteria as $key => $c):
                                $val      = isset($existingScores[$key]) ? $existingScores[$key] : null;
                                $hasScore = ($val !== null && $val !== '');
                                $displayVal = $hasScore ? (float)$val : 0;
                            ?>
                            <div class="slider-group <?= $hasScore ? 'active' : 'inactive' ?>"
                                 id="group-<?= $key ?>"
                                 style="<?= $hasScore ? '' : 'display:none;' ?>margin-bottom:14px;padding:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;">

                                <input type="checkbox" name="active_scores[<?= $key ?>]"
                                       id="check-<?= $key ?>" <?= $hasScore ? 'checked' : '' ?> style="display:none;">

                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                                    <span style="font-size:0.72rem;font-weight:800;color:var(--text);"><?= h($c['label']) ?></span>
                                    <span id="score-<?= $key ?>" style="font-size:0.85rem;font-weight:900;font-family:monospace;color:var(--pastel-blue);">
                                        <?= $hasScore ? ($displayVal > 0 ? '+'.$displayVal : $displayVal) : '0' ?>
                                    </span>
                                </div>
                                
                                <div style="font-size:0.6rem;color:var(--text-dim);margin-bottom:12px;line-height:1.4;">
                                    <?= h($c['desc']) ?>
                                </div>

                                <div class="slider-track-container"
                                     onmousedown="activateSliderKey('<?= $key ?>', event)"
                                     ontouchstart="activateSliderKey('<?= $key ?>', event)">
                                    <input type="range"
                                           name="scores[<?= $key ?>]"
                                           id="slider-<?= $key ?>"
                                           min="-10" max="10" step="0.5"
                                           value="<?= $displayVal ?>"
                                           class="input-slider <?= $hasScore ? '' : 'slider-untouched' ?>"
                                           oninput="onCriteriaSliderInput('<?= $key ?>', this.value)"
                                           <?= $hasScore ? '' : 'disabled' ?>>
                                    <div class="slider-center-tick"></div>
                                </div>
                                <div class="slider-sublabels">
                                    <span class="slider-sublabel slider-sublabel--left"><?= h($c['left']) ?></span>
                                    <span class="slider-sublabel slider-sublabel--right"><?= h($c['right']) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-base active" onclick="window.goToStep2()" style="width:100%;">
                            Suivant
                        </button>
                    </div>
                </div>
            </div>

            <div id="rateStep-2" style="display:none;">
                <div style="padding:24px 24px 0;">
                    <button class="modal-close" onclick="closeRateModal()" type="button">&times;</button>
                    <h3 class="modal-title" style="margin-bottom:4px;">RESSENTI GLOBAL (2/2)</h3>
                    <p style="font-size:0.65rem;color:var(--text-dim);margin-bottom:20px;">
                        Votre jugement final. Positionnez le curseur selon votre appréciation globale de l'œuvre.
                    </p>
                </div>

                <div style="padding:0 24px 24px;">
                    <div class="rate-section" style="margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid var(--border);">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                            <span style="font-size:0.7rem;font-weight:800;letter-spacing:1px;text-transform:uppercase;">Appréciation</span>
                            <span id="likeValueDisplay" style="font-size:0.85rem;font-weight:900;font-family:monospace;color:var(--pastel-blue);">
                                <?= $likeDisplayVal > 0 ? '+'.$likeDisplayVal : $likeDisplayVal ?>
                            </span>
                        </div>
                        <div class="slider-inline-row">
                            <div class="slider-track-container">
                                <input type="range" id="likeSlider" name="like_score"
                                       min="-10" max="10" step="1"
                                       value="<?= $likeDisplayVal ?>"
                                       class="input-slider"
                                       oninput="window.onLikeSliderInput(this)">
                                <div class="slider-center-tick"></div>
                            </div>
                        </div>
                        <div class="slider-sublabels" style="padding-left:0;">
                            <span class="slider-sublabel slider-sublabel--left">Je déteste</span>
                            <span class="slider-sublabel slider-sublabel--right">J'adore</span>
                        </div>
                    </div>

                    <div class="modal-actions" style="display:flex; gap:10px;">
                        <button type="button" class="btn-base" onclick="window.goToStep1()" style="flex:1;">
                            Retour
                        </button>
                        <button type="button" class="btn-base active" onclick="submitRateForm()" style="flex:2;">
                            Enregistrer mon analyse
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div id="rateView-mutation" style="display:none;padding:28px 24px 24px;text-align:center;">
            <p style="font-size:0.55rem;font-weight:800;letter-spacing:3px;text-transform:uppercase;color:var(--text-dim);margin-bottom:4px;">Séquence ADN</p>
            <p style="font-size:0.65rem;color:var(--text-dim);margin-bottom:20px;">Votre profil vient de muter</p>

            <div id="radarContainer" style="position:relative;height:220px;margin-bottom:16px;">
                <canvas id="dnaMutationChart" style="width:100%;height:100%;"></canvas>
            </div>

            <div id="dnaDeltaList" style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;min-height:28px;margin-bottom:20px;"></div>

            <button type="button" class="btn-base active" onclick="window.closeMutationViewAndReload()" style="width:100%;">
                Fermer
            </button>
        </div>

    </div>
</div>

<script>
window.__currentUserDna = <?php
    $__dnaMap = [];
    if (isset($_SESSION['user_id'])) {
        $__table = $isSeries ? 'user_series_dna' : 'user_dna';
        $__rows  = db_fetch_all(
            "SELECT criterio, avg_score FROM {$__table} WHERE user_id = ?",
            [$_SESSION['user_id']]
        );
        foreach ($__rows as $__r) {
            $__dnaMap[$__r['criterio']] = (float)$__r['avg_score'];
        }
    }
    echo json_encode(empty($__dnaMap) ? new stdClass() : $__dnaMap);
?>;
</script>
<?php
}
?>