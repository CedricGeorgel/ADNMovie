const CACHE = 'adnmovie-v1';

self.addEventListener('install', e => {
    self.skipWaiting();
});

self.addEventListener('activate', e => {
    e.waitUntil(clients.claim());
});

self.addEventListener('fetch', e => {
    // Pass-through : pas de cache applicatif, on laisse le PHP gérer
    e.respondWith(fetch(e.request).catch(() => caches.match(e.request)));
});
