/**
 * ASSETS/JS/UI.JS - Gestion de l'interface, des analyses et des interactions sociales
 */

/* ── GESTION DES MODALES ────────────────────────────────────────────────── */

function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

function openRateModal() {
    const modal = document.getElementById('rateModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        history.pushState({ modalOpen: true }, "");
    }
}

function closeRateModal() {
    closeModal('rateModal');
    if (history.state && history.state.modalOpen) {
        history.back();
    }
}

window.onpopstate = function(event) {
    const modal = document.getElementById('rateModal');
    if (modal && modal.classList.contains('active')) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
};

/* ── NAVIGATION MODALE ANALYSE ─────────────────────────────────────────── */

window.goToStep1 = function() {
    const step1 = document.getElementById('rateStep-1');
    const step2 = document.getElementById('rateStep-2');
    if (step1) step1.style.display = 'block';
    if (step2) step2.style.display = 'none';
};

window.goToStep2 = function() {
    const step1 = document.getElementById('rateStep-1');
    const step2 = document.getElementById('rateStep-2');
    if (step1) step1.style.display = 'none';
    if (step2) step2.style.display = 'block';
};

window.toggleCriteriaChip = function(key) {
    const chip = document.getElementById('chip-' + key);
    const group = document.getElementById('group-' + key);
    const checkbox = document.getElementById('check-' + key);

    if (!chip) return;

    if (chip.classList.contains('criteria-chip--active')) {
        chip.classList.remove('criteria-chip--active');
        if (group) group.style.display = 'none';
        if (checkbox) checkbox.checked = false;
    } else {
        const activeCount = document.querySelectorAll('.criteria-chip--active').length;
        if (activeCount >= 3) {
            alert("Vous ne pouvez sélectionner que 3 axes d'analyse.");
            return;
        }
        chip.classList.add('criteria-chip--active');
        if (group) group.style.display = 'block';
        if (checkbox) checkbox.checked = true;
    }
    
    const counter = document.getElementById('criteriaCount');
    if (counter) {
        counter.textContent = document.querySelectorAll('.criteria-chip--active').length;
    }
};

window.onLikeSliderInput = function(slider) {
    const display = document.getElementById('likeValueDisplay');
    if (display) {
        const val = parseInt(slider.value, 10);
        display.textContent = val > 0 ? '+' + val : val;
    }
};

window.closeMutationViewAndReload = function() {
    closeRateModal();
    location.reload();
};

/* ── API : NOTATION & ACTIONS RAPIDES ──────────────────────────────────── */

function submitRateForm() {
    const payload = new URLSearchParams();
    const movieIdInput = document.querySelector('input[name="movie_id"]');
    if (!movieIdInput || !movieIdInput.value) {
        alert("Erreur locale : Impossible de lire l'ID du film.");
        return;
    }

    payload.append('movie_id', movieIdInput.value);
    payload.append('action', 'rate');

    // Lire content_kind depuis le formulaire (movie | tv)
    const contentKindInput = document.querySelector('input[name="content_kind"]');
    const contentKind = contentKindInput ? contentKindInput.value : 'movie';
    payload.append('content_kind', contentKind);

    // Si c'est une série (saison individuelle), transmettre series_id et season_number
    if (contentKind === 'tv') {
        const seriesIdInput  = document.querySelector('input[name="series_id"]');
        const seasonInput    = document.querySelector('input[name="season_number"]');
        if (seriesIdInput) payload.append('series_id',     seriesIdInput.value);
        if (seasonInput)   payload.append('season_number', seasonInput.value);
    }

    // Si c'est une notation série complète, series_id est injecté par fiche.php
    if (contentKind === 'tv_full') {
        const seriesId = window.__seriesLocalId || 0;
        if (!seriesId) {
            alert('Erreur : ID série introuvable.');
            return;
        }
        payload.append('series_id', seriesId);
    }

    // Si c'est une notation d'épisode, transmettre les identifiants injectés par fiche.php
    if (contentKind === 'episode') {
        const seriesId      = window.__episodeSeriesLocalId || 0;
        const seasonNumber  = window.__episodeSeasonNumber  || 0;
        const episodeNumber = window.__episodeNumber        || 0;
        const totalEpisodes = window.__episodeTotalEpisodes || 0;
        if (!seriesId || !seasonNumber || !episodeNumber) {
            alert('Erreur : identifiants épisode introuvables.');
            return;
        }
        payload.append('series_id',      seriesId);
        payload.append('season_number',  seasonNumber);
        payload.append('episode_number', episodeNumber);
        payload.append('total_episodes', totalEpisodes);
    }

    const likeActive = document.querySelector('input[name="like_active"]');
    if (likeActive) payload.append('like_active', likeActive.value);

    const likeSlider = document.getElementById('likeSlider');
    if (likeSlider && !likeSlider.disabled) {
        payload.append('like_score', likeSlider.value);
    }

    const criteria = ['complexite', 'previsibilite', 'intensite', 'malaise', 'stylisation', 'dynamique', 'depaysement', 'coherence'];
    criteria.forEach(c => {
        const check = document.getElementById('check-' + c);
        const slider = document.getElementById('slider-' + c);
        if (check && check.checked && slider) {
            payload.append('active_scores[' + c + ']', '1');
            payload.append('scores[' + c + ']', slider.value);
        }
    });

    // Routing selon content_kind
    const endpointMap = {
        'tv_full': 'api/api_rate_series_full.php',
        'episode': 'api/api_rate_episode.php',
    };
    const endpoint = endpointMap[contentKind] || 'api/api_rate.php';

    fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert("Refus du serveur : " + (data.message || "Cause inconnue"));
            return;
        }
        if (typeof showMutationView === 'function') {
            const dnaSnapshot = window.__currentUserDna || {};
            const before = (Object.keys(dnaSnapshot).length > 0) ? dnaSnapshot : (data.dna_before || {});
            showMutationView(before, data.dna || {});
        } else {
            location.reload();
        }
    })
    .catch(err => console.error("Erreur de transport :", err));
}

