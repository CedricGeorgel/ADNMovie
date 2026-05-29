/**
 * SW.JS — Service Worker ADN Movie
 * Gère les Web Push notifications (RFC 8030 / RFC 8291).
 */

const CACHE_NAME = 'adnmovie-v1';

// ── Push event ────────────────────────────────────────────────────────────────

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

// ── Notification click ────────────────────────────────────────────────────────

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
