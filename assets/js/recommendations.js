// ── Loaders indépendants par type (film / series) ────────────────────────────
// Affiché uniquement quand l'algo doit recalculer (aucun pending en cache).
// Délai : 7s pour les tests — remettre à (3000 + Math.random() * 3000) en prod.

function initRecoLoader(type) {
    var overlay = document.getElementById('reco-loading-' + type);
    var content = document.getElementById('reco-content-' + type);
    if (!overlay || !content) return;

    var delay = 7000; // TODO : remplacer par (3000 + Math.random() * 3000) en prod

    setTimeout(function () {
        overlay.style.transition = 'opacity 0.5s ease';
        overlay.style.opacity    = '0';
        content.style.display    = 'block';
        setTimeout(function () { overlay.remove(); }, 500);
    }, delay);
}

initRecoLoader('film');
initRecoLoader('series');


// ── Skip d'une reco ───────────────────────────────────────────────────────────
function skipReco(movieId, btn) {
    if (!confirm('Écarter ce spécimen de votre profil ?')) return;

    fetch('api/api_skip_reco.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ movie_id: movieId })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        const card = btn.closest('.movie-card').parentElement;
        card.style.transition = 'opacity 0.4s, transform 0.4s';
        card.style.opacity    = '0';
        card.style.transform  = 'scale(0.9)';
        setTimeout(function () {
            card.remove();
            if (document.querySelectorAll('.movie-card').length === 0) {
                location.reload();
            }
        }, 400);
    });
}


// ── Recalibrage manuel ────────────────────────────────────────────────────────
function recoRefresh(type) {
    const btn = document.querySelector(
        '.reco-refresh-btn[onclick*="' + type + '"]'
    );
    if (btn) {
        btn.disabled     = true;
        btn.textContent  = '↺ Recalibrage…';
        btn.style.opacity = '0.5';
    }

    fetch('api/api_reco_refresh.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'type=' + encodeURIComponent(type)
    })
    .then(r => r.json())
    .then(function (data) {
        if (!data.success) {
            if (btn) {
                btn.disabled      = false;
                btn.textContent   = '↺ ' + (data.message || 'Erreur');
                btn.style.opacity = '0.4';
            }
            return;
        }
        // Succès : reload avec le loading overlay naturel
        location.reload();
    })
    .catch(function () {
        if (btn) {
            btn.disabled     = false;
            btn.textContent  = '↺ Erreur réseau';
            btn.style.opacity = '0.4';
        }
    });
}
