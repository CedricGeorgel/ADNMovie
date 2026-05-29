let _activeType = 'all';

// Filtre texte — respecte le filtre type actif
const searchInput = document.getElementById('movieSearchInput');
if (searchInput) {
    searchInput.addEventListener('input', e => {
        const term = e.target.value.toLowerCase().trim();
        document.querySelectorAll('.collection-item').forEach(item => {
            const title   = item.querySelector('.movie-title')?.innerText.toLowerCase() || '';
            const typeOk  = _activeType === 'all' || item.dataset.type === _activeType.replace('movies','movie').replace('series','series');
            const textOk  = !term || title.includes(term);
            item.style.display = (typeOk && textOk) ? '' : 'none';
        });
    });
}

// Filtre type — exposé globalement pour le inline script
window.filterType = function(type) {
    _activeType = type;
    document.querySelectorAll('#type-all, #type-movies, #type-series').forEach(b => b?.classList.remove('active'));
    document.getElementById('type-' + type)?.classList.add('active');

    const term = document.getElementById('movieSearchInput')?.value.toLowerCase().trim() || '';
    document.querySelectorAll('.collection-item').forEach(item => {
        const title  = item.querySelector('.movie-title')?.innerText.toLowerCase() || '';
        const typeOk = type === 'all'
            || (type === 'movies' && item.dataset.type === 'movie')
            || (type === 'series' && item.dataset.type === 'series');
        const textOk = !term || title.includes(term);
        item.style.display = (typeOk && textOk) ? '' : 'none';
    });
};

document.querySelectorAll('.movie-card img').forEach(img => {
    img.onload = () => img.classList.add('loaded');
});