function openSeriesFullRateModal() {
    openRateModal();
}

function toggleStatus(type, movieId) {
    fetch('api/api_rate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 'action': type, 'movie_id': movieId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
        else alert(data.message);
    });
}

/* ── GESTION DES SESSIONS (ROOMS) ───────────────────────────────────────── */

function openCreateRoomModal() {
    const modal = document.getElementById('createRoomModal');
    if (!modal) return;
    modal.innerHTML = `
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal('createRoomModal')">&times;</button>
            <h3>CRÉER UNE SESSION</h3>
            <form id="createRoomForm" style="margin-top:20px;" onsubmit="submitCreateRoom(); return false;">
                <div class="slider-group">
                    <label class="slider-labels">NOM DE LA SESSION</label>
                    <input type="text" name="room_name" placeholder="ex: Soirée Pizza" class="input-room-create">
                </div>
                <div style="display:flex;gap:10px;margin-top:16px;">
                    <label style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;border:1px solid var(--border);border-radius:12px;cursor:pointer;font-size:0.8rem;transition:border-color .2s;">
                        <input type="radio" name="room_visibility" value="0" checked style="accent-color:var(--pastel-blue);">
                        <span>Privée</span>
                    </label>
                    <label style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;border:1px solid var(--border);border-radius:12px;cursor:pointer;font-size:0.8rem;transition:border-color .2s;">
                        <input type="radio" name="room_visibility" value="1" style="accent-color:var(--pastel-blue);">
                        <span>Publique</span>
                    </label>
                </div>
                <div class="modal-actions" style="margin-top:20px;">
                    <button type="submit" class="btn-base active">Lancer la session</button>
                </div>
            </form>
        </div>`;
    openModal('createRoomModal');
}

function submitCreateRoom() {
    const nameInput = document.querySelector('input[name="room_name"]');
    const name = nameInput ? nameInput.value.trim() : "";
    if (!name) return alert("Donne un nom à ta session !");

    const visibilityEl = document.querySelector('input[name="room_visibility"]:checked');
    const isPublic = visibilityEl ? visibilityEl.value : '0';

    fetch('api/api_room_manage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 'action': 'create', 'name': name, 'is_public': isPublic })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'session.php?id=' + data.room_id;
        } else {
            alert(data.message || "Erreur lors de la création.");
        }
    });
}

/* ── PROFIL & ADN ───────────────────────────────────────────────────────── */

