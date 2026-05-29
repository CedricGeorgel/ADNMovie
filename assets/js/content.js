let ccOffset = 0;
let ccReplyTo = null;

function ccFormatBody(text) {
    text = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const escTokens = {};
    let escIdx = 0;
    text = text.replace(/\\([*_`#\/\-\\>])/g, (_, ch) => {
        const key = '\x02' + escIdx++ + '\x03';
        escTokens[key] = ch;
        return key;
    });
    text = text.replace(/@(\w+)/g, '<span style="color:var(--pastel-blue);font-weight:bold;">@$1</span>');
    text = text.replace(/\*\*(.+?)\*\*/gs, '<strong style="color:var(--text-main);">$1</strong>');
    text = text.replace(/\*([^*\n]+?)\*/g, '<em>$1</em>');
    text = text.replace(/`([^`]+)`/g, '<code style="background:rgba(255,255,255,0.07);padding:2px 5px;border-radius:4px;font-size:0.85em;font-family:monospace;">$1</code>');

    // #slug → archive (vert)
    text = text.replace(/(?<!\S)#([\w-]+)/g, (_, slug) =>
        `<a href="content.php?slug=${slug}" style="color:var(--pastel-green);font-weight:bold;">#${slug}</a>`);

    // //[id|encTitle|encPoster|year] → carte film/série (format résolu par le serveur)
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

    // /[tv-id:titre] et /[id:titre] → lien amber (format résolu)
    text = text.replace(/\/\[tv-(\d+):([^\]]+)\]/g, (_, id, t) =>
        `<a href="fiche.php?id=${id}&type=tv" style="color:var(--color-amber);font-weight:bold;">/${t}</a>`);
    text = text.replace(/\/\[(\d+):([^\]]+)\]/g, (_, id, t) =>
        `<a href="fiche.php?id=${id}" style="color:var(--color-amber);font-weight:bold;">/${t}</a>`);

    // fallbacks non résolus
    text = text.replace(/(?<!\S)\/\/tv-(\d+)/g, (_, id) =>
        `<a href="fiche.php?id=${id}&type=tv" style="color:var(--color-amber);font-weight:bold;">/Série #${id}</a>`);
    text = text.replace(/(?<!\S)\/\/(\d+)/g, (_, id) =>
        `<a href="fiche.php?id=${id}" style="color:var(--color-amber);font-weight:bold;">/Film #${id}</a>`);
    text = text.replace(/(?<!\S)\/tv-(\d+)/g, (_, id) =>
        `<a href="fiche.php?id=${id}&type=tv" style="color:var(--color-amber);font-weight:bold;">/Série #${id}</a>`);
    text = text.replace(/(?<!\S)\/(\d+)/g, (_, id) =>
        `<a href="fiche.php?id=${id}" style="color:var(--color-amber);font-weight:bold;">/${id}</a>`);

    text = text.replace(/\n/g, '<br>');
    Object.entries(escTokens).forEach(([k, v]) => { text = text.split(k).join(v); });
    return text;
}

function ccRenderComment(c, prepend = false) {
    const isReply = !!c.parent_id;
    const indent  = isReply ? 'margin-left:50px;border-left:2px solid var(--border);padding-left:20px;' : '';
    const date    = new Date(c.created_at).toLocaleDateString('fr-FR');
    const canDel  = CC_IS_ADMIN || (CC_IS_LOGGED && c.user_id === CC_CURRENT_USER_ID);

    const html = `
    <div class="cc-item" data-id="${c.id}" style="display:flex;gap:15px;margin-bottom:25px;padding-bottom:10px;${indent}">
        <a href="adn.php?id=${encodeURIComponent(c.user_id)}" style="flex-shrink:0;">
            <img src="${c.avatar || 'assets/default-avatar.png'}"
                 onerror="this.src='assets/default-avatar.png'"
                 style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
        </a>
        <div style="flex:1;min-width:0;">
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-weight:700;font-size:0.85rem;">${c.username}</span>
                    <span style="font-size:0.65rem;color:var(--text-dim);font-family:monospace;">${date}</span>
                </div>
                <div style="display:flex;gap:8px;">
                    ${!isReply && CC_IS_LOGGED ? `<button onclick="ccReply(${c.id},'${c.username.replace(/'/g,"\\'")}') " class="comment-reply-btn">Répondre</button>` : ''}
                    ${canDel ? `<button onclick="ccDelete(${c.id})" style="background:none;border:none;color:var(--danger);font-size:0.6rem;cursor:pointer;text-transform:uppercase;font-weight:800;opacity:0.5;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.5">[Purger]</button>` : ''}
                </div>
            </div>
            <p style="font-size:0.85rem;line-height:1.5;color:var(--text-muted);margin:0;word-wrap:break-word;">
                ${ccFormatBody(c.body)}
            </p>
        </div>
    </div>`;

    const list = document.getElementById('ccList');
    if (prepend) list.insertAdjacentHTML('afterbegin', html);
    else list.insertAdjacentHTML('beforeend', html);
}

async function ccLoad() {
    const res  = await fetch(`api/api_content_comments.php?content_id=${CC_CONTENT_ID}&offset=${ccOffset}`);
    const data = await res.json();
    if (!data.success) return;
    data.comments.forEach(c => ccRenderComment(c));
    ccOffset += data.comments.length;
    document.getElementById('ccLoadMore').style.display = data.has_more ? 'block' : 'none';
}

async function ccPost() {
    if (!CC_IS_LOGGED) return;
    const body = document.getElementById('ccInput').value.trim();
    if (!body) return;

    const fd = new FormData();
    fd.append('action', 'post');
    fd.append('content_id', CC_CONTENT_ID);
    fd.append('body', body);
    if (ccReplyTo) fd.append('parent_id', ccReplyTo);

    const res  = await fetch('api/api_content_comments.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) return;

    document.getElementById('ccInput').value = '';
    ccCancelReply();
    ccRenderComment(data.comment, true);
}

function ccReply(id, username) {
    ccReplyTo = id;
    document.getElementById('ccReplyLabel').textContent = `Réponse à @${username}`;
    document.getElementById('ccReplyIndicator').style.display = 'flex';
    document.getElementById('ccInput').focus();
}

function ccCancelReply() {
    ccReplyTo = null;
    document.getElementById('ccReplyIndicator').style.display = 'none';
}

async function ccDelete(id) {
    if (!confirm('Purger ce commentaire ?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('comment_id', id);
    const res  = await fetch('api/api_content_comments.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) document.querySelector(`.cc-item[data-id="${id}"]`)?.remove();
}

async function deleteContent(id) {
    if (!confirm('Supprimer définitivement ce contenu ?')) return;
    const fd = new FormData();
    fd.append('id', id);
    const res  = await fetch('api/api_content_delete.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) window.location.href = 'archives.php';
}

ccLoad();
