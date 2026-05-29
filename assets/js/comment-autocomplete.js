/**
 * ASSETS/JS/COMMENT-AUTOCOMPLETE.JS
 * Widget d'autocomplétion pour les champs de commentaire/chat.
 *
 * Triggers reconnus :
 *   #<query>    → archives  (insère #slug)
 *   //<query>   → films/séries en carte  (insère //id ou //tv-id)
 *   /<query>    → films/séries en lien   (insère /id ou /tv-id)
 *   @<query>    → utilisateurs
 *
 * Data attrs requis sur l'input/textarea :
 *   data-ac-context : "fiche" | "archive" | "session"
 *   data-ac-ref     : refId ou contentId (contextes fiche/archive)
 *   data-ac-room    : roomId (contexte session, fallback sur body.data-room-id)
 */

class CommentAutocomplete {
    constructor(el) {
        this.el  = el;
        this.ctx = {
            context: el.dataset.acContext || 'fiche',
            ref:     el.dataset.acRef     || '',
            room:    el.dataset.acRoom    || document.body.dataset.roomId || '',
        };

        this._dropdown     = this._buildDropdown();
        document.body.appendChild(this._dropdown);

        this._items        = [];
        this._active       = -1;
        this._timer        = null;
        this._triggerStart = 0;
        this._trigger      = null;

        el.addEventListener('input',   () => this._onInput());
        el.addEventListener('keydown', (e) => this._onKeydown(e));
        el.addEventListener('blur',    () => setTimeout(() => this._hide(), 160));
        window.addEventListener('scroll', () => { if (this._isVisible()) this._position(); }, { passive: true });
        window.addEventListener('resize', () => { if (this._isVisible()) this._position(); }, { passive: true });
    }

    _buildDropdown() {
        const d = document.createElement('div');
        d.className = 'ac-dropdown';
        d.style.cssText = [
            'position:absolute', 'z-index:9999',
            'background:var(--surface-elevated,#1a1a1a)',
            'border:1px solid var(--border,#333)',
            'border-radius:10px',
            'box-shadow:0 8px 32px rgba(0,0,0,0.6)',
            'max-height:240px', 'overflow-y:auto',
            'display:none', 'min-width:240px',
        ].join(';');
        return d;
    }

    _isVisible() {
        return this._dropdown.style.display !== 'none';
    }

    // ── Détection du trigger ────────────────────────────────────────────────────

