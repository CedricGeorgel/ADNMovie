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

function toggleSeriesWishlist(seriesLocalId) {
    const btn = document.getElementById('btnSeriesWishlist');
    fetch('api/api_wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ series_id: seriesLocalId })
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
    .catch(e => console.error('toggleSeriesWishlist:', e));
}

// Fonction pour suivre/ne plus suivre une série
window.toggleSeriesFollow = async function(seriesId) {
    const btn = document.getElementById('btnSeriesFollow');
    if (!btn) return;

    const isFollowed = btn.classList.contains('btn-followed');
    const action = isFollowed ? 'unfollow' : 'follow';

    try {
        const response = await fetch('api/api_series_follow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: action, series_id: seriesId })
        });
        const data = await response.json();

        if (data.success) {
            btn.classList.toggle('btn-followed', data.followed);
            btn.textContent = data.followed ? '✓ Suivi' : '+ Suivre';
        } else {
            console.error('Erreur lors du changement de statut de suivi:', data.message);
            alert('Une erreur est survenue. Veuillez réessayer.');
        }
    } catch (error) {
        console.error('Erreur réseau:', error);
        alert('Erreur réseau. Veuillez vérifier votre connexion.');
    }
};

// Fonction pour marquer une série comme terminée
window.markSeriesAsEnded = async function(seriesId) {
    const btn = document.getElementById('btnSeriesEnded');
    if (!btn) return;

    const isEnded = btn.classList.contains('btn-ended');
    const action = isEnded ? 'unmark_ended' : 'mark_ended';

    try {
        const response = await fetch('api/api_series_follow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: action, series_id: seriesId })
        });
        const data = await response.json();

        if (data.success) {
            btn.classList.toggle('btn-ended', data.ended);
            btn.textContent = data.ended ? 'Terminée' : 'Marquer comme terminée';
            if (data.ended) {
                const followBtn = document.getElementById('btnSeriesFollow');
                if (followBtn) {
                    followBtn.textContent = '+ Suivre';
                    followBtn.classList.remove('btn-followed');
                }
            }
        } else {
            console.error('Erreur lors du changement de statut "terminée":', data.message);
            alert('Une erreur est survenue. Veuillez réessayer.');
        }
    } catch (error) {
        console.error('Erreur réseau:', error);
        alert('Erreur réseau. Veuillez vérifier votre connexion.');
    }
};

// Initialisation des états des boutons au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    const seriesElement = document.querySelector('[data-series-id]');
    const seriesId = seriesElement ? seriesElement.dataset.seriesId : null;

    if (seriesId) {
        fetch(`api/api_series_follow.php?action=is_followed&series_id=${seriesId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const btn = document.getElementById('btnSeriesFollow');
                    if (btn) {
                        btn.classList.toggle('btn-followed', data.followed);
                        btn.textContent = data.followed ? '✓ Suivi' : '+ Suivre';
                    }
                }
            });

        fetch(`api/api_series_follow.php?action=is_ended&series_id=${seriesId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const btn = document.getElementById('btnSeriesEnded');
                    if (btn) {
                        btn.classList.toggle('btn-ended', data.ended);
                        btn.textContent = data.ended ? 'Terminée' : 'Marquer comme terminée';
                    }
                }
            });
    }
});