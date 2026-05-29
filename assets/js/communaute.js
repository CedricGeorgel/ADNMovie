let searchTimer = null;
let activeTab   = 'active';

// ── Icônes SVG ────────────────────────────────────────────────────────────────

const SVG = {
    amis: `<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 442.666 442.666" fill="var(--pastel-blue)">
        <path d="M230.729,340.323l-144.152.197c-17.95-2.755-28.38-18.263-27.346-36.097,2.218-38.222,37.067-63.929,72.938-69.017,13.023-1.847,25.897-1.753,39.237-.833,17.537,1.21,34.179,6.851,48.808,16.164,23.742,15.115,39.794,41.588,32.385,70.207-2.485,9.596-10.552,19.364-21.871,19.379Z"/>
        <path d="M354.568,340.065l-98.807.414c18.12-16.761,23.5-41.348,14.311-63.848-5.57-13.641-16.308-23.722-29.598-30.832,14.227-9.213,29.571-11.529,45.726-11.845,24.431-.479,47.839,5.535,67.345,20.118,16.658,11.867,27.815,28.852,29.795,49.459,1.672,17.397-9.561,36.454-28.772,36.535Z"/>
        <path d="M164.994,206.594c-16.752,2.38-31.876-2.644-43.452-13.322-11.083-10.223-17.061-25.615-16.286-41.597,1.511-31.175,29.16-53.339,60.275-48.983,19.691,2.756,36.954,16.293,42.974,35.659,9.593,30.864-11.087,63.636-43.511,68.243Z"/>
        <path d="M293.792,206.539c-17.415,2.601-32.769-3.172-44.451-15.113-13.432-13.731-18.711-32.959-12.944-52.477,4.679-15.835,18.063-30.036,36.166-34.918,26.335-7.101,54.837,7.106,63.833,33.329,10.599,30.894-9.995,64.307-42.604,69.178Z"/>
    </svg>`,
    commentaires: `<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 442.666 442.666" fill="var(--pastel-blue)">
        <path d="M193.347,352.051c-5.486,3.724-12.376,3.074-17.983.227-3.961-2.012-8.49-8.134-8.507-14.125l-.113-38.926-20.098-.23c-38.418-.439-70.99-30.918-72.294-69.942-.803-24.024-1.142-46.428.1-70.919,1.831-36.121,32.151-70.121,69.285-70.101l154.768.083c39.002.021,70.473,35.443,70.492,73.006l.034,65.842c.021,39.23-32.211,71.374-71.304,71.965l-26.724.404-77.657,52.716Z"/>
    </svg>`,
    analyses: `<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 442.666 442.666" fill="var(--pastel-blue)">
        <path d="M317.824,349.076c-.036,8.913-10.045,13.285-17.107,12.355-7.826-1.031-13.332-7.491-13.436-15.933l-.156-12.755-131.125.031c-.041,5.983.038,10.66-.254,15.529-.515,8.601-8.337,13.621-16.075,13.243-7.807-.382-14.84-6.93-14.877-15.693l-.189-43.711c1.092-21.715,11.026-41.877,29.184-54.096l40.732-27.41c-15.605-10.057-30.84-19.049-44.867-30.271-15.651-12.52-24.392-30.487-24.895-50.22-.375-14.716-.327-28.8.039-43.444.218-8.703,7.066-15.11,15.003-15.573,8.201-.479,15.955,5.73,16.083,14.695l.241,16.938,131.017-.003.134-16.956c.067-8.522,7.55-14.545,15.356-14.665,7.703-.118,15.173,6.053,15.267,14.624.17,15.528.564,30.385-.133,46.031-.898,20.163-11.913,37.899-27.978,49.641-13.96,10.203-27.738,18.812-43.036,28.756l41.132,27.357c17.306,11.511,27.928,29.312,30.147,49.95l-.209,51.581ZM263.627,166.574c14.335-.051,24.014-12.728,23.764-25.83l-131.396-.052c-.99,15.051,10.299,26.225,25.15,26.173l82.482-.291ZM232.2,196.984l-23.629-.44,12.594,8.002,11.035-7.562ZM237.343,248.401l-17.489-10.856-16.037,10.769,33.526.087ZM287.611,302.942c.387-12.633-6.807-23.299-18.767-26.324l-91.676.224c-13.691.034-22.296,13.544-21.442,26.108l131.885-.008Z"/>
    </svg>`,
    archives: `<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 442.666 442.666" fill="var(--pastel-blue)">
        <path d="M342.805,349.837c-.004,11.387-9.791,22.249-21.007,22.25l-200.557.006c-14.186,0-21.447-13.353-21.445-25.896l.039-250.478c.002-13.39,7.669-23.582,21.443-25.147l149.592.078c9.643.005,18.666,4.222,25.21,10.784l34.876,34.972c7.698,7.719,10.81,17.451,11.913,28.335l-.064,205.096ZM288.93,165.894c-23.463-3.673-40.446-20.855-45.073-43.365l-.503-22.994-116.126.027.002,244.745,188.67.019.012-177.559c-9.033-.188-17.774.569-26.982-.873Z"/>
        <path d="M278.278,225.169l-115.163-.017c-7.462-.001-11.545-8.301-11.622-14.263-.078-6.077,4.288-14.211,11.85-14.219l114.582-.124c7.581-.008,12.706,7.079,13.102,13.465.408,6.572-4.316,15.16-12.749,15.159Z"/>
        <path d="M245.414,282.204l-82.316-.1c-7.041-.009-11.367-7.68-11.588-13.508-.201-5.306,3.862-13.938,10.71-13.942l84.677-.05c7.605-.005,11.792,8.044,11.466,14.23-.332,6.296-4.862,13.38-12.949,13.37Z"/>
        <path d="M222.137,166.956c-20.008.959-39.358.612-59.049.278-7.048-.12-11.264-7.56-11.53-13.534s4.275-14.046,11.552-14.07l55.899-.186c7.325-.024,12.259,5.542,13.343,12.34.848,5.318-2.199,14.789-10.214,15.173Z"/>
    </svg>`,
};

