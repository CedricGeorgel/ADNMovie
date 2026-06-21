/**
 * CHAT_DM.JS — Messages directs (chat.php)
 * Utilise window.buildChatBubble() de ui.js pour le rendu unifié.
 */

(function () {
    'use strict';

    let lastId    = 0;
    let pollTimer = null;
    let isSending = false;
    const POLL_MS = 3000;

    const messagesEl   = document.getElementById('dmMessages');
    const loadingState = document.getElementById('dmLoadingState');
    const inputEl      = document.getElementById('dmInput');

    function scrollToBottom(smooth) {
        if (!messagesEl) return;
        messagesEl.scrollTo({ top: messagesEl.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
    }

    async function poll() {
        if (!CHAT_WITH) return;
        try {
            const url = `api/api_dm_get.php?with=${encodeURIComponent(CHAT_WITH)}&last_id=${lastId}`;
            const res = await fetch(url, { cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();
            if (!data.success || !Array.isArray(data.messages)) return;

            if (data.messages.length > 0) {
                if (loadingState && loadingState.parentNode) loadingState.remove();
                const wasAtBottom = messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight < 60;
                for (const msg of data.messages) {
                    const bubble = window.buildChatBubble(msg, msg.is_mine, {
                        replyInputId:     'dmReplyToId',
                        replyLabelId:     'dmReplyLabel',
                        replyIndicatorId: 'dmReplyIndicator',
                        chatInputId:      'dmInput',
                    });
                    messagesEl.appendChild(bubble);
                    if (msg.id > lastId) lastId = msg.id;
                }
                if (wasAtBottom) scrollToBottom(true);
            } else if (lastId === 0 && loadingState && loadingState.parentNode) {
                loadingState.textContent = 'Aucun message. Dites bonjour !';
            }
        } catch (_) {}
        pollTimer = setTimeout(poll, POLL_MS);
    }

    async function sendDm() {
        if (!inputEl || isSending) return;
        const text = inputEl.value.trim();
        if (!text) return;

        const replyId = document.getElementById('dmReplyToId')?.value || '';
        isSending = true;
        inputEl.disabled = true;
        inputEl.value = '';
        cancelDmReply();

        try {
            const params = { to: CHAT_WITH, message: text };
            if (replyId) params['reply_to_id'] = replyId;
            const res  = await fetch('api/api_dm_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(params),
            });
            const data = await res.json();
            if (data.success) {
                clearTimeout(pollTimer);
                poll();
            }
        } catch (_) {}
        finally {
            isSending = false;
            inputEl.disabled = false;
            inputEl.focus();
        }
    }

    window.cancelDmReply = function() {
        const el = document.getElementById('dmReplyToId');
        if (el) el.value = '';
        const ind = document.getElementById('dmReplyIndicator');
        if (ind) ind.style.display = 'none';
        const lbl = document.getElementById('dmReplyLabel');
        if (lbl) lbl.textContent = '';
    };

    if (inputEl) {
        inputEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendDm(); }
        });
    }

    window.sendDm = sendDm;
    poll();

    window.addEventListener('pagehide', () => clearTimeout(pollTimer));
})();
