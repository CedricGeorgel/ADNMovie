/**
 * CHAT_DM.JS
 * Gestion du polling et de l'envoi de messages directs.
 * Dépend des globales CHAT_WITH et CHAT_MY_ID injectées par chat.php.
 */

(function () {
    'use strict';

    let lastId     = 0;
    let pollTimer  = null;
    let isSending  = false;
    const POLL_MS  = 3000;

    const messagesEl   = document.getElementById('dmMessages');
    const loadingState = document.getElementById('dmLoadingState');
    const inputEl      = document.getElementById('dmInput');

    // ── Rendu d'une bulle ─────────────────────────────────────────────────────

    function buildBubble(msg) {
        const wrap = document.createElement('div');
        wrap.style.display        = 'flex';
        wrap.style.flexDirection  = 'column';
        wrap.style.alignItems     = msg.is_mine ? 'flex-end' : 'flex-start';

        const bubble = document.createElement('div');
        bubble.classList.add('dm-bubble', msg.is_mine ? 'mine' : 'theirs');
        bubble.textContent = msg.text;

        const time = document.createElement('div');
        time.classList.add('dm-time');
        time.textContent = msg.time;

        wrap.appendChild(bubble);
        wrap.appendChild(time);
        return wrap;
    }

    // ── Scroll vers le bas ────────────────────────────────────────────────────

    function scrollToBottom(smooth) {
        if (!messagesEl) return;
        messagesEl.scrollTo({
            top:      messagesEl.scrollHeight,
            behavior: smooth ? 'smooth' : 'instant',
        });
    }

    // ── Appel API : récupère les nouveaux messages ────────────────────────────

    async function poll() {
        if (!CHAT_WITH) return;

        try {
            const url = `api/api_dm_get.php?with=${encodeURIComponent(CHAT_WITH)}&last_id=${lastId}`;
            const res = await fetch(url, { cache: 'no-store' });
            if (!res.ok) return;

            const data = await res.json();
            if (!data.success || !Array.isArray(data.messages)) return;

            if (data.messages.length > 0) {
                // Premier chargement : vide le placeholder
                if (loadingState && loadingState.parentNode) {
                    loadingState.remove();
                }

                const wasAtBottom =
                    messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight < 60;

                for (const msg of data.messages) {
                    messagesEl.appendChild(buildBubble(msg));
                    if (msg.id > lastId) lastId = msg.id;
                }

                if (wasAtBottom) scrollToBottom(true);
            } else if (lastId === 0 && loadingState && loadingState.parentNode) {
                // Aucun message : retire le placeholder
                loadingState.textContent = 'Aucun message. Dites bonjour !';
            }
        } catch (_) {
            // Silencieux en cas d'erreur réseau
        }

        pollTimer = setTimeout(poll, POLL_MS);
    }

    // ── Envoi d'un message ────────────────────────────────────────────────────

    async function sendDm() {
        if (!inputEl || isSending) return;

        const text = inputEl.value.trim();
        if (!text) return;

        isSending = true;
        inputEl.disabled = true;

        try {
            const body = new URLSearchParams({ to: CHAT_WITH, message: text });
            const res  = await fetch('api/api_dm_post.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
            });

            const data = await res.json();
            if (data.success) {
                inputEl.value = '';
                // Force un poll immédiat pour afficher le message envoyé
                clearTimeout(pollTimer);
                poll();
            }
        } catch (_) {
            // silencieux
        } finally {
            isSending        = false;
            inputEl.disabled = false;
            inputEl.focus();
        }
    }

    // ── Envoi sur touche Entrée ───────────────────────────────────────────────

    if (inputEl) {
        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendDm();
            }
        });
    }

    // ── Expose sendDm globalement pour le bouton HTML ─────────────────────────

    window.sendDm = sendDm;

    // ── Démarre le polling dès le chargement ──────────────────────────────────

    poll();

    // ── Nettoyage quand on quitte la page ────────────────────────────────────

    window.addEventListener('pagehide', function () {
        clearTimeout(pollTimer);
    });
})();
