const CACHE_NAME = 'netcrafter-formations-v1';
const urlsToCache = [
  'https://netcrafterniger.com/formation/formations.php',
  'https://netcrafterniger.com/formation/formation-details.php',
  'https://netcrafterniger.com/formation/dashboard.php',
  'https://netcrafterniger.com/formation/my-formation.php',
  'https://netcrafterniger.com/formation/formation-favorites.php',
  'https://netcrafterniger.com/formation/forum.php',
  'https://netcrafterniger.com/formation/certificates.php',
  'https://netcrafterniger.com/formation/profile.php',
  'https://netcrafterniger.com/formation/settings.php',
  'https://netcrafterniger.com/formation/manifest.json',
  'https://netcrafterniger.com/image/logo-n.png',
  'https://netcrafterniger.com/image/netcrafter.png',
  // CSS externes
  'https://cdn.tailwindcss.com',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css',
  'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css'
];

// Installation du Service Worker
self.addEventListener('install', event => {
  console.log('[SW] Installation...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[SW] Cache ouvert');
        return cache.addAll(urlsToCache.map(url => new Request(url, {mode: 'no-cors'})))
          .catch(err => {
            console.warn('[SW] Certaines ressources n\'ont pas pu être mises en cache:', err);
            // Cache les ressources une par une pour éviter qu'une erreur bloque tout
            return Promise.allSettled(
              urlsToCache.map(url => cache.add(new Request(url, {mode: 'no-cors'})))
            );
          });
      })
  );
  self.skipWaiting();
});

// Activation du Service Worker
self.addEventListener('activate', event => {
  console.log('[SW] Activation...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('[SW] Suppression ancien cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Gestion des requêtes réseau
self.addEventListener('fetch', event => {
  // Ignorer les requêtes non-GET et les extensions de navigateur
  if (event.request.method !== 'GET' || 
      event.request.url.startsWith('chrome-extension://') ||
      event.request.url.startsWith('moz-extension://')) {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(cachedResponse => {
        // Si la ressource est en cache, la retourner
        if (cachedResponse) {
          return cachedResponse;
        }

        // Sinon, faire la requête réseau
        return fetch(event.request)
          .then(response => {
            // Vérifier si la réponse est valide
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            // Cloner la réponse car elle ne peut être consommée qu'une fois
            const responseToCache = response.clone();

            // Ajouter la réponse au cache
            caches.open(CACHE_NAME)
              .then(cache => {
                cache.put(event.request, responseToCache);
              });

            return response;
          })
          .catch(err => {
            console.warn('[SW] Erreur réseau:', err);
            // Retourner une page hors ligne personnalisée si disponible
            if (event.request.destination === 'document') {
              return caches.match('https://netcrafterniger.com/formation/offline.html') || 
                     new Response('Hors ligne - Veuillez vérifier votre connexion internet', {
                       status: 503,
                       statusText: 'Service Unavailable',
                       headers: {'Content-Type': 'text/html'}
                     });
            }
          });
      })
  );
});

// Gestion des notifications push (optionnel)
self.addEventListener('push', event => {
  if (event.data) {
    const data = event.data.json();
    const options = {
      body: data.body,
      icon: 'https://netcrafterniger.com/image/logo-n.png',
      badge: 'https://netcrafterniger.com/image/logo-n.png',
      vibrate: [100, 50, 100],
      data: {
        dateOfArrival: Date.now(),
        primaryKey: data.primaryKey || '1'
      },
      actions: [
        {
          action: 'explore',
          title: 'Voir la formation',
          icon: 'https://netcrafterniger.com/image/logo-n.png'
        },
        {
          action: 'close',
          title: 'Fermer',
          icon: 'https://netcrafterniger.com/image/logo-n.png'
        }
      ]
    };

    event.waitUntil(
      self.registration.showNotification(data.title || 'Netcrafter Formations', options)
    );
  }
});

// Gestion des clics sur notifications
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  if (event.action === 'explore') {
    event.waitUntil(
      clients.openWindow('https://netcrafterniger.com/formation/formations.php')
    );
  }
});