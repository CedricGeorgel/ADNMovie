/**
 * ASSETS/JS/FILMS.JS
 * Virtualisation, lazy loading, filtres genre + texte
 */
const ALL_MOVIES = window.FILMS_DATA || [];
const PAGE_SIZE  = 24;

let currentPage    = 0;
let isLoading      = false;
let filteredMovies = [...ALL_MOVIES];

const grid     = document.getElementById('moviesGrid');
const sentinel = document.getElementById('gridSentinel');
const counter  = document.getElementById('gridCount');

// ── Création d'une card ───────────────────────────────────────
function createCard(movie) {
    const card = document.createElement('div');
    card.className = 'movie-card';
    card.dataset.id = movie.id;
    card.style.cssText = 'display:flex;flex-direction:column;height:100%;overflow:hidden;';
    const title = (movie.title || '').replace(/"/g, '&quot;');
    card.innerHTML = `
        <a href="fiche.php?id=${movie.id}" class="poster-wrapper" style="flex:1;display:block;position:relative;">
            <img data-src="${movie.poster || ''}"
                 alt="${title}"
                 src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
                 style="display:block;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity 0.3s;">
        </a>
        <div class="movie-details" style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.10);border-top:none;padding:10px 10px 8px;flex-shrink:0;">
            <h4 class="movie-title" style="margin:0 0 4px;font-size:0.82rem;line-height:1.3em;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${movie.title || '—'}</h4>
            <p class="movie-meta" style="margin:0;font-size:0.72rem;color:var(--text-dim);">${movie.year || ''}</p>
        </div>`;
    return card;
}

// ── Lazy loading ──────────────────────────────────────────────
const imgObs = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (!e.isIntersecting) return;
        const img = e.target;
        img.src = img.dataset.src;
        img.onload  = () => img.style.opacity = '1';
        img.onerror = () => { img.src = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22300%22 height=%22450%22%3E%3Crect width=%22300%22 height=%22450%22 fill=%22%231a1a2e%22/%3E%3Ctext x=%22150%22 y=%22225%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22 font-family=%22sans-serif%22 font-size=%2248%22 fill=%22%23444%22%3E%3F%3C/text%3E%3C/svg%3E'; img.style.opacity = '1'; };
        imgObs.unobserve(img);
    });
}, { rootMargin: '200px' });

// Lazy loading pour les sections h-scroll (badges TMDB)
document.querySelectorAll('.h-scroll img[data-src]').forEach(img => {
    img.src = img.dataset.src;
    img.onload  = () => img.style.opacity = '1';
    img.onerror = () => { img.src = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22300%22 height=%22450%22%3E%3Crect width=%22300%22 height=%22450%22 fill=%22%231a1a2e%22/%3E%3Ctext x=%22150%22 y=%22225%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22 font-family=%22sans-serif%22 font-size=%2248%22 fill=%22%23444%22%3E%3F%3C/text%3E%3C/svg%3E'; img.style.opacity = '1'; };
});

function observeImages() {
    grid.querySelectorAll('img[data-src]').forEach(img => imgObs.observe(img));
}

// ── Batch ─────────────────────────────────────────────────────
function loadBatch() {
    if (isLoading) return;
    const start = currentPage * PAGE_SIZE;
    const end   = Math.min(start + PAGE_SIZE, filteredMovies.length);
    if (start >= filteredMovies.length) return;

    isLoading = true;
    if (currentPage === 0) {
        grid.querySelectorAll('.movie-card-skeleton, .movie-card').forEach(c => c.remove());
    }

    const frag = document.createDocumentFragment();
    for (let i = start; i < end; i++) frag.appendChild(createCard(filteredMovies[i]));
    grid.insertBefore(frag, sentinel);

    currentPage++;
    if (counter) counter.textContent = Math.min(currentPage * PAGE_SIZE, filteredMovies.length);
    isLoading = false;
    observeImages();
}

// ── Infinite scroll ───────────────────────────────────────────
new IntersectionObserver(entries => {
    if (entries[0].isIntersecting) loadBatch();
}, { rootMargin: '400px' }).observe(sentinel);

// ── Filtres ───────────────────────────────────────────────────
let activeGenre = '', activeSearch = '';

function applyFilters() {
    let movies = ALL_MOVIES;
    if (activeGenre) {
        movies = movies.filter(m => {
            // genres peut être une string JSON ou un tableau
            const g = typeof m.genres === 'string' ? m.genres : JSON.stringify(m.genres || '');
            return g.includes(activeGenre);
        });
    }
    if (activeSearch) {
        movies = movies.filter(m => (m.title || '').toLowerCase().includes(activeSearch));
    }
    filteredMovies = movies;
    currentPage    = 0;
    grid.querySelectorAll('.movie-card').forEach(c => c.remove());
    if (counter) counter.textContent = 0;
    if (movies.length > 0) loadBatch();
}

document.getElementById('genreChips')?.addEventListener('click', e => {
    const chip = e.target.closest('.genre-chip');
    if (!chip) return;
    document.querySelectorAll('.genre-chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    activeGenre = chip.dataset.genre;
    applyFilters();
});

document.getElementById('movieSearchInput') || document.getElementById('gridSearch')?.addEventListener('input', function() {
    activeSearch = this.value.trim().toLowerCase();
    applyFilters();
});

// ── Init ──────────────────────────────────────────────────────
loadBatch();

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('movieSearchInput');
    const defaultView = document.getElementById('default-library-view');
    const moviesGrid = document.getElementById('moviesGrid');

    if (searchInput && defaultView && moviesGrid) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            if (query.length > 0) {
                defaultView.style.display = 'none';
            } else {
                defaultView.style.display = 'block';
                moviesGrid.innerHTML = '';
                moviesGrid.classList.remove('search-results-mode');
            }
        });
    }
});