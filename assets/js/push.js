/**
 * ASSETS/JS/PUSH.JS
 * Service Worker registration + Web Push opt-in/out.
 * Requires window.VAPID_PUBLIC_KEY set by header.php.
 */

(async () => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
    if (!window.VAPID_PUBLIC_KEY) return;

    // Enregistrement du SW
    let reg;
    try {
        reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
    } catch (e) {
        console.warn('[push] SW registration failed', e);
        return;
    }

    // Attend que le SW soit actif — avec timeout de 4s pour ne pas bloquer
    // indéfiniment si un ancien SW est en conflit dans le navigateur.
    const activeReg = await Promise.race([
        navigator.serviceWorker.ready,
        new Promise(resolve => setTimeout(() => resolve(reg), 4000)),
    ]);

    updatePushBtn(activeReg);
})();

async function updatePushBtn(reg) {
    const btn = document.getElementById('push-optin-btn');
    if (!btn) return;

    try {
        const sub = await reg.pushManager.getSubscription();
        btn.style.display = 'inline';
        btn.textContent   = sub ? '🔔 Push ON' : '🔕 Push OFF';
        btn.title         = sub ? 'Désactiver les notifications push' : 'Activer les notifications push';
        btn._reg = reg;
    } catch (e) {
        console.warn('[push] updatePushBtn failed', e);
    }
}

async function pushToggle() {
    const btn = document.getElementById('push-optin-btn');
    if (!btn || !btn._reg) return;

    const reg = btn._reg;

    try {
        const sub = await reg.pushManager.getSubscription();

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

            const newSub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(window.VAPID_PUBLIC_KEY),
            });

            const { endpoint, keys } = newSub.toJSON();
            const res = await fetch('/api/api_push_subscribe.php', {
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

        btn._reg = reg;

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
