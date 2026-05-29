/**
 * SW.JS — Service Worker ADN Movie (unique)
 * Gère : PWA install / offline fallback + Web Push notifications.
 *
 * Fichier unique pour éviter le conflit de scope '/' entre
 * l'ancien service-worker.js (install.php) et ce fichier (push).
 * Les deux enregistraient scope '/' — le second restait en 'waiting'
 * indéfiniment, bloquant navigator.serviceWorker.ready dans push.js.
 */

const CACHE_NAME = 'adnmovie-v2';

// ── Lifecycle ────────────────────────────────────────────────────────────────

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(clients.claim());
});

// ── Fetch (pass-through, fallback cache) ────────────────────────────────────

self.addEventListener('fetch', event => {
    event.respondWith(fetch(event.request).catch(() => caches.match(event.request)));
});

// ── Push event ───────────────────────────────────────────────────────────────

self.addEventListener('push', event => {
    let data = { title: 'ADN Movie', body: 'Nouvelle notification', url: '/' };
    try {
        if (event.data) data = { ...data, ...event.data.json() };
    } catch (_) {}

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/assets/Icons/Logo3.png',
            badge: '/assets/Icons/Logo2.ico',
            data: { url: data.url },
            vibrate: [100, 50, 100],
        })
    );
});

// ── Notification click ───────────────────────────────────────────────────────

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const target = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
            for (const client of list) {
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    client.navigate(target);
                    return client.focus();
                }
            }
            return clients.openWindow(target);
        })
    );
});