function confirmAnonymization() {
    if (confirm("Supprimer votre compte ? Cette action est irréversible.") && 
        confirm("Dernière chance : Vos analyses deviendront anonymes. On y va ?")) {
        fetch('api/api_anonymize.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => res.json())
        .then(data => {
            if (data.success) window.location.href = 'index.php';
            else alert("Erreur : " + data.message);
        })
        .catch(err => console.error("Erreur API:", err));
    }
}

function copyAdnLink(userId) {
    const isGlitched = Math.random() < 0.05;
    const page = isGlitched ? 'anomaly' : 'adn';
    const url  = window.location.origin + '/' + page + '.php?id=' + userId;
    const btn  = document.getElementById('btnShareAdn');

    navigator.clipboard.writeText(url).then(() => {
        if (btn) {
            btn.classList.remove('copied', 'copied-glitch');
            btn.classList.add(isGlitched ? 'copied-glitch' : 'copied');
            setTimeout(() => btn.classList.remove('copied', 'copied-glitch'), 2200);
        } else if (isGlitched) {
            alert("ALERTE : Séquence instable copiée. L'archive semble corrompue...");
        } else {
            alert("Lien ADN copié !");
        }
    }).catch(() => {
        prompt("Copiez ce lien manuellement :", url);
    });
}

/* ── FICHE FILM : COMMENTAIRES (Reddit-style threads) ───────────────────── */

const THREAD_COLORS = ['var(--pastel-blue)', '#a78bfa', '#34d399', '#fb923c', 'var(--text-dim)'];

function _buildCommentTree(flat) {
    const map = {};
    flat.forEach(c => { map[c.id] = { ...c, children: [] }; });
    const roots = [];
    flat.forEach(c => {
        if (c.parent_id && map[c.parent_id]) map[c.parent_id].children.push(map[c.id]);
        else roots.push(map[c.id]);
    });
    roots.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    return roots;
}

function _renderCommentNode(node, depth) {
    const color  = THREAD_COLORS[Math.min(depth, THREAD_COLORS.length - 1)];
    const safe   = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const avatar = (node.avatar || 'assets/default-avatar.png').replace(/"/g,'&quot;');
    const dt     = new Date(node.created_at).toLocaleString('fr-FR',
        { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
    const hasChildren = node.children && node.children.length > 0;

    const collapseBtn = hasChildren
        ? `<button class="c-collapse" onclick="toggleCommentThread(this)"
               title="Réduire" style="background:none;border:none;cursor:pointer;
               color:${color};font-size:0.75rem;padding:0 2px;flex-shrink:0;align-self:flex-start;margin-top:14px;">▼</button>`
        : `<span style="width:14px;flex-shrink:0;"></span>`;

    const replyBtn = node.is_logged_in
        ? `<button onclick="replyToComment(${node.id},'${safe(node.username).replace(/'/g,"\\'")}')"
               class="comment-reply-btn">Répondre</button>`
        : '';
    const deleteBtn = node.is_admin
        ? `<button onclick="deleteComment(${node.id})"
               style="background:none;border:none;color:var(--danger);font-size:0.6rem;
                      cursor:pointer;text-transform:uppercase;font-weight:800;opacity:0.5;"
               onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.5">[Purger]</button>`
        : '';

    const childrenHtml = hasChildren
        ? `<div class="c-children" style="margin-top:10px;border-left:2px solid ${color};padding-left:16px;">
               ${node.children.map(c => _renderCommentNode(c, depth + 1)).join('')}
           </div>`
        : '';

    return `
    <div class="comment-item" data-id="${node.id}"
         style="display:flex;gap:8px;margin-bottom:${depth===0?'22px':'10px'};">
        ${collapseBtn}
        <div style="flex-shrink:0;">
            <a href="adn.php?id=${encodeURIComponent(node.user_id)}"
               style="display:block;width:32px;height:32px;border-radius:50%;overflow:hidden;">
                <img src="${avatar}" onerror="this.src='assets/default-avatar.png'"
                     style="width:100%;height:100%;object-fit:cover;">
            </a>
        </div>
        <div style="flex:1;min-width:0;">
            <div style="display:flex;justify-content:space-between;align-items:center;
                        margin-bottom:5px;flex-wrap:wrap;gap:4px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-weight:700;font-size:0.85rem;color:var(--text-main);">
                        ${safe(node.username)}${node.role_badge}
                    </span>
                    <span style="font-size:0.65rem;color:var(--text-dim);font-family:monospace;">${dt}</span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">${replyBtn}${deleteBtn}</div>
            </div>
            <p style="font-size:0.85rem;line-height:1.5;color:var(--text-muted);margin:0;word-wrap:break-word;">
                ${node.content_html}
            </p>
            ${childrenHtml}
        </div>
    </div>`;
}

window.toggleCommentThread = function(btn) {
    const item     = btn.closest('.comment-item');
    const children = item.querySelector('.c-children');
    if (!children) return;
    const collapsed = children.style.display === 'none';
    children.style.display = collapsed ? '' : 'none';
    btn.textContent = collapsed ? '▼' : '▶';
};

document.addEventListener('DOMContentLoaded', () => {
    // 1. Commentaires en arbre
    const commentsList    = document.getElementById('commentsList');
    const commentForm     = document.getElementById('commentForm');
    const commentsContainer = document.querySelector('.movie-comments-section');

    if (commentsList && commentsContainer) {
        const movieId = commentsContainer.dataset.movieId;

        const loadComments = async () => {
            try {
                const res  = await fetch(`api/api_comments.php?movie_id=${encodeURIComponent(movieId)}`);
                const data = await res.json();
                if (!data.success || !data.comments.length) {
                    commentsList.innerHTML = '<p style="color:var(--text-dim);font-size:0.8rem;font-style:italic;">Aucune analyse enregistrée.</p>';
                    return;
                }
                const tree = _buildCommentTree(data.comments);
                commentsList.innerHTML = tree.map(n => _renderCommentNode(n, 0)).join('');
            } catch(err) { console.error('Erreur archives :', err); }
        };

        loadComments();

        if (commentForm) {
            commentForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const input    = document.getElementById('commentInput');
                const parentEl = document.getElementById('commentParentId');
                const content  = input.value.trim();
                if (!content) return;

                const params = { movie_id: movieId, content };
                if (parentEl && parentEl.value) params.parent_id = parentEl.value;

                try {
                    const res  = await fetch('api/api_post_comment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams(params)
                    });
                    const data = await res.json();
                    if (data.success) {
                        input.value = '';
                        if (parentEl) parentEl.value = '';
                        cancelReply();
                        loadComments();
                    } else { alert(data.message); }
                } catch(err) { console.error('Erreur post commentaire :', err); }
            });
        }
    }

    // 2. Observer pour les cartes d'algorithme
    const cards = document.querySelectorAll('.algo-card');
    if (cards.length > 0) {
        const algoObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = 1;
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });
        cards.forEach(card => {
            card.style.opacity = 0;
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'all 0.6s ease-out';
            algoObserver.observe(card);
        });
    }
});

