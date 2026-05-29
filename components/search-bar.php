<?php
/**
 * SEARCH-BAR.PHP
 */
function renderSearchBar($placeholder = 'Rechercher un film (BDD ou TMDB)...') {
    ?>
    <div class="search-container">
        <input type="text"
               id="movieSearchInput"
               placeholder="<?= h($placeholder) ?>"
               autocomplete="off">
        <div id="searchLoader" class="loader" style="display:none;"></div>
    </div>
    <?php
}