// ── Rendu d'une carte membre ──────────────────────────────────────────────────

function renderMemberCard(m) {
    const avatar  = m.avatar || 'assets/default-avatar.png';
    const showBtn = IS_LOGGED && m.friend_status !== 'self' && m.friend_status !== 'accepted';
    const btnHtml = showBtn ? renderFriendBtn(m.id, m.friend_status) : '';
    const bannerBg = m.backdrop
        ? `<div style="position:absolute;inset:0;background-image:url('${m.backdrop}');background-size:cover;background-position:center;filter:blur(14px);opacity:0.18;transform:scale(1.12);pointer-events:none;"></div>`
        : '';

    return `
    <div class="member-card" data-id="${m.id}" style="position:relative;overflow:hidden;">
        ${bannerBg}
        <a href="adn.php?id=${encodeURIComponent(m.id)}" class="member-card-link" style="position:relative;">
            <img src="${avatar}" onerror="this.src='assets/default-avatar.png'" class="member-card-avatar" alt="${m.username}">
            <div class="member-card-body">
                <div class="member-card-name">${escHtml(m.username)}</div>
                <div class="member-card-stats">
                    <span title="Liens">${SVG.amis} ${m.friend_count}</span>
                    <span title="Analyses">${SVG.analyses} ${m.rating_count}</span>
                    <span title="Commentaires">${SVG.commentaires} ${m.comment_count}</span>
                    <span title="Archives">${SVG.archives} ${m.content_count}</span>
                </div>
            </div>
        </a>
        ${btnHtml ? `<div style="position:relative;">${btnHtml}</div>` : ''}
    </div>`;
}