/* ── COMMENTAIRES : RÉPONSE ──────────────────────────────────────────────── */

window.replyToComment = function (commentId, username) {
    const textarea  = document.getElementById('commentInput');
    const parentEl  = document.getElementById('commentParentId');
    const indicator = document.getElementById('commentReplyIndicator');
    const label     = document.getElementById('commentReplyLabel');
    if (!textarea) return;

    if (parentEl)  parentEl.value = commentId;
    textarea.value = '@' + username + ' ';
    if (label)     label.textContent = 'Réponse à @' + username;
    if (indicator) indicator.style.display = 'flex';

    textarea.focus();
    textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

window.cancelReply = function () {
    const parentEl  = document.getElementById('commentParentId');
    const indicator = document.getElementById('commentReplyIndicator');
    if (parentEl)  parentEl.value = '';
    if (indicator) indicator.style.display = 'none';
};

window.purgeFilm = function(id) {
    if (confirm('Purger l\'archive et toutes ses notes ?') && confirm('Action irréversible. Confirmer ?')) {
        fetch('api/api_delete_movie.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ movie_id: id })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) window.location.href = 'films.php';
            else alert(d.message);
        });
    }
};

/**
 * Suppression administrative d'un commentaire
 */
window.deleteComment = function(commentId) {
    if (!confirm("Voulez-vous vraiment supprimer définitivement cette pensée ?")) return;

    fetch('api/api_delete_comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 'comment_id': commentId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || "Erreur lors de la suppression.");
        }
    })
    .catch(err => console.error("Échec de la purge :", err));
};

/**
 * MÉCANIQUES DU CHAT DE SESSION (Smart Polling)
 */
let lastMessageId = 0;
let pollingInterval = 3000;
let pollingTimer = null;

let _chatInitialized = false;

function initSessionChat() {
    if (_chatInitialized) return;

    const chatContainer = document.getElementById('chatMessages');
    const chatForm = document.getElementById('chatForm');
    const roomId = document.body.dataset.roomId;

    if (!chatContainer || !chatForm || !roomId) return;

    _chatInitialized = true;

    const fetchNewMessages = async () => {
        try {
            const apiBase = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/');
            const response = await fetch(`${apiBase}api/api_chat_get.php?room_id=${roomId}&last_id=${lastMessageId}`);

            if (!response.ok) {
                resetPollingDelay(false);
                pollingTimer = setTimeout(fetchNewMessages, pollingInterval);
                return;
            }

            const data = await response.json();

            if (data.success && data.messages.length > 0) {
                lastMessageId = data.messages[data.messages.length - 1].id;
                
                data.messages.forEach(msg => {
                    const isMe = String(msg.user_id) === String(document.body.dataset.myId);
                    appendMessage(msg, isMe);
                });

                chatContainer.scrollTop = chatContainer.scrollHeight;
                resetPollingDelay(true);
            } else {
                resetPollingDelay(false);
            }
        } catch (err) {
            console.error("Échec de synchronisation chat :", err);
            resetPollingDelay(false);
        }
        
        pollingTimer = setTimeout(fetchNewMessages, pollingInterval);
    };

    const resetPollingDelay = (hasActivity) => {
        if (hasActivity) {
            pollingInterval = 2000;
        } else {
            pollingInterval = Math.min(pollingInterval + 1000, 10000);
        }
    };

    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('chatInput');
        const text = input.value.trim();
        if (!text) return;

        const replyToId = document.getElementById('chatReplyToId')?.value || '';
        input.value = '';
        cancelChatReply();

        try {
            const params = { 'room_id': roomId, 'message': text };
            if (replyToId) params['reply_to_id'] = replyToId;
            await fetch('api/api_chat_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(params)
            });
            clearTimeout(pollingTimer);
            fetchNewMessages();
        } catch (err) {
            console.error("Erreur d'envoi message :", err);
        }
    });

    fetchNewMessages();
}

