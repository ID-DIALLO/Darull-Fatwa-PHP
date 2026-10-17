/*
 * Darul Fatwa — service worker (§13.4, PWA légère).
 * - Ressources statiques (/assets, /vendor) : cache d'abord, mise à jour en arrière-plan.
 * - Pages HTML : réseau uniquement, page hors-ligne traduite en repli. Aucune page privée
 *   (/admin, mon espace, espace professeur, /api, /media) n'est mise en cache.
 */
const VERSION = 'df-v1';
const STATIC_CACHE = VERSION + '-static';
const OFFLINE_URL = '/offline';
const PRECACHE = [OFFLINE_URL, '/assets/img/logo/darul-fatwa-icone.svg', '/assets/img/logo/darul-fatwa-logo.svg'];
const PRIVATE = /^\/(admin|api|media|fr\/mon-espace|fr\/professeur|(ar|en)\/my-account|(ar|en)\/scholar)(\/|$)/;

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== STATIC_CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }
  const url = new URL(request.url);
  if (url.origin !== self.location.origin || PRIVATE.test(url.pathname)) {
    return;
  }

  // Navigation : réseau, puis page hors-ligne
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
    return;
  }

  // Statique versionné : cache d'abord (stale-while-revalidate)
  if (/^\/(assets|vendor)\//.test(url.pathname) || url.pathname === '/manifest.webmanifest') {
    event.respondWith(
      caches.open(STATIC_CACHE).then(async (cache) => {
        const cached = await cache.match(request);
        const network = fetch(request).then((response) => {
          if (response.ok && response.type === 'basic') {
            cache.put(request, response.clone());
          }
          return response;
        }).catch(() => cached);
        return cached || network;
      }),
    );
  }
});
