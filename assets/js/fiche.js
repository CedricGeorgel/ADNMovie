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
            // Mettre à jour aussi le statut 'ended' si on unfollow ? Ou laisser tel quel.
            // Pour l'instant, on laisse le statut 'ended' indépendant.
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
    // Assurez-vous que l'API gère 'unmark_ended' si nécessaire pour le unfollow
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
            // Si on marque comme terminée, on peut désactiver le suivi ou changer le bouton
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

// Initialisation des états des boutons au chargement de la page (si nécessaire)
document.addEventListener('DOMContentLoaded', () => {
    // Assurez-vous que l'ID de la série est disponible dans le DOM
    // Par exemple, via un data-attribute sur un élément parent
    const seriesElement = document.querySelector('[data-series-id]');
    const seriesId = seriesElement ? seriesElement.dataset.seriesId : null;

    if (seriesId) {
        // Vérifier le statut de suivi
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
        // Vérifier le statut "terminée"
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
