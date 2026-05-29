let searchTimeout  = null;
let previewTimeout = null;
let previewDirty   = true;

function switchTab(tab) {
    const isPreview = tab === 'preview';

    document.getElementById('paneWrite').style.display   = isPreview ? 'none' : '';
    document.getElementById('panePreview').style.display = isPreview ? '' : 'none';

    document.getElementById('tabWrite').style.background   = isPreview ? 'transparent' : 'rgba(255,255,255,0.08)';
    document.getElementById('tabWrite').style.color        = isPreview ? 'var(--text-dim)' : 'var(--text-main)';
    document.getElementById('tabPreview').style.background = isPreview ? 'rgba(255,255,255,0.08)' : 'transparent';
    document.getElementById('tabPreview').style.color      = isPreview ? 'var(--text-main)' : 'var(--text-dim)';

    if (isPreview && previewDirty) renderPreview();
}

async function renderPreview() {
    const body = document.getElementById('inputBody').value;
    const box  = document.getElementById('previewContent');

    if (!body.trim()) {
        box.innerHTML = '<span style="color:var(--text-dim);font-style:italic;font-size:0.8rem;">Rien à afficher.</span>';
        previewDirty = false;
        return;
    }

    box.innerHTML = '<span style="color:var(--text-dim);font-style:italic;font-size:0.8rem;">Rendu en cours…</span>';

    const fd = new FormData();
    fd.append('body', body);
    const res  = await fetch('api/api_preview_md.php', { method: 'POST', body: fd });
    const data = await res.json();
    box.innerHTML = data.html || '<span style="color:var(--text-dim);font-style:italic;">Vide.</span>';
    previewDirty = false;
}

document.getElementById('inputBody').addEventListener('input', () => {
    previewDirty = true;
});

function setType(type) {
    document.getElementById('selectedType').value = type;
    document.querySelectorAll('.type-btn').forEach(b => b.classList.toggle('active', b.dataset.type === type));
    document.getElementById('itemsCard').style.display = type === 'list' ? '' : 'none';
}

document.getElementById('filmSearch').addEventListener('input', function () {
    clearTimeout(searchTimeout);
    const q = this.value.trim();
    if (q.length < 2) { hideSuggestions(); return; }
    searchTimeout = setTimeout(() => searchFilms(q), 300);
});
document.addEventListener('click', e => { if (!e.target.closest('.film-search-wrap')) hideSuggestions(); });

async function searchFilms(q) {
    const res  = await fetch(`api/api_search.php?q=${encodeURIComponent(q)}&local=1`);
    const data = await res.json();
    const box  = document.getElementById('filmSuggestions');
    if (!data.results?.length) { hideSuggestions(); return; }
    box.innerHTML = data.results.slice(0, 8).map(m => `
        <div class="film-suggestion-item" onclick="addItem(${m.id},'${escJs(m.title)}','${escJs(m.poster || '')}','${escJs(m.year || '')}')">
            <img src="${m.poster || 'assets/default-avatar.png'}" onerror="this.src='assets/default-avatar.png'">
            <span style="font-size:0.8rem;">${m.title} <span style="color:var(--text-dim);">${m.year || ''}</span></span>
        </div>`).join('');
    box.style.display = 'block';
}

function hideSuggestions() {
    document.getElementById('filmSuggestions').style.display = 'none';
}

function addItem(movieId, title, poster, year) {
    hideSuggestions();
    document.getElementById('filmSearch').value = '';
    if (document.querySelector(`.item-row[data-movie-id="${movieId}"]`)) return;

    const list = document.getElementById('itemsList');
    const pos  = list.children.length;
    const row  = document.createElement('div');
    row.className = 'item-row';
    row.dataset.movieId = movieId;
    row.dataset.pos = pos;
    row.innerHTML = `
        <span class="item-handle">⠿</span>
        <span class="item-rank">${pos + 1}</span>
        <div class="item-poster"><img src="${poster}" onerror="this.src='assets/default-avatar.png'"></div>
        <div class="item-info">
            <div class="item-title">${escHtml(title)} <span style="color:var(--text-dim);font-weight:400;">${escHtml(year)}</span></div>
            <textarea class="item-note-input" rows="2" placeholder="Commentaire optionnel…"></textarea>
        </div>
        <button class="item-remove" onclick="removeItem(this)" title="Retirer">✕</button>`;
    list.appendChild(row);
    rerank();
}

function removeItem(btn) { btn.closest('.item-row').remove(); rerank(); }

function rerank() {
    document.querySelectorAll('#itemsList .item-row').forEach((row, i) => {
        row.querySelector('.item-rank').textContent = i + 1;
    });
}

async function saveContent() {
    const title = document.getElementById('inputTitle').value.trim();
    const type  = document.getElementById('selectedType').value;
    if (!title) { showError('Le titre est obligatoire.'); return; }

    const items = [];
    document.querySelectorAll('#itemsList .item-row').forEach((row, i) => {
        items.push({
            movie_id: parseInt(row.dataset.movieId),
            position: i,
            note: row.querySelector('.item-note-input')?.value.trim() || ''
        });
    });

    const fd = new FormData();
    fd.append('type',      type);
    fd.append('title',     title);
    fd.append('body',      document.getElementById('inputBody').value);
    fd.append('is_public', document.getElementById('togglePublic').classList.contains('on') ? 1 : 0);
    fd.append('items',     JSON.stringify(items));
    if (EDIT_ID) fd.append('id', EDIT_ID);

    const res  = await fetch('api/api_content_save.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        window.location.href = 'content.php?slug=' + encodeURIComponent(data.slug);
    } else {
        showError(data.message || 'Erreur lors de la sauvegarde.');
    }
}

function showError(msg) {
    const el = document.getElementById('saveError');
    el.textContent = msg;
    el.style.display = 'block';
}

function escJs(s)   { return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
