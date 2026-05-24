function toggleWishlist(movieId) {
    const btn = document.getElementById('btnWishlist');
    fetch('api/api_wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ movie_id: movieId })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        if (data.wishlisted) {
            btn.textContent = '🔖 Dans ma wishlist';
            btn.classList.add('btn-wishlisted');
        } else {
            btn.textContent = '+ Ajouter à ma watchlist';
            btn.classList.remove('btn-wishlisted');
        }
    })
    .catch(e => console.error('toggleWishlist:', e));
}

function toggleSeriesWishlist(tmdbId) {
    const btn = document.getElementById('btnSeriesWishlist');
    btn.textContent = btn.classList.contains('btn-wishlisted')
        ? '+ Ajouter à ma watchlist'
        : '🔖 Dans ma watchlist';
    btn.classList.toggle('btn-wishlisted');
}
