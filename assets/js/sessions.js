/**
 * ASSETS/JS/SESSIONS.JS - Gestion des Rooms et Événements
 */

function removeProposal(movieId, roomId) {
    if (!confirm("Retirer ce film du scrutin ?")) return;
    fetch('api/api_room_manage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'remove_proposal', movie_id: movieId, room_id: roomId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.message || "Erreur.");
    });
}

function joinRoomByCode() {
    const codeInput = document.getElementById('roomCodeInput');
    if (!codeInput) return;
    
    // On force les majuscules car tes identifiants de session sont générés ainsi
    const code = codeInput.value.trim().toUpperCase(); 

    if (!code) {
        return alert("Veuillez saisir un code de session valide.");
    }

    fetch('api/api_room_manage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'join', room_id: code })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'session.php?id=' + code;
        } else {
            alert("Échec : " + (data.message || "Code invalide ou accès refusé."));
        }
    })
    .catch(err => console.error("Erreur réseau critique:", err));
}

function proposeMovie(movieId, roomId) {
    const rId = roomId || document.body.dataset.roomId;
    fetch('api/api_room_manage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 'action': 'propose_movie', 'movie_id': movieId, 'room_id': rId })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) location.reload();
        else alert(data.message);
    });
}

function toggleVote(movieId, roomId) {
    const rId = roomId || document.body.dataset.roomId;
    fetch('api/api_room_manage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 'action': 'toggle_vote', 'movie_id': movieId, 'room_id': rId })
    })
    .then(res => res.json())
    .then(data => { 
        if(data.success) location.reload(); 
    });
}

function openCreateEventModal() {
    const modal = document.getElementById('eventModal');
    const roomId = document.body.dataset.roomId;
    
    const movieCards = document.querySelectorAll('.movie-card');
    let proposedMovies = [];

    movieCards.forEach(card => {
        const id = card.dataset.id;
        const title = card.querySelector('.movie-title').innerText;
        const voteElement = card.querySelector('.movie-votes span');
        const votes = voteElement ? parseInt(voteElement.innerText) || 0 : 0;
        proposedMovies.push({ id, title, votes });
    });

    proposedMovies.sort((a, b) => b.votes - a.votes);

    let options = '';
    proposedMovies.forEach(movie => {
        options += `<option value="${movie.id}">${movie.title} (${movie.votes} votes)</option>`;
    });

    modal.innerHTML = `
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('eventModal')">&times;</button>
            <h3>CRÉER UN ÉVÈNEMENT</h3>
            <form id="eventForm" class="event-form-grid" onsubmit="submitEvent(); return false;">
                <input type="hidden" name="room_id" value="${roomId}">
                <div class="field-group">
                    <label>FILM</label>
                    <select name="movie_id" class="input-room-create">${options}</select>
                </div>
                <div class="field-group">
                    <label>DATE & HEURE</label>
                    <div style="display:flex; gap:10px;">
                        <input type="date" name="date" class="input-room-create" required>
                        <input type="time" name="time" class="input-room-create" required>
                    </div>
                </div>
                <div class="field-group">
                    <label>LIEU</label>
                    <input type="text" name="location" placeholder="Chez Marc..." class="input-room-create">
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-base active">Lancer l'invitation</button>
                </div>
            </form>
        </div>`;
    openModal('eventModal');

    // Entrée depuis date/time/select ne déclenche pas le submit nativement — on le force
    modal.querySelectorAll('input, select').forEach(el => {
        el.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); submitEvent(); }
        });
    });
}

function submitEvent() {
    const form = document.getElementById('eventForm');
    const formData = new FormData(form);
    formData.append('action', 'create_event');

    fetch('api/api_room_manage.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) location.reload();
        else alert(data.message);
    });
}

/**
 * GESTION DES MEMBRES ET DE LA SESSION
 */
function openManageMembersModal() {
    const modal = document.getElementById('manageMembersModal');
    const myId = document.body.dataset.myId;

    const memberNodes = document.querySelectorAll('#memberList .member-avatar-container');
    let membersListHtml = '<ul class="modal-member-list" style="list-style:none; padding:0;">';

    memberNodes.forEach(node => {
        const userId   = node.dataset.userId;
        const username = node.dataset.tooltip;
        const avatar   = node.dataset.avatar;

        if (userId !== myId) {
            membersListHtml += `
                <li style="display:flex; align-items:center; justify-content:space-between; margin-bottom:15px; padding-bottom:10px; border-bottom:1px solid var(--border);">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <img src="${avatar}" onerror="this.src='assets/default-avatar.png'" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                        <span style="font-weight:bold; color:var(--text);">${username}</span>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button onclick="executeRoomAction('kick', '${userId}')" class="btn-base" style="padding:5px 15px; font-size:0.8rem;">Exclure</button>
                        <button onclick="executeRoomAction('ban', '${userId}')" class="btn-base" style="padding:5px 15px; font-size:0.8rem; background:var(--red); color:white;">Bannir</button>
                    </div>
                </li>`;
        }
    });

    membersListHtml += '</ul>';

    modal.innerHTML = `
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('manageMembersModal')">&times;</button>
            <h3 style="margin-bottom: 20px;">MANAGER LA SESSION</h3>

            ${memberNodes.length > 1 ? membersListHtml : '<p style="color:var(--text-dim); margin-bottom:20px;">Vous êtes actuellement le seul membre de cette session.</p>'}

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px dashed var(--border);">
                <button onclick="executeRoomAction('close')" class="btn-base" style="width:100%; background:darkred; color:white; font-weight:bold;">Clôturer définitivement la session</button>
            </div>
        </div>
    `;

    openModal('manageMembersModal');
}

