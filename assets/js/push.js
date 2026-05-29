/**
 * ASSETS/JS/PUSH.JS
 * Service Worker registration + Web Push opt-in/out.
 * Requires window.VAPID_PUBLIC_KEY set by header.php.
 *
 * Le bouton #push-optin-btn est visible par défaut dans le HTML.
 * Ce script met à jour son état (ON/OFF) et masque le bouton si
 * le navigateur ne supporte pas les push notifications.
 */

(async () => {
    const btn = document.getElementById('push-optin-btn');
    if (!btn) return;

    // Masquer le bouton si le navigateur ne supporte pas push
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !window.VAPID_PUBLIC_KEY) {
        btn.style.display = 'none';
        return;
    }

    // Enregistrement SW
    let reg;
    try {
        reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
    } catch (e) {
        console.warn('[push] SW registration failed', e);
        // Bouton reste visible en état "OFF" — l'utilisateur peut réessayer
        return;
    }

    // Attend le SW actif, avec timeout 4s pour ne pas bloquer
    const activeReg = await Promise.race([
        navigator.serviceWorker.ready,
        new Promise(resolve => setTimeout(() => resolve(reg), 4000)),
    ]);

    await updatePushBtn(activeReg);
})();

async function updatePushBtn(reg) {
    const btn = document.getElementById('push-optin-btn');
    if (!btn) return;

    // pushManager peut être null si le SW n'est pas encore actif
    const pm = reg?.pushManager ?? reg?.active?.pushManager ?? null;
    if (!pm) {
        // SW pas encore actif — bouton visible en état indéfini, sera mis à jour au prochain chargement
        btn._reg = reg;
        return;
    }

    try {
        const sub = await pm.getSubscription();
        btn.textContent = sub ? '🔔 Push ON' : '🔕 Push OFF';
        btn.title       = sub ? 'Désactiver les notifications push' : 'Activer les notifications push';
        btn._reg = reg;
    } catch (e) {
        console.warn('[push] getSubscription failed', e);
        btn._reg = reg;
    }
}

async function pushToggle() {
    const btn = document.getElementById('push-optin-btn');
    if (!btn) return;

    // Si _reg n'est pas encore set, tenter de l'initialiser maintenant
    if (!btn._reg) {
        try {
            const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            const activeReg = await Promise.race([
                navigator.serviceWorker.ready,
                new Promise(resolve => setTimeout(() => resolve(reg), 4000)),
            ]);
            btn._reg = activeReg;
        } catch (e) {
            console.error('[push] Cannot initialize SW for toggle', e);
            return;
        }
    }

    const reg = btn._reg;
    const pm  = reg?.pushManager ?? reg?.active?.pushManager ?? null;
    if (!pm) {
        console.warn('[push] pushManager not available');
        return;
    }

    try {
        const sub = await pm.getSubscription();

        if (sub) {
            await sub.unsubscribe();
            await fetch('/api/api_push_subscribe.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ endpoint: sub.endpoint }),
            });
            btn.textContent = '🔕 Push OFF';
            btn.title = 'Activer les notifications push';
        } else {
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') return;

            const newSub = await pm.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(window.VAPID_PUBLIC_KEY),
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

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw     = atob(base64);
    const output  = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) output[i] = raw.charCodeAt(i);
    return output;
}
