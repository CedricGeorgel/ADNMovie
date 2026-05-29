<?php
/**
 * CAST-CARD.PHP - Affiche un acteur (Photo, Nom, Rôle)
 */
function renderCastCard($actor) {
    // TMDB peut ne pas renvoyer de photo, on prévoit un fallback
    $photo = !empty($actor['profile_path']) 
        ? "https://image.tmdb.org/t/p/w185" . $actor['profile_path'] 
        : "assets/default-avatar.png";
    ?>
    <div class="cast-card">
        <div class="cast-photo">
            <img src="<?= h($photo) ?>" alt="<?= h($actor['name']) ?>" loading="lazy">
        </div>
        <div class="cast-info">
            <p class="cast-name"><?= h($actor['name']) ?></p>
            <p class="cast-character"><?= h($actor['character'] ?? '') ?></p>
        </div>
    </div>
    <?php
}