function switchModoTab(tab, btn) {
    document.querySelectorAll('.modo-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.modo-tab').forEach(b => b.classList.remove('active'));
    const sec = document.getElementById('section-' + tab);
    if (sec) sec.classList.add('active');
    if (btn) btn.classList.add('active');
    if (tab === 'mots') loadWords();
    if (tab === 'chat') initStaffChat();
}

function loadWords() {
    fetch('api/api_censored_words.php')
        .then(r => r.json())
        .then(res => {
            if (res.success) renderWords(res.words);
        })
        .catch(err => console.error('loadWords:', err));
}

function renderWords(words) {
    const container = document.getElementById('wordsList');
    const counter   = document.getElementById('word-count');
    if (!container) return;
    if (counter) counter.textContent = words.length + ' mot' + (words.length > 1 ? 's' : '');
    if (!words.length) {
        container.innerHTML = '<span style="color:var(--text-dim);font-size:0.8rem;">Aucun mot configuré.</span>';
        return;
    }
    container.innerHTML = words.map(w => `
        <span style="display:inline-flex;align-items:center;gap:6px;background:rgba(248,113,113,0.08);
                     border:1px solid rgba(248,113,113,0.2);border-radius:20px;
                     padding:4px 12px;font-size:0.78rem;font-family:monospace;">
            ${w.replace(/&/g,'&amp;').replace(/</g,'&lt;')}
            <button onclick="removeWord('${w.replace(/'/g,"\\'")}', this)"
                    style="background:none;border:none;color:#f87171;cursor:pointer;
                           font-size:0.8rem;line-height:1;padding:0;opacity:0.7;"
                    onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.7">✕</button>
        </span>
    `).join('');
}

function addWord() {
    const input = document.getElementById('newWordInput');
    const word  = input ? input.value.trim() : '';
    if (!word) return;
    fetch('api/api_censored_words.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'add', word })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            renderWords(res.words);
            if (input) input.value = '';
        } else {
            alert(res.message || 'Erreur.');
        }
    })
    .catch(err => console.error('addWord:', err));
}

function removeWord(word, btn) {
    fetch('api/api_censored_words.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'remove', word })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) renderWords(res.words);
        else alert(res.message || 'Erreur.');
    })
    .catch(err => console.error('removeWord:', err));
}

function deleteComment(id, btn) {
    if (!confirm('Supprimer définitivement ce commentaire ?')) return;
    fetch('api/api_delete_comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ comment_id: id })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const row = btn.closest('tr');
            if (row) row.style.opacity = '0.3';
            btn.textContent = '✓ Purgé';
            btn.disabled = true;
        } else {
            alert('Erreur : ' + (res.message || 'Impossible de supprimer.'));
        }
    })
    .catch(err => console.error(err));
}

function dismissSignal(id, btn) {
    fetch('api/api_roadmap.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_management', id, field: 'status', value: 'done' })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            btn.textContent = '✓ Traité';
            btn.disabled = true;
            btn.classList.add('done');
            const row = btn.closest('tr');
            if (row) row.style.opacity = '0.4';
        } else {
            alert(res.message || 'Erreur.');
        }
    })
    .catch(err => console.error('dismissSignal:', err));
}

function deleteSignal(id, btn) {
    if (!confirm('Purger ce signalement définitivement ?')) return;
    fetch('api/api_roadmap.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_management', id })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const row = btn.closest('tr');
            if (row) row.remove();
        } else {
            alert(res.message || 'Erreur.');
        }
    })
    .catch(err => console.error('deleteSignal:', err));
}

function flagUser(userId, flagValue, btn) {
    const label = flagValue ? 'Signaler cet utilisateur ?' : 'Retirer le signalement ?';
    if (!confirm(label)) return;
    fetch('api/api_flag_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ target_id: userId, flagged: flagValue })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            btn.classList.add('done');
            btn.textContent = res.flagged ? '✓ Signalé' : '✓ Retiré';
            btn.disabled = true;
            if (!res.flagged) {
                const row = btn.closest('tr');
                if (row) row.style.opacity = '0.4';
            }
        } else {
            alert('Erreur : ' + (res.message || 'Opération échouée.'));
        }
    })
    .catch(err => console.error(err));
}

/* ── CHAT STAFF ────────────────────────────────────────────────────────────── */

let _chatLastId    = 0;
let _chatPollTimer = null;
let _chatReady     = false;

const ROLE_COLORS = { superadmin: '#f87171', admin: '#f87171', moderator: '#6ee7b7', user: '#B8A7E7' };

function initStaffChat() {
    if (_chatReady) return;
    _chatReady = true;
    staffChatLoad(true);
    _chatPollTimer = setInterval(() => staffChatLoad(false), 3000);
    document.getElementById('staff-chat-input')?.focus();
}

function staffChatLoad(initial) {
    fetch('/api/api_staff_chat_get.php?last_id=' + _chatLastId)
        .then(r => r.json())
        .then(res => {
            if (!res.success || !res.messages.length) {
                if (initial) {
                    const log = document.getElementById('staff-chat-log');
                    if (log) log.innerHTML = '<p style="color:var(--text-dim);font-size:0.75rem;text-align:center;margin:auto;">Aucun message pour le moment.</p>';
                }
                return;
            }
            const log = document.getElementById('staff-chat-log');
            if (!log) return;
            if (initial) log.innerHTML = '';
            const atBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 60;
            res.messages.forEach(m => {
                _chatLastId = Math.max(_chatLastId, m.id);
                log.appendChild(staffChatBuildMsg(m));
            });
            if (initial || atBottom) log.scrollTop = log.scrollHeight;
        })
        .catch(() => {});
}

function staffChatBuildMsg(m) {
    const isSelf = m.user_id === (window._staffCurrentUserId ?? '');
    // Adapte le format pour buildChatBubble (texte brut → pas de formatChatText)
    const msgAdapted = {
        ...m,
        text: m.text,
        reply_preview: m.reply_preview || null,
    };
    return window.buildChatBubble(msgAdapted, isSelf, {
        replyInputId:     'staff-chat-reply-id',
        replyLabelId:     'staff-chat-reply-label',
        replyIndicatorId: 'staff-chat-reply-indicator',
        chatInputId:      'staff-chat-input',
        formatFn:         s => escHtml(s),
    });
}

function staffChatSend() {
    const input = document.getElementById('staff-chat-input');
    if (!input) return;
    const text = input.value.trim();
    if (!text) return;
    const replyId = document.getElementById('staff-chat-reply-id')?.value || '';
    input.value = '';
    input.disabled = true;
    staffChatCancelReply();
    const fd = new FormData();
    fd.append('message', text);
    if (replyId) fd.append('reply_to_id', replyId);
    fetch('/api/api_staff_chat_post.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(() => { input.disabled = false; input.focus(); staffChatLoad(false); })
        .catch(() => { input.disabled = false; });
}

function staffChatReply(msgId, username) {
    document.getElementById('staff-chat-reply-id').value = msgId;
    document.getElementById('staff-chat-reply-label').textContent = '↩ ' + username;
    document.getElementById('staff-chat-reply-indicator').style.display = 'flex';
    document.getElementById('staff-chat-input').focus();
}

function staffChatCancelReply() {
    document.getElementById('staff-chat-reply-id').value = '';
    document.getElementById('staff-chat-reply-indicator').style.display = 'none';
    document.getElementById('staff-chat-reply-label').textContent = '';
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
