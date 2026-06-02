// Netcrafter Service Worker — v1
const CACHE = 'nc-cache-v1';
const OFFLINE_URL = '/netcrafter/offline.html';

const PRECACHE = [
  '/netcrafter/',
  '/netcrafter/index.php',
  '/netcrafter/offline.html',
  '/netcrafter/image/logo-n.png',
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE)
      .then(c => c.addAll(PRECACHE.filter(Boolean)))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  if (e.request.url.includes('/admin/')) return;

  e.respondWith(
    caches.match(e.request).then(cached => {
      const network = fetch(e.request).then(response => {
        if (response.ok) {
          const clone = response.clone();
          caches.open(CACHE).then(c => c.put(e.request, clone));
        }
        return response;
      }).catch(() => cached || caches.match(OFFLINE_URL));
      return cached || network;
    })
  );
});
