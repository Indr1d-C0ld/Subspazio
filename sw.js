/* SubSpazio — service worker: guscio offline + cache degli asset statici. */
const VERSION = 'subspazio-v6';
const BASE = '/subspazio';
const SHELL = [
  BASE + '/',
  BASE + '/offline.html',
  BASE + '/manifest.webmanifest',
  BASE + '/assets/css/app.css',
  BASE + '/assets/js/app.js',
  BASE + '/assets/js/live.js',
  BASE + '/assets/js/game.js',
  BASE + '/assets/js/pwa.js',
  BASE + '/assets/icons/icon-192.png',
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(VERSION).then((c) => c.addAll(SHELL)).catch(() => {}).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((ks) => Promise.all(ks.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // stato di gioco e stream: mai dalla cache
  if (url.pathname.includes('/api/')) return;

  // asset statici: cache-first, aggiorna in background
  if (/\.(css|js|png|svg|webmanifest|woff2?|ico)$/.test(url.pathname)) {
    e.respondWith(
      caches.match(req).then((hit) => {
        const net = fetch(req).then((res) => {
          const copy = res.clone();
          caches.open(VERSION).then((c) => c.put(req, copy));
          return res;
        }).catch(() => hit);
        return hit || net;
      })
    );
    return;
  }

  // navigazioni: rete prima, guscio offline come fallback
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match(BASE + '/offline.html')));
  }
});