function executeRoomAction(action, targetId = '') {
    const roomId = document.body.dataset.roomId;
    let message = "";

    // Validation des doutes et sécurisation de l'action
    if (action === 'kick') message = "Êtes-vous certain de vouloir exclure ce membre ?";
    if (action === 'ban') message = "Bannir ce membre lui interdira de revenir. Confirmer ?";
    if (action === 'close') message = "ATTENTION : Clôturer la session est irréversible. Tout le monde sera expulsé. Continuer ?";

    if (!confirm(message)) return;

    fetch('api/api_room_manage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: action, room_id: roomId, target_id: targetId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (action === 'close') {
                window.location.href = 'rooms.php'; // Fuite vers l'accueil
            } else {
                location.reload(); // Rechargement brutal mais efficace
            }
        } else {
            alert("Échec de l'opération : " + (data.message || "Raison inconnue."));
        }
    })
    .catch(err => console.error("Erreur réseau critique:", err));
}
/**
 * Toggle présence à un événement
 */
function toggleEventAttendance(eventId) {
    const roomId = document.body.dataset.roomId;
    fetch('api/api_room_manage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'toggle_attendance', event_id: eventId, room_id: roomId || '' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.message || "Erreur.");
    })
    .catch(err => console.error("Erreur toggleEventAttendance:", err));
}

/**
 * Supprime un événement (hôte uniquement)
 */
function deleteEvent(eventId, roomId) {
    if (!confirm("Supprimer cet événement définitivement ?")) return;
    fetch('api/api_room_manage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'delete_event', event_id: eventId, room_id: roomId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) window.location.href = 'session.php?id=' + roomId;
        else alert(data.message || "Erreur.");
    })
    .catch(err => console.error("Erreur deleteEvent:", err));
}

/**
 * SESSION.JS - Logique de la page session.php (onglets, chat, recherche, tri)
 */
document.addEventListener('DOMContentLoaded', () => {
    const roomId = document.body.dataset.roomId;
    const myId   = document.body.dataset.myId;

    // --- Onglets mobiles ---
    const tabs  = document.querySelectorAll('.session-tab-btn');
    const panes = document.querySelectorAll('.session-pane');
    tabs.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.target;
            tabs.forEach(b => b.classList.remove('active'));
            panes.forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(`section-${target}`).classList.add('active');
        });
    });

    if (!roomId) return;

    // 1. INITIALISATION DU CHAT (une seule fois)
    if (typeof initSessionChat === 'function') {
        initSessionChat();
    }

    // 2. GESTION DE LA RECHERCHE EN OVERLAY
    const searchInput = document.getElementById('movieSearchInput');
    const resultsOverlay = document.getElementById('searchResultsOverlay');

    if (searchInput && resultsOverlay) {
        searchInput.addEventListener('input', async (e) => {
            const query = e.target.value.trim();
            if (query.length < 3) {
                resultsOverlay.style.display = 'none';
                return;
            }

            try {
                // Appel à l'API qui renvoie du JSON
                const response = await fetch(`api/api_search.php?q=${encodeURIComponent(query)}`);
                const results = await response.json();

                if (results && results.length > 0) {
                    resultsOverlay.innerHTML = results.map(m => {
                        // Compatibilité avec différentes structures retournées par search_movies_hybrid()
                        const id     = m.tmdb_id || m.id || '';
                        const title  = m.title || m.name || 'Titre inconnu';
                        const poster = m.poster || m.poster_path || m.image || 'assets/no-poster.svg';
                        const year   = m.year || m.release_year || (m.release_date ? m.release_date.substring(0,4) : '');
                        return `
                        <div class="search-result-item" data-id="${id}" style="display:flex; align-items:center; gap:12px; padding:10px; cursor:pointer; border-bottom:1px solid rgba(255,255,255,0.05);">
                            <img src="${poster}" onerror="this.src='assets/no-poster.svg'" style="width:35px; height:50px; object-fit:cover; border-radius:4px;">
                            <div class="res-info">
                                <div style="font-weight:800; font-size:0.8rem; color:white;">${title}</div>
                                <div style="font-size:0.65rem; color:var(--text-dim);">${year}</div>
                            </div>
                        </div>`;
                    }).join('');
                    resultsOverlay.style.display = 'block';

                    resultsOverlay.querySelectorAll('.search-result-item').forEach(item => {
                        item.onclick = () => proposeMovie(item.dataset.id, roomId);
                    });
                } else {
                    resultsOverlay.innerHTML = '<div style="padding:15px; font-size:0.75rem; color:var(--text-dim);">Aucun résultat trouvé.</div>';
                    resultsOverlay.style.display = 'block';
                }
            } catch (err) {
                console.error("Erreur recherche:", err);
            }
        });

        // Fermeture au clic extérieur
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !resultsOverlay.contains(e.target)) {
                resultsOverlay.style.display = 'none';
            }
        });
    }

    // 3. GESTION DU TRI (Correction pour les boutons sans onclick)
    document.querySelectorAll('.sort-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const type = btn.dataset.sort;
            if (type === 'votes') {
                const grid = document.getElementById('sessionMoviesGrid');
                const cards = Array.from(grid.querySelectorAll('.movie-card'));
                document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                cards.sort((a, b) => {
                    const votesA = parseInt(a.querySelector('.movie-votes span')?.innerText) || 0;
                    const votesB = parseInt(b.querySelector('.movie-votes span')?.innerText) || 0;
                    return votesB - votesA;
                });
                grid.innerHTML = '';
                cards.forEach(c => grid.appendChild(c));
            } else {
                location.reload();
            }
        });
    });
});