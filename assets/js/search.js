/**
 * ASSETS/JS/SEARCH.JS - Templates de résultats
 */

function createMovieRowHTML(movie) {
    const isSeries = movie.content_type === 'tv';
    const url = isSeries
        ? `fiche.php?id=${movie.id}&type=tv`
        : `fiche.php?id=${movie.id}`;
    const badge = isSeries
        ? `<span style="font-size:0.65rem;color:#93c5fd;background:rgba(147,197,253,0.12);border:1px solid rgba(147,197,253,0.25);border-radius:4px;padding:1px 5px;margin-left:6px;vertical-align:middle;">SÉRIE</span>`
        : '';
    return `
    <a href="${url}" class="movie-row-item">
        <div class="row-poster"><img src="${movie.poster}"></div>
        <div class="row-content">
            <div class="row-header">
                <h4 class="row-title">${movie.title}${badge}</h4>
                <span class="row-year">${movie.year ?? ''}</span>
            </div>
        </div>
        <div class="row-arrow">▶</div>
    </a>`;
}

function createMovieRowSessionHTML(movie, roomId) {
    return `
    <div class="movie-row-item" onclick="proposeMovie('${movie.id}', '${roomId}')" style="cursor:pointer;">
        <div class="row-poster"><img src="${movie.poster}"></div>
        <div class="row-content">
            <div class="row-header">
                <h4 class="row-title">${movie.title}</h4>
                <span class="row-year">${movie.year}</span>
            </div>
            <p style="font-size:0.7em; color:#A7C7E7;">Cliquer pour proposer au vote</p>
        </div>
        <div class="row-arrow">✚</div>
    </div>`;
}