function formatChatText(text) {
    // Échappement HTML de base
    text = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    // Échappement backslash : \* \_ \` \# → littéral
    const escTokens = {};
    let escIdx = 0;
    text = text.replace(/\\([*_`#\-\\>])/g, (_, ch) => {
        const key = '\x02' + escIdx++ + '\x03';
        escTokens[key] = ch;
        return key;
    });

    // @[pseudo:userId] → lien adn avec le vrai ID (résolu côté serveur)
    text = text.replace(/@\[(\w+):(\w+)\]/g, (_, pseudo, userId) => {
        const isAnomaly = Math.floor(Math.random() * 24) === 0;
        const page = isAnomaly ? 'anomaly' : 'adn';
        return `<a href="${page}?id=${encodeURIComponent(userId)}" style="color:var(--pastel-blue);font-weight:bold;text-decoration:none;">@${pseudo}</a>`;
    });

    // @pseudo non résolu (user introuvable en BDD)
    text = text.replace(/@(\w+)/g, (_, pseudo) => {
        return `<span style="color:var(--pastel-blue);font-weight:bold;">@${pseudo}</span>`;
    });

    // Markdown inline : bold **text**, italic *text*, code `text`
    text = text.replace(/\*\*(.+?)\*\*/gs, (_, t) => `<strong style="color:var(--text-main);">${t}</strong>`);
    text = text.replace(/\*([^*\n]+?)\*/g,  (_, t) => `<em>${t}</em>`);
    text = text.replace(/`([^`]+)`/g, (_, t) => `<code style="background:rgba(255,255,255,0.07);padding:2px 5px;border-radius:4px;font-size:0.85em;font-family:monospace;">${t}</code>`);

    // #slug → archive (vert)
    text = text.replace(/(?<!\S)#([\w-]+)/g, (_, slug) =>
        `<a href="content.php?slug=${encodeURIComponent(slug)}" style="color:var(--pastel-green);font-weight:bold;">#${slug}</a>`);

    // //[id|encTitle|encPoster|year] → carte film/série
    text = text.replace(/\/\/\[((?:tv-)?\d+)\|([^|]*)\|([^|]*)\|([^\]]*)\]/g, (_, id, encTitle, encPoster, year) => {
        const isTv   = id.startsWith('tv-');
        const numId  = isTv ? id.slice(3) : id;
        const href   = `fiche.php?id=${numId}${isTv ? '&type=tv' : ''}`;
        const title  = decodeURIComponent(encTitle);
        const poster = decodeURIComponent(encPoster);
        const img    = poster ? `<img src="${poster}" style="width:26px;height:38px;object-fit:cover;border-radius:4px;flex-shrink:0;">` : '';
        const yr     = year ? ` <span style="color:var(--text-dim);font-weight:400;">(${year})</span>` : '';
        return `<a href="${href}" style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:8px;padding:4px 10px 4px 4px;text-decoration:none;color:var(--text-main);font-weight:700;font-size:0.82rem;vertical-align:middle;">${img}<span>${title}${yr}</span></a>`;
    });

    // /[tv-id:titre] et /[id:titre] → lien amber résolu
    text = text.replace(/\/\[tv-(\d+):([^\]]+)\]/g, (_, id, title) =>
        `<a href="fiche.php?id=${id}&type=tv" style="color:var(--color-amber);font-weight:bold;">/${title}</a>`);
    text = text.replace(/\/\[(\d+):([^\]]+)\]/g, (_, id, title) =>
        `<a href="fiche.php?id=${id}" style="color:var(--color-amber);font-weight:bold;">/${title}</a>`);

    // fallbacks non résolus
    text = text.replace(/(?<!\S)\/\/tv-(\d+)/g, (_, id) =>
        `<a href="fiche.php?id=${id}&type=tv" style="color:var(--color-amber);font-weight:bold;">/Série #${id}</a>`);
    text = text.replace(/(?<!\S)\/\/(\d+)/g, (_, id) =>
        `<a href="fiche.php?id=${id}" style="color:var(--color-amber);font-weight:bold;">/Film #${id}</a>`);
    text = text.replace(/(?<!\S)\/tv-(\d+)/g, (_, id) =>
        `<a href="fiche.php?id=${id}&type=tv" style="color:var(--color-amber);font-weight:bold;">/Série #${id}</a>`);
    text = text.replace(/(?<!\S)\/(\d+)/g, (_, id) =>
        `<a href="fiche.php?id=${id}" style="color:var(--color-amber);font-weight:bold;">/${id}</a>`);

    // Restaure les échappements backslash
    Object.entries(escTokens).forEach(([k, v]) => { text = text.split(k).join(v); });

    return text;
}

function appendMessage(msg, isMe) {
    const chatContainer = document.getElementById('chatMessages');
    const formattedText = formatChatText(msg.text);
    const safeUsername  = String(msg.username).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const safeAvatar    = (msg.avatar || 'assets/default-avatar.png').replace(/"/g, '&quot;');
    const roleShield    = (msg.role === 'admin' || msg.role === 'superadmin')
        ? '<span title="Administrateur" style="color:#f87171;font-size:0.65em;margin-left:3px;vertical-align:middle;">🛡</span>'
        : (msg.role === 'moderator')
            ? '<span title="Modérateur" style="color:#6ee7b7;font-size:0.65em;margin-left:3px;vertical-align:middle;">🛡</span>'
            : '';

    // Bulle "reply preview" (Discord-style)
    let replyBubble = '';
    if (msg.reply_preview) {
        const rUser = String(msg.reply_preview.username).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        const rSnip = String(msg.reply_preview.snippet).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        replyBubble = `
            <div style="font-size:0.68rem;color:var(--text-dim);border-left:2px solid var(--pastel-blue);
                        padding:3px 8px;margin-bottom:4px;border-radius:0 4px 4px 0;
                        background:rgba(167,199,231,0.06);max-width:100%;overflow:hidden;
                        white-space:nowrap;text-overflow:ellipsis;">
                <span style="color:var(--pastel-blue);font-weight:700;">${rUser}</span>
                <span style="margin-left:6px;opacity:0.7;">${rSnip}</span>
            </div>`;
    }

    const wrapper = document.createElement('div');
    wrapper.dataset.msgId = msg.id;
    wrapper.style.cssText = `
        display: flex;
        flex-direction: ${isMe ? 'row-reverse' : 'row'};
        align-items: flex-end;
        gap: 8px;
        margin-bottom: 4px;
    `;

    const isAnomaly = Math.floor(Math.random() * 24) === 0;
    const avatarPage = isAnomaly ? 'anomaly' : 'adn';
    const safeUser = String(msg.username).replace(/'/g, "\\'");
    wrapper.innerHTML = `
        <a href="${avatarPage}?id=${encodeURIComponent(msg.user_id)}"
           style="flex-shrink:0; width:30px; height:30px; border-radius:50%; overflow:hidden; display:block; align-self:flex-end;">
            <img src="${safeAvatar}"
                 onerror="this.src='assets/default-avatar.png'"
                 style="width:100%; height:100%; object-fit:cover;">
        </a>
        <div style="display:flex; flex-direction:column; align-items:${isMe ? 'flex-end' : 'flex-start'}; max-width:75%;">
            <span style="font-size:0.6rem; font-weight:800; color:${isMe ? 'var(--pastel-blue)' : 'var(--text-dim)'}; margin-bottom:3px; padding:0 4px;">
                ${safeUsername}${roleShield}
                <span style="font-weight:400; color:rgba(255,255,255,0.2); font-family:monospace; margin-left:4px;">${msg.time}</span>
            </span>
            <div class="chat-bubble" style="
                background:${isMe ? 'rgba(167,199,231,0.15)' : 'rgba(255,255,255,0.06)'};
                border:1px solid ${isMe ? 'var(--pastel-blue)' : 'var(--border)'};
                padding:8px 12px;
                border-radius:${isMe ? '14px 14px 4px 14px' : '14px 14px 14px 4px'};
                font-size:0.82rem; color:white; line-height:1.5; word-break:break-word;
                position:relative;">
                ${replyBubble}
                ${formattedText}
                <button onclick="replyChatMessage(${msg.id}, '${safeUser}')"
                        title="Répondre"
                        style="position:absolute;${isMe ? 'left:-26px' : 'right:-26px'};top:50%;transform:translateY(-50%);
                               background:none;border:none;color:var(--text-dim);font-size:0.75rem;
                               cursor:pointer;opacity:0;transition:opacity 0.15s;padding:4px;">↩</button>
            </div>
        </div>
    `;

    // Affiche le bouton reply au hover sur la bulle
    const bubble = wrapper.querySelector('.chat-bubble');
    const replyBtn = wrapper.querySelector('.chat-bubble button');
    bubble.addEventListener('mouseenter', () => replyBtn.style.opacity = '1');
    bubble.addEventListener('mouseleave', () => replyBtn.style.opacity = '0');

    chatContainer.appendChild(wrapper);
}

window.replyChatMessage = function(msgId, username) {
    document.getElementById('chatReplyToId').value = msgId;
    document.getElementById('chatReplyLabel').textContent = '↩ ' + username;
    const indicator = document.getElementById('chatReplyIndicator');
    indicator.style.display = 'flex';
    document.getElementById('chatInput').focus();
};

window.cancelChatReply = function() {
    document.getElementById('chatReplyToId').value = '';
    document.getElementById('chatReplyIndicator').style.display = 'none';
    document.getElementById('chatReplyLabel').textContent = '';
};

// Le chat et la recherche sont initialisés par sessions.js
// pour éviter les doublons d'écouteurs sur la page session.php

/* ── NOTIFICATIONS : CLOCHE & PANNEAU ────────────────────────────────────── */
(function () {
    const bellBtn   = document.getElementById('notif-bell-btn');
    const countEl   = document.getElementById('notif-count');
    const panel     = document.getElementById('notif-panel');
    const listEl    = document.getElementById('notif-list');
    if (!bellBtn) return;

    const POLL_MS   = 15000;
    let panelOpen   = false;
    let currentCount = 0;

    function updateCount(n) {
        currentCount = n;
        if (n === 0) {
            countEl.style.display = 'none';
            countEl.textContent   = '';
        } else {
            countEl.textContent   = n > 99 ? '99+' : String(n);
            countEl.style.display = 'flex';
        }
    }

    function poll() {
        fetch('api/api_notifications.php')
            .then(r => r.json())
            .then(d => { if (d.success) updateCount(d.count); })
            .catch(() => {})
            .finally(() => setTimeout(poll, POLL_MS));
    }
    poll();

    function renderItem(n) {
        const div = document.createElement('div');
        div.className = 'notif-item' + (n.is_read ? '' : ' unread');
        div.innerHTML =
            '<span class="notif-item-icon">' + n.icon + '</span>' +
            '<div class="notif-item-body">' +
                '<div class="notif-item-title">' + escN(n.title) + '</div>' +
                '<div class="notif-item-ago">' + escN(n.ago) + '</div>' +
            '</div>' +
            (!n.is_read ? '<span class="notif-unread-dot"></span>' : '');

        div.addEventListener('click', function () {
            if (!n.is_read) {
                fetch('api/api_notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'mark_one', id: n.id })
                })
                .then(r => r.json())
                .then(d => { if (d.success) updateCount(d.count); })
                .catch(() => {});
            }
            closePanel();
            if (n.link) window.location.href = n.link;
        });
        return div;
    }

    function escN(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function loadList() {
        listEl.innerHTML = '<div class="notif-empty">Chargement…</div>';
        fetch('api/api_notifications.php?action=list')
            .then(r => r.json())
            .then(d => {
                updateCount(d.count);
                listEl.innerHTML = '';
                if (!d.notifications || d.notifications.length === 0) {
                    listEl.innerHTML = '<div class="notif-empty">Aucune notification.</div>';
                    return;
                }
                d.notifications.forEach(n => listEl.appendChild(renderItem(n)));
            })
            .catch(() => {
                listEl.innerHTML = '<div class="notif-empty">Erreur de chargement.</div>';
            });
    }

    function openPanel() {
        panelOpen = true;
        panel.style.display = 'block';
        bellBtn.classList.add('active');
        loadList();
    }

    function closePanel() {
        panelOpen = false;
        panel.style.display = 'none';
        bellBtn.classList.remove('active');
    }

    window.toggleNotifPanel = function () {
        panelOpen ? closePanel() : openPanel();
    };

    window.notifMarkAllRead = function () {
        fetch('api/api_notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'mark_all' })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                updateCount(0);
                loadList();
            }
        })
        .catch(() => {});
    };

    document.addEventListener('click', function (e) {
        if (panelOpen && !document.getElementById('notif-bell-wrapper').contains(e.target)) {
            closePanel();
        }
    });
})();

/* ── PAGE MOOD : SÉLECTEUR D'HUMEUR ──────────────────────────────────────── */
(function () {
    if (!document.getElementById('slider-energie')) return;

    const state = { energie: 5, emotion: 5, realite: 5 };
    let debounceTimer = null;

    function updateSliderFill(slider) {
        const pct = ((slider.value - slider.min) / (slider.max - slider.min)) * 100;
        slider.style.background =
            `linear-gradient(to right, var(--pastel-blue) ${pct}%, rgba(255,255,255,0.08) ${pct}%)`;
    }

    document.querySelectorAll('.mood-range').forEach(s => updateSliderFill(s));

    window.onMoodSliderChange = function (key, value) {
        state[key] = parseInt(value, 10);
        document.getElementById('val-' + key).textContent = value;
        updateSliderFill(document.getElementById('slider-' + key));
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchMood, 280);
    };

    const platformToggle = document.getElementById('toggle-my-platforms');
    if (platformToggle) {
        platformToggle.addEventListener('change', () => fetchMood());
    }

    function fetchMood() {
        const loading    = document.getElementById('mood-loading');
        const result     = document.getElementById('mood-result');
        const empty      = document.getElementById('mood-empty');
        const myPlatforms = platformToggle && platformToggle.checked ? '&my_platforms=1' : '';

        loading.style.display = 'block';
        result.innerHTML = '';
        empty.style.display = 'none';

        fetch('api/api_mood.php?energie=' + state.energie + '&emotion=' + state.emotion + '&realite=' + state.realite + myPlatforms)
            .then(r => r.text())
            .then(html => {
                loading.style.display = 'none';
                if (!html.trim()) { empty.style.display = 'block'; return; }
                result.innerHTML = html;
            })
            .catch(() => {
                loading.style.display = 'none';
                empty.style.display = 'block';
            });
    }

    fetchMood();
})();