    _detect() {
        const cursor = this.el.selectionStart ?? this.el.value.length;
        const before = this.el.value.substring(0, cursor);

        // # archive (précédé d'un espace ou début)
        let m = before.match(/(?<!\S)#([\w-]{0,60})$/);
        if (m) return { trigger: '#', query: m[1], start: cursor - m[0].length };

        // // carte (avant / simple)
        m = before.match(/(?<!\S)\/\/([^\n]{0,60})$/);
        if (m) return { trigger: '//', query: m[1].trimStart(), start: cursor - m[0].length };

        // / lien (pas suivi d'un autre /)
        m = before.match(/(?<!\S)\/(?!\/)([^\n]{0,60})$/);
        if (m) return { trigger: '/', query: m[1].trimStart(), start: cursor - m[0].length };

        // @
        m = before.match(/@([\w]{0,30})$/);
        if (m) return { trigger: '@', query: m[1], start: cursor - m[0].length };

        return null;
    }

    // ── Événements ──────────────────────────────────────────────────────────────

    _onInput() {
        const state = this._detect();
        if (!state) { this._hide(); return; }

        const minLen = (state.trigger === '@' || state.trigger === '#') ? 1 : 2;
        if (state.query.length < minLen) { this._hide(); return; }

        this._trigger      = state.trigger;
        this._triggerStart = state.start;

        clearTimeout(this._timer);
        this._timer = setTimeout(() => this._fetch(state.query), 240);
    }

    _onKeydown(e) {
        if (!this._isVisible()) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this._setActive(Math.min(this._active + 1, this._items.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this._setActive(Math.max(this._active - 1, 0));
        } else if (e.key === 'Enter' && this._active >= 0) {
            e.preventDefault();
            this._select(this._active);
        } else if (e.key === 'Escape') {
            this._hide();
        }
    }

    // ── Fetch ───────────────────────────────────────────────────────────────────

    _fetch(query) {
        let url;
        if (this._trigger === '#') {
            url = `api/api_search_archives.php?q=${encodeURIComponent(query)}`;
        } else if (this._trigger === '/' || this._trigger === '//') {
            url = `api/api_search_movies.php?q=${encodeURIComponent(query)}`;
        } else {
            const p = new URLSearchParams({ q: query, context: this.ctx.context });
            if (this.ctx.context === 'session') p.set('room_id', this.ctx.room);
            else p.set('ref_id', this.ctx.ref);
            url = `api/api_search_users.php?${p}`;
        }

        fetch(url)
            .then(r => r.ok ? r.json() : [])
            .then(data => this._show(data))
            .catch(() => this._hide());
    }

    // ── Affichage ───────────────────────────────────────────────────────────────

    _show(items) {
        if (!items || items.length === 0) { this._hide(); return; }
        this._items  = items;
        this._active = -1;

        const triggerColor = this._trigger === '#'
            ? 'var(--pastel-green,#6ee7b7)'
            : (this._trigger === '/' || this._trigger === '//')
                ? 'var(--color-amber,#f59e0b)'
                : 'var(--pastel-blue,#93c5fd)';

        this._dropdown.innerHTML = items.map((item, i) => {
            let label, sub, icon;

            if (this._trigger === '#') {
                label = item.title;
                sub   = item.slug;
                icon  = `<span style="font-size:0.6rem;font-weight:800;color:${triggerColor};flex-shrink:0;">#</span>`;
            } else if (this._trigger === '/' || this._trigger === '//') {
                label = item.title;
                sub   = item.type === 'tv' ? 'TV #' + item.id : '#' + item.id;
                icon  = `<span style="font-size:0.6rem;font-weight:800;color:${triggerColor};flex-shrink:0;">${item.type === 'tv' ? '📺' : '🎬'}</span>`;
            } else {
                label = item.username;
                sub   = '';
                icon  = item.avatar
                    ? `<img src="${this._esc(item.avatar)}" onerror="this.src='assets/default-avatar.png'"
                            style="width:24px;height:24px;border-radius:50%;object-fit:cover;flex-shrink:0;">`
                    : `<span style="font-size:0.6rem;font-weight:800;color:${triggerColor};flex-shrink:0;">@</span>`;
            }

            return `<div class="ac-item" data-idx="${i}"
                style="display:flex;align-items:center;gap:10px;padding:8px 14px;
                       cursor:pointer;font-size:0.82rem;
                       border-bottom:1px solid rgba(255,255,255,0.04);
                       transition:background 0.1s;">
                ${icon}
                <span style="flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    ${this._esc(label)}
                </span>
                ${sub ? `<span style="font-size:0.6rem;color:var(--text-dim,#666);flex-shrink:0;font-family:monospace;">${this._esc(sub)}</span>` : ''}
            </div>`;
        }).join('');

        this._dropdown.querySelectorAll('.ac-item').forEach(el => {
            el.addEventListener('mouseenter', () => this._setActive(+el.dataset.idx));
            el.addEventListener('click',      () => this._select(+el.dataset.idx));
        });

        this._position();
        this._dropdown.style.display = 'block';
    }

    _position() {
        const rect  = this.el.getBoundingClientRect();
        const scrollY = window.scrollY;
        const scrollX = window.scrollX;
        this._dropdown.style.top   = (rect.bottom + scrollY + 4) + 'px';
        this._dropdown.style.left  = (rect.left + scrollX) + 'px';
        this._dropdown.style.width = Math.max(rect.width, 260) + 'px';
    }

    _hide() {
        this._dropdown.style.display = 'none';
        this._items  = [];
        this._active = -1;
    }

    _setActive(idx) {
        this._active = idx;
        this._dropdown.querySelectorAll('.ac-item').forEach((el, i) => {
            el.style.background = i === idx ? 'rgba(255,255,255,0.07)' : '';
        });
    }

    // ── Sélection ───────────────────────────────────────────────────────────────

    _select(idx) {
        const item = this._items[idx];
        if (!item) return;

        let insert;
        if (this._trigger === '#') {
            insert = '#' + item.slug + ' ';
        } else if (this._trigger === '//') {
            insert = item.type === 'tv' ? '//tv-' + item.id + ' ' : '//' + item.id + ' ';
        } else if (this._trigger === '/') {
            insert = item.type === 'tv' ? '/tv-' + item.id + ' ' : '/' + item.id + ' ';
        } else {
            insert = '@' + item.username + ' ';
        }

        const val    = this.el.value;
        const cursor = this.el.selectionStart ?? val.length;
        const before = val.substring(0, this._triggerStart);
        const after  = val.substring(cursor);

        this.el.value = before + insert + after;
        const pos = before.length + insert.length;
        this.el.setSelectionRange(pos, pos);
        this.el.focus();
        this._hide();
    }

    _esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
}

// ── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ac-context]').forEach(el => {
        new CommentAutocomplete(el);
    });
});