function renderFriendBtn(targetId, status) {
    const configs = {
        none:             { label: 'Tisser un lien',   cls: 'btn-base active',  action: 'send'    },
        pending_sent:     { label: 'Demande envoyée',  cls: 'btn-base',         action: null      },
        pending_received: { label: 'Accepter',         cls: 'btn-base active',  action: 'accept'  },
        accepted:         { label: 'Lien établi ✓',    cls: 'btn-base',         action: 'remove'  },
    };
    const c = configs[status] || configs.none;
    const disabled = !c.action ? 'disabled' : '';
    return `<button class="${c.cls} member-card-btn" data-id="${targetId}" data-action="${c.action || ''}" ${disabled}>${c.label}</button>`;
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Chargement ────────────────────────────────────────────────────────────────

async function loadActive() {
    const grid = document.getElementById('communityGrid');
    grid.innerHTML = '<p style="color:var(--text-dim);font-size:0.8rem;text-align:center;padding:40px;">Chargement…</p>';

    const res  = await fetch('api/api_communaute.php?mode=active');
    const data = await res.json();

    if (!data.success || !data.members.length) {
        grid.innerHTML = '<p style="color:var(--text-dim);font-size:0.8rem;text-align:center;padding:40px;">Aucun spécimen actif.</p>';
        return;
    }
    grid.innerHTML = data.members.map(renderMemberCard).join('');
}

function getOrCreateSearchSection() {
    let section = document.getElementById('searchResultsSection');
    if (!section) {
        section = document.createElement('div');
        section.id = 'searchResultsSection';
        const label = document.querySelector('.communaute-section-label');
        label.parentNode.insertBefore(section, label);
    }
    return section;
}

async function search(q) {
    const section = getOrCreateSearchSection();
    if (q.length < 2) {
        section.innerHTML = '';
        return;
    }

    const res  = await fetch(`api/api_communaute.php?mode=search&q=${encodeURIComponent(q)}`);
    const data = await res.json();

    if (!data.success || !data.members.length) {
        section.innerHTML = '<p style="color:var(--text-dim);font-size:0.8rem;text-align:center;padding:20px 0;">Aucun spécimen trouvé.</p>';
        return;
    }
    section.innerHTML = `
        <p class="communaute-section-label" style="margin-top:0;">Résultats</p>
        <div class="community-grid">${data.members.map(renderMemberCard).join('')}</div>
    `;
}


// ── Bouton ami (délégation) ───────────────────────────────────────────────────

document.getElementById('communityGrid').addEventListener('click', async (e) => {
    const btn = e.target.closest('.member-card-btn');
    if (!btn || btn.disabled || !btn.dataset.action) return;

    const targetId = btn.dataset.id;
    const action   = btn.dataset.action;

    btn.disabled = true;
    btn.textContent = '…';

    const fd = new FormData();
    fd.append('action', action);
    fd.append('target_id', targetId);

    const res  = await fetch('api/api_friends.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.ok || action === 'remove' || action === 'decline') {
        // Remplace le bouton selon le nouveau statut
        btn.outerHTML = renderFriendBtn(targetId, data.status);
    } else {
        btn.disabled = false;
        btn.textContent = 'Réessayer';
    }
});

// ── Search debounce ───────────────────────────────────────────────────────────

document.getElementById('searchInput').addEventListener('input', (e) => {
    clearTimeout(searchTimer);
    const q = e.target.value.trim();
    searchTimer = setTimeout(() => q.length >= 2 ? search(q) : loadActive(), 300);
});

// ── Init ──────────────────────────────────────────────────────────────────────
loadActive();

// ── ADN Match : synchronisation ───────────────────────────────────────────────

function dnaRevealMutualDOM(card, user) {
    const pct        = card.dataset.matchPct || '';
    const profileUrl = `adn.php?id=${encodeURIComponent(user.id)}`;
    const avatarSrc  = user.avatar || 'assets/default-avatar.png';

    card.querySelector('.dna-card-header').innerHTML = `
        <div class="dna-card-user">
            <a href="${profileUrl}">
                <img src="${escHtml(avatarSrc)}" onerror="this.src='assets/default-avatar.png'" class="dna-card-avatar">
            </a>
            <div class="dna-card-info">
                <a href="${profileUrl}" class="dna-card-username">${escHtml(user.username)}</a>
                <div class="dna-card-resonance">Lien génomique tissé · ${pct}% de résonance</div>
            </div>
        </div>`;

    card.querySelector('.dna-card-cta').innerHTML =
        `<div class="dna-card-established">Résonance ADN établie</div>`;
}

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.dna-sync-btn');
    if (!btn) return;

    const matchId = btn.dataset.matchId;
    const state   = btn.dataset.state;
    const dnaType = btn.dataset.dnaType || 'movie';
    const action  = (state === 'pending') ? 'cancel' : 'reveal';

    btn.disabled = true;
    btn.textContent = '…';

    const fd = new FormData();
    fd.append('action', action);
    fd.append('match_user_id', matchId);
    fd.append('dna_type', dnaType);

    let data;
    try {
        const res = await fetch('api/api_dna_match.php', { method: 'POST', body: fd });
        data = await res.json();
    } catch (err) {
        btn.disabled = false;
        btn.textContent = state === 'pending' ? 'Annuler la synchronisation' : 'Synchroniser les ADN';
        return;
    }

    if (!data.success) {
        btn.disabled = false;
        btn.textContent = state === 'pending' ? 'Annuler la synchronisation' : 'Synchroniser les ADN';
        return;
    }

    if (action === 'cancel') {
        btn.closest('.dna-card-cta').innerHTML =
            `<button class="btn-dna-sync dna-sync-btn" data-match-id="${matchId}" data-state="none">Synchroniser les ADN</button>`;
        return;
    }

    if (data.mutual && data.user && data.user.id) {
        dnaRevealMutualDOM(btn.closest('.dna-match-card'), data.user);
    } else if (!data.mutual) {
        btn.closest('.dna-card-cta').innerHTML =
            `<button class="btn-dna-cancel dna-sync-btn" data-match-id="${matchId}" data-state="pending">Annuler la synchronisation</button>`;
    }
});

// ── Auto-refresh quand l'utilisateur revient sur l'onglet ─────────────────────
(function () {
    const loadedAt = Date.now();
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') return;
        if (Date.now() - loadedAt < 20000) return;
        if (document.querySelector('.dna-sync-btn')) location.reload();
    });
})();
