<?php
/**
 * MOVIE-ROW.PHP - Visuel allongé pour les résultats de recherche
 * @param array $movie Données du film
 */
function renderMovieRow($movie) {
    $id = $movie['id'] ?? $movie['tmdb_id'] ?? null;
    $isTmdb = isset($movie['source']) && $movie['source'] === 'tmdb';
    ?>
    <a href="fiche.php?id=<?= h($id) ?>" class="movie-row-item">
        <div class="row-poster">
            <img src="<?= h($movie['poster']) ?>" alt="<?= h($movie['title']) ?>" loading="lazy">
        </div>
        <div class="row-content">
            <div class="row-header">
                <h4 class="row-title"><?= h($movie['title']) ?></h4>
                <span class="row-year"><?= h($movie['year']) ?></span>
            </div>
            <div class="row-meta">
                <?php if ($isTmdb): ?>
                    <span class="badge-source">TMDB</span>
                <?php else: ?>
                    <span class="badge-source local">BDD</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="row-arrow">
            <svg viewBox="0 0 24 24" width="20" height="20"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z" fill="currentColor"/></svg>
        </div>
    </a>
    <?php
}