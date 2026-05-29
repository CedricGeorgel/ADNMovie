document.addEventListener('DOMContentLoaded', () => {
    const data = window.MOOVIE_ADMIN_DATA;
    if (!data) return;

    const searchInput = document.getElementById('userSearchDisplay');
    const hiddenInput = document.getElementById('targetUser');
    const resultsContainer = document.getElementById('autocompleteResults');
    const panel = document.getElementById('userDetailsPanel');

    // 1. AUTOCOMPLÉTION
    if(searchInput) {
        searchInput.addEventListener('input', function() {
            const val = this.value.toLowerCase().trim();
            resultsContainer.innerHTML = '';
            if (!val) { resultsContainer.style.display = 'none'; return; }

            const filtered = data.users.filter(u => u.username.toLowerCase().includes(val)).slice(0, 8);

            if (filtered.length > 0) {
                resultsContainer.style.display = 'block';
                filtered.forEach(u => {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item';

                    const safeId = String(u.id);
                    const shortId = safeId.length > 6 ? safeId.substring(0, 6) : safeId;

                    div.innerHTML = `
                        <img src="${u.avatar}" class="autocomplete-avatar" onerror="this.src='assets/default-avatar.png'">
                        <span>${u.username} <small>(${shortId}...)</small></span>
                    `;
                    div.onclick = () => {
                        loadUserProfile(u.id, u.username, u.avatar);
                        resultsContainer.style.display = 'none';
                    };
                    resultsContainer.appendChild(div);
                });
            } else {
                resultsContainer.style.display = 'block';
                resultsContainer.innerHTML = '<div class="autocomplete-empty" style="padding:15px; color:gray;">Aucun résultat</div>';
            }
        });
    }

    // 2. CHARGEMENT DU PROFIL
    window.loadUserProfile = (id, username, avatar) => {
        if(searchInput) searchInput.value = username;
        if(hiddenInput) hiddenInput.value = id;

        const avatarEl = document.getElementById('panelAvatar');
        if(avatarEl) avatarEl.src = avatar || 'assets/default-avatar.png';

        const userEl = document.getElementById('panelUsername');
        if(userEl) userEl.innerText = username;

        const idEl = document.getElementById('panelId');
        if(idEl) idEl.innerText = "ID: " + id;

        const voteEl = document.getElementById('panelVoteCount');
        if(voteEl) voteEl.innerText = data.activity[id] || 0;

        if(panel) panel.style.display = 'block';

        fetch(`api/api_get_user_details.php?id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const seenEl = document.getElementById('panelSeenCount');
                    if(seenEl) seenEl.innerText = res.seen_count || 0;
                    renderBadges(res.badges);

                    // Rôle
                    const roleSelect = document.getElementById('targetRole');
                    if (roleSelect) roleSelect.value = res.role || 'user';

                    // Flag
                    const btnFlag = document.getElementById('btnFlagUser');
                    if (btnFlag) {
                        const isFlagged = res.is_flagged;
                        btnFlag.textContent = isFlagged ? '✅ Désignaler' : '🚩 Signaler';
                        btnFlag.style.background = isFlagged ? 'rgba(110,231,183,0.1)' : '';
                        btnFlag.dataset.flagged = isFlagged ? '1' : '0';
                    }
                }
            })
            .catch(err => console.error('Erreur API Details:', err));
    };

    function renderBadges(userBadges) {
        const container = document.getElementById('panelBadgesList');
        if(!container) return;

        container.innerHTML = '';
        if (!userBadges || userBadges.length === 0) {
            container.innerHTML = '<span style="font-size:0.7rem; color:var(--text-dim);">Aucun succès</span>';
            return;
        }
        userBadges.forEach(bk => {
            const b = data.badgeLib[bk];
            if (b) {
                const span = document.createElement('span');
                span.className = 'p-badge-tag';
                span.style.color = b.color;
                span.style.borderColor = b.color + '44';
                span.innerText = b.label;
                container.appendChild(span);
            }
        });
    }

    window.selectUserDirect = (id, username) => {
        const user = data.users.find(u => u.id == id);
        loadUserProfile(id, username, user ? user.avatar : '');
    };

    // 3. ACTIONS BADGES
    const manageBadge = (action) => {
        if(!hiddenInput) return;
        const userId = hiddenInput.value;
        const badgeInput = document.getElementById('targetBadge');
        if(!badgeInput) return;

        const badgeKey = badgeInput.value;
        if (!userId) return alert("Veuillez d'abord chercher un sujet.");

        fetch('api/api_admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action, target_id: userId, badge_key: badgeKey })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                alert(res.message);
                const avatarSrc = document.getElementById('panelAvatar') ? document.getElementById('panelAvatar').src : '';
                loadUserProfile(userId, searchInput.value, avatarSrc);
            } else {
                alert("Erreur : " + res.message);
            }
        })
        .catch(err => console.error('Erreur API Admin:', err));
    };

    const btnAward = document.getElementById('btnAward');
    if(btnAward) btnAward.onclick = () => manageBadge('award_badge');

    const btnRevoke = document.getElementById('btnRevoke');
    if(btnRevoke) btnRevoke.onclick = () => manageBadge('revoke_badge');

    // 4. ACTION RÔLE
    const btnSetRole = document.getElementById('btnSetRole');
    if (btnSetRole) {
        btnSetRole.onclick = () => {
            const userId = hiddenInput ? hiddenInput.value : '';
            const roleSelect = document.getElementById('targetRole');
            if (!userId) return alert('Veuillez d\'abord sélectionner un utilisateur.');
            const role = roleSelect ? roleSelect.value : 'user';
            if (!confirm(`Attribuer le rôle "${window.MOOVIE_ROLE_LABELS[role] || role}" ?`)) return;

            fetch('api/api_set_role.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ target_id: userId, role })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert('Rôle mis à jour.');
                    const avatarSrc = document.getElementById('panelAvatar') ? document.getElementById('panelAvatar').src : '';
                    loadUserProfile(userId, searchInput ? searchInput.value : '', avatarSrc);
                } else {
                    alert('Erreur : ' + res.message);
                }
            })
            .catch(err => console.error('Erreur setRole:', err));
        };
    }

    // 5. ACTION FLAG
    const btnFlagUser = document.getElementById('btnFlagUser');
    if (btnFlagUser) {
        btnFlagUser.onclick = () => {
            const userId = hiddenInput ? hiddenInput.value : '';
            if (!userId) return alert('Veuillez d\'abord sélectionner un utilisateur.');
            const currentlyFlagged = btnFlagUser.dataset.flagged === '1';
            const newFlagged = currentlyFlagged ? 0 : 1;
            const label = newFlagged ? 'Signaler cet utilisateur ?' : 'Retirer le signalement ?';
            if (!confirm(label)) return;

            fetch('api/api_flag_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ target_id: userId, flagged: newFlagged })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    btnFlagUser.dataset.flagged = res.flagged ? '1' : '0';
                    btnFlagUser.textContent = res.flagged ? '✅ Désignaler' : '🚩 Signaler';
                    btnFlagUser.style.background = res.flagged ? 'rgba(110,231,183,0.1)' : '';
                } else {
                    alert('Erreur : ' + res.message);
                }
            })
            .catch(err => console.error('Erreur flagUser:', err));
        };
    }

    document.addEventListener('click', (e) => {
        if (searchInput && resultsContainer && !searchInput.contains(e.target)) {
            resultsContainer.style.display = 'none';
        }
    });
});

// ── DELETE MOVIE ──────────────────────────────────────────────
window.deleteMovie = () => {
    const movieId = document.getElementById('deleteMovieId').value.trim();

    if (!movieId) return alert("Veuillez saisir un ID de film valide.");

    if (!confirm(`Êtes-vous sûr de vouloir purger le film ID: ${movieId} ?`)) return;
    if (!confirm(`CONFIRMATION FINALE :\nCela va supprimer les données du film ET les notes dans les dossiers de TOUS les utilisateurs.\nContinuer ?`)) return;

    fetch('api/api_delete_movie.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ movie_id: movieId })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            alert("Cible neutralisée : " + res.message);
            document.getElementById('deleteMovieId').value = '';
        } else {
            alert("Échec de la purge : " + res.message);
        }
    })
    .catch(err => {
        console.error("Erreur critique de purge :", err);
        alert("Erreur de liaison avec le serveur.");
    });
};

// ── ROADMAP : CHANGEMENT D'ONGLET ─────────────────────────────
function switchTab(view, btn) {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', view);
    window.location.href = url.toString();
}

// ── ROADMAP : MISE À JOUR D'UN CHAMP (status, type, priority) ─
function updateSystemItem(id, field, value) {
    const fd = new FormData();
    fd.append('action', 'update_management');
    fd.append('id', id);
    fd.append('field', field);
    fd.append('value', value);

    fetch('api/api_roadmap.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert('Erreur : ' + (d.message || 'Mise à jour échouée'));
            }
        })
        .catch(err => {
            console.error('Erreur updateSystemItem:', err);
            alert('Erreur réseau.');
        });
}

// ── ROADMAP : TOGGLE MODE ÉDITION ─────────────────────────────
function toggleEditText(btn, id) {
    const card      = btn.closest('.roadmap-card');
    const editZone  = card.querySelector('.card-edit-zone');
    const titleDisp = card.querySelector('.card-title-display');
    const descDisp  = card.querySelector('.card-desc-display');
    const isEditing = editZone.style.display !== 'none';

    if (isEditing) {
        cancelEditText(btn);
    } else {
        titleDisp.style.display = 'none';
        descDisp.style.display  = 'none';
        editZone.style.display  = 'flex';
        btn.textContent = '✕ FERMER';
        btn.style.color = 'var(--text-dim)';
        btn.style.borderColor = 'var(--border)';
        card.querySelector('.card-edit-title').focus();
    }
}

// ── ROADMAP : ANNULER L'ÉDITION ───────────────────────────────
function cancelEditText(btnInCard) {
    const card      = btnInCard.closest('.roadmap-card');
    const editZone  = card.querySelector('.card-edit-zone');
    const titleDisp = card.querySelector('.card-title-display');
    const descDisp  = card.querySelector('.card-desc-display');
    const editBtn   = card.querySelector('.card-edit-btn');

    card.querySelector('.card-edit-title').value      = titleDisp.textContent;
    card.querySelector('.card-edit-desc').textContent = descDisp.textContent;

    editZone.style.display  = 'none';
    titleDisp.style.display = '';
    descDisp.style.display  = '';

    if (editBtn) {
        editBtn.textContent       = '✏️ ÉDITER';
        editBtn.style.color       = 'var(--pastel-blue)';
        editBtn.style.borderColor = 'var(--pastel-blue)';
    }
}

// ── ROADMAP : CONFIRMER L'ÉDITION ────────────────────────────
function confirmEditText(confirmBtn, id) {
    const card  = confirmBtn.closest('.roadmap-card');
    const title = card.querySelector('.card-edit-title').value.trim();
    const desc  = card.querySelector('.card-edit-desc').value.trim();

    if (!title) {
        alert('Le titre ne peut pas être vide.');
        return;
    }

    confirmBtn.textContent = '...';
    confirmBtn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'edit_text');
    fd.append('id', id);
    fd.append('title', title);
    fd.append('description', desc);

    fetch('api/api_roadmap.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const titleDisp = card.querySelector('.card-title-display');
                const descDisp  = card.querySelector('.card-desc-display');
                titleDisp.textContent = title;
                descDisp.textContent  = desc;

                cancelEditText(confirmBtn);

                card.style.borderColor = 'var(--success, #6ee7b7)';
                setTimeout(() => card.style.borderColor = '', 1500);
            } else {
                alert('Erreur sauvegarde : ' + (d.message || ''));
                confirmBtn.textContent = '✓ CONFIRMER';
                confirmBtn.disabled = false;
            }
        })
        .catch(err => {
            console.error('Erreur confirmEditText:', err);
            alert('Erreur réseau.');
            confirmBtn.textContent = '✓ CONFIRMER';
            confirmBtn.disabled = false;
        });
}

// ── ROADMAP : SUPPRESSION ─────────────────────────────────────
function deleteSystemItem(id) {
    if (!confirm('Supprimer définitivement cet élément ?')) return;

    const fd = new FormData();
    fd.append('action', 'delete_management');
    fd.append('id', id);

    fetch('api/api_roadmap.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert('Erreur : ' + (d.message || 'Suppression échouée'));
            }
        })
        .catch(err => {
            console.error('Erreur deleteSystemItem:', err);
            alert('Erreur réseau.');
        });
}