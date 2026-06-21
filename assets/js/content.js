// content.js — commentaires de page archive (content.php)
// Utilise window.buildCommentTree + window.renderCommentNode de ui.js

async function ccLoad() {
    const res  = await fetch(`api/api_content_comments.php?content_id=${CC_CONTENT_ID}`);
    const data = await res.json();
    const list = document.getElementById('ccList');
    if (!list) return;
    if (!data.success || !data.comments.length) {
        list.innerHTML = '<p style="color:var(--text-dim);font-size:0.8rem;font-style:italic;">Aucune réaction enregistrée.</p>';
        return;
    }
    const tree = window.buildCommentTree(data.comments);
    list.innerHTML = tree.map(n => window.renderCommentNode(n, 0)).join('');
}

async function ccPost() {
    if (!CC_IS_LOGGED) return;
    const body = document.getElementById('ccInput')?.value.trim();
    if (!body) return;

    const fd = new FormData();
    fd.append('action', 'post');
    fd.append('content_id', CC_CONTENT_ID);
    fd.append('body', body);
    const replyId = document.getElementById('commentParentId')?.value;
    if (replyId) fd.append('parent_id', replyId);

    const res  = await fetch('api/api_content_comments.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) return;

    document.getElementById('ccInput').value = '';
    ccCancelReply();
    // Recharge tout l'arbre pour avoir la bonne position dans le thread
    ccLoad();
}

function ccReply(id, username) {
    // Réutilise le mécanisme de replyToComment de ui.js
    if (window.replyToComment) { window.replyToComment(id, username); return; }
    const parentEl = document.getElementById('commentParentId');
    if (parentEl) parentEl.value = id;
    const indicator = document.getElementById('commentReplyIndicator') || document.getElementById('ccReplyIndicator');
    const label     = document.getElementById('commentReplyLabel')     || document.getElementById('ccReplyLabel');
    if (indicator) indicator.style.display = 'flex';
    if (label)     label.textContent = `Réponse à @${username}`;
    document.getElementById('ccInput')?.focus();
}

function ccCancelReply() {
    const parentEl  = document.getElementById('commentParentId');
    const indicator = document.getElementById('commentReplyIndicator') || document.getElementById('ccReplyIndicator');
    const label     = document.getElementById('commentReplyLabel')     || document.getElementById('ccReplyLabel');
    if (parentEl)  parentEl.value = '';
    if (indicator) indicator.style.display = 'none';
    if (label)     label.textContent = '';
}

async function ccDelete(id) {
    if (!confirm('Purger ce commentaire ?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('comment_id', id);
    const res  = await fetch('api/api_content_comments.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) document.querySelector(`.comment-item[data-id="${id}"]`)?.remove();
}

// Override deleteComment de ui.js pour pointer sur la bonne API
window.deleteComment = ccDelete;

async function deleteContent(id) {
    if (!confirm('Supprimer définitivement ce contenu ?')) return;
    const fd = new FormData();
    fd.append('id', id);
    const res  = await fetch('api/api_content_delete.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) window.location.href = 'archives.php';
}

// Wiring du formulaire (même IDs que fiche.php via renderCommentSection)
document.addEventListener('DOMContentLoaded', () => {
    // Le formulaire sur content.php utilise ccInput/ccPost, pas commentForm
    // On connecte aussi le bouton "Transmettre" déjà en HTML via onclick="ccPost()"
    ccLoad();
});
