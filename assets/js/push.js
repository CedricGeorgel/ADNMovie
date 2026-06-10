/**
 * ASSETS/JS/PUSH.JS
 * Toujours chargé pour les users connectés.
 * Affiche le bouton #push-optin-btn uniquement si les conditions sont réunies :
 *   - VAPID_PUBLIC_KEY disponible (vapid_keys.php présent sur le serveur)
 *   - serviceWorker + PushManager supportés par le navigateur
 * pushToggle() est toujours défini pour éviter les ReferenceError.
 */

// ── Initialisation ────────────────────────────────────────────────────────────

(async () => {
    const btn = document.getElementById('push-optin-btn');
    if (!btn) return;

    // Conditions requises — si l'une manque, le bouton reste caché
    if (!window.VAPID_PUBLIC_KEY) return; // vapid_keys.php absent du serveur
    if (!('serviceWorker' in navigator)) return;
    if (!('PushManager' in window)) return; // navigateur non compatible

    // Enregistrement SW
    let reg;
    try {
        reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
    } catch (e) {
        console.warn('[push] SW registration failed', e);
        return;
    }

    // Attend le SW actif, avec timeout 4s
    const activeReg = await Promise.race([
        navigator.serviceWorker.ready,
        new Promise(resolve => setTimeout(() => resolve(reg), 4000)),
    ]);

    // Affiche le bouton et met à jour son état
    btn.style.display = 'inline';
    btn._reg = activeReg;
    await _refreshPushBtnState(btn, activeReg);
})();

// ── Helpers privés ────────────────────────────────────────────────────────────

async function _getPushManager(reg) {
    // pushManager peut être null si le SW n'est pas encore actif
    return reg?.pushManager ?? null;
}

async function _refreshPushBtnState(btn, reg) {
    const pm = await _getPushManager(reg);
    if (!pm) return; // SW pas encore actif, état mis à jour au prochain chargement

    try {
        const sub = await pm.getSubscription();
        btn.textContent = sub ? '🔔 Push ON' : '🔕 Push OFF';
        btn.title       = sub ? 'Désactiver les notifications push' : 'Activer les notifications push';
    } catch (e) {
        console.warn('[push] getSubscription failed', e);
    }
}

// ── API publique ──────────────────────────────────────────────────────────────

// Toujours défini pour éviter "pushToggle is not defined"
async function pushToggle() {
    const btn = document.getElementById('push-optin-btn');
    if (!btn) return;

    // VAPID non configuré sur ce serveur
    if (!window.VAPID_PUBLIC_KEY) {
        alert('Les notifications push ne sont pas configurées sur ce serveur.');
        return;
    }

    // Initialiser _reg si absent
    if (!btn._reg) {
        try {
            const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            btn._reg = await Promise.race([
                navigator.serviceWorker.ready,
                new Promise(resolve => setTimeout(() => resolve(reg), 4000)),
            ]);
        } catch (e) {
            console.error('[push] Cannot init SW', e);
            return;
        }
    }

    const reg = btn._reg;
    const pm  = await _getPushManager(reg);
    if (!pm) {
        console.warn('[push] pushManager not available');
        return;
    }

    try {
        const sub = await pm.getSubscription();

        if (sub) {
            // Désabonnement
            await sub.unsubscribe();
            await fetch('/api/api_push_subscribe.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endpoint: sub.endpoint }),
            });
            btn.textContent = '🔕 Push OFF';
            btn.title = 'Activer les notifications push';

        } else {
            // Abonnement
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') return;

            const newSub = await pm.subscribe({
                userVisibleOnly: true,
                applicationServerKey: _urlBase64ToUint8Array(window.VAPID_PUBLIC_KEY),
            });

            const { endpoint, keys } = newSub.toJSON();
            const res  = await fetch('/api/api_push_subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endpoint, keys }),
            });
            const json = await res.json().catch(() => ({}));
            if (!json.ok) {
                await newSub.unsubscribe();
                console.error('[push] subscribe failed', json);
                return;
            }

            btn.textContent = '🔔 Push ON';
            btn.title = 'Désactiver les notifications push';
        }

    } catch (e) {
        console.error('[push] pushToggle failed', e);
    }
}

function _urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw     = atob(base64);
    const output  = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) output[i] = raw.charCodeAt(i);
    return output;
}
