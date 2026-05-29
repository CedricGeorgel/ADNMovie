<?php
/**
 * MATCH-BADGE.PHP - Affiche le pourcentage de match ADN
 */
function renderMatchBadge($label) {
    if (!$label) return;
    ?>
    <div class="match-badge">
        <?= h($label) ?>
    </div>
    <?php
}