/* ── PAGE MOOD : ONGLET ANOMALIE ──────────────────────────────────────────── */
(function () {
    const tabs = document.querySelectorAll('.mood-tab-btn');
    if (!tabs.length) return;

    let anomalyLoaded = false;

    tabs.forEach(btn => {
        btn.addEventListener('click', () => {
            tabs.forEach(b => b.classList.toggle('active', b === btn));
            document.querySelectorAll('.mood-tab-panel').forEach(p => {
                p.hidden = p.id !== 'tab-' + btn.dataset.tab;
            });
            if (btn.dataset.tab === 'anomalie' && !anomalyLoaded) {
                anomalyLoaded = true;
                fetchAnomaly();
            }
        });
    });

    function fetchAnomaly() {
        const loading = document.getElementById('anomaly-loading');
        const result  = document.getElementById('anomaly-result');
        const empty   = document.getElementById('anomaly-empty');
        if (!loading) return;
        loading.style.display = 'block';

        fetch('api/api_anomaly_mood.php')
            .then(r => r.text())
            .then(html => {
                loading.style.display = 'none';
                if (!html.trim()) { empty.style.display = 'block'; return; }
                result.innerHTML = html;
            })
            .catch(() => {
                loading.style.display = 'none';
                empty.style.display = 'block';
            });
    }
})();

