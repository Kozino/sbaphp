/* SBA 2012 — offline support. Static assets: cache-first. Pages: network-first, falls back to last copy, then offline page. */
const VERSION = 'sba2012-v1';
const SHELL = ['/', '/offline.html', '/assets/css/site.css', '/assets/js/app.js', '/assets/img/logo.png', '/assets/img/icon-192.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin) return;
  // Never cache admin, API, audit lookups or file downloads
  if (url.pathname.startsWith('/admin') || url.pathname.startsWith('/api') || url.pathname.startsWith('/file.php') || url.pathname.startsWith('/audit.php')) return;

  if (url.pathname.startsWith('/assets/') || url.pathname.startsWith('/uploads/')) {
    e.respondWith(
      caches.match(req).then((hit) => hit || fetch(req).then((res) => {
        if (res.ok) caches.open(VERSION).then((c) => c.put(req, res.clone()));
        return res;
      }))
    );
    return;
  }

  // Pages: network first, cache a copy for offline reading
  e.respondWith(
    fetch(req).then((res) => {
      if (res.ok && res.headers.get('content-type')?.includes('text/html')) {
        caches.open(VERSION).then((c) => c.put(req, res.clone()));
      }
      return res;
    }).catch(() => caches.match(req).then((hit) => hit || caches.match('/offline.html')))
  );
});
