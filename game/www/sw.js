/* sw.js — سرویس‌ورکر قارچ‌خور: بازی پس از نخستین بارگذاری آفلاین اجرا می‌شود */
const CACHE = 'gharchekhor-v1';
const ASSETS = [
  './',
  './index.html',
  './manifest.webmanifest',
  './css/style.css',
  './js/config.js',
  './js/utils.js',
  './js/sprites.js',
  './js/levels.js',
  './js/audio.js',
  './js/input.js',
  './js/physics.js',
  './js/player.js',
  './js/entities.js',
  './js/fx.js',
  './js/game.js',
  './js/render.js',
  './js/ui.js',
  './js/main.js',
  './assets/img/icon-192.png',
  './assets/img/icon-512.png',
  './assets/fonts/Vazirmatn-400.woff2',
  './assets/fonts/Vazirmatn-500.woff2',
  './assets/fonts/Vazirmatn-700.woff2',
  './assets/fonts/Vazirmatn-900.woff2',
  './assets/fonts/VazirmatnLatin-400.woff2',
  './assets/fonts/VazirmatnLatin-500.woff2',
  './assets/fonts/VazirmatnLatin-700.woff2',
  './assets/fonts/VazirmatnLatin-900.woff2',
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE).then((c) => Promise.all(ASSETS.map((a) => c.add(a).catch(() => null)))).then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET' || new URL(req.url).origin !== location.origin) return;
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put('./index.html', copy)).catch(() => {});
          return res;
        })
        .catch(() => caches.match('./index.html').then((r) => r || caches.match('./'))),
    );
    return;
  }
  e.respondWith(
    caches.match(req).then(
      (hit) =>
        hit ||
        fetch(req).then((res) => {
          if (res && res.status === 200 && res.type === 'basic') {
            const copy = res.clone();
            caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
          }
          return res;
        }),
    ),
  );
});