// ── KONAMI CODE — corruption totale ────────────────────────────────────────
(function () {
    const SEQUENCE = ['ArrowUp','ArrowUp','ArrowDown','ArrowDown','ArrowLeft','ArrowRight','ArrowLeft','ArrowRight'];
    let idx = 0;
    let active = false;

    document.addEventListener('keydown', function (e) {
        if (e.key === SEQUENCE[idx]) {
            idx++;
            if (idx === SEQUENCE.length) {
                idx = 0;
                if (!active) triggerKonami();
            }
        } else {
            idx = e.key === SEQUENCE[0] ? 1 : 0;
        }
    });

    function triggerKonami() {
        active = true;

        // Overlay
        const overlay = document.createElement('div');
        overlay.id = 'konami-overlay';
        document.body.appendChild(overlay);
        document.body.classList.add('konami-mode');

        // Badge si connecté
        fetch('api/api_konami.php')
            .then(r => r.json())
            .then(data => {
                if (data.badge) {
                    const badge = document.createElement('div');
                    badge.id = 'konami-badge';
                    badge.textContent = '⬆⬆⬇⬇ BADGE DÉBLOQUÉ · ANOMALIE DÉTECTÉE';
                    document.body.appendChild(badge);
                    setTimeout(() => badge.remove(), 3000);
                }
            })
            .catch(() => {});

        // Fin après 3s
        setTimeout(() => {
            overlay.remove();
            document.body.classList.remove('konami-mode');
            active = false;
        }, 3000);
    }
})();

// ── BURGER MENU MOBILE ─────────────────────────────────────────────────────
// Géré par script.js via DOMContentLoaded pour éviter les conflits d'exécution