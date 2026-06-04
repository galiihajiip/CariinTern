const CACHE_VERSION = 'cariintern-v1.0.1';
const STATIC_CACHE = 'cariintern-static-v2';
const DYNAMIC_CACHE = 'cariintern-dynamic-v2';
const BASE_PATH = '/internship-system';
const OFFLINE_PAGE = `${BASE_PATH}/offline.php`;
const MAX_DYNAMIC_ITEMS = 50;

const STATIC_ASSETS = [
  `${BASE_PATH}/`,
  `${BASE_PATH}/index.php`,
  `${BASE_PATH}/login.php`,
  `${BASE_PATH}/offline.php`,
  `${BASE_PATH}/manifest.json`,
  `${BASE_PATH}/assets/vendor/bootstrap/bootstrap.min.css`,
  `${BASE_PATH}/assets/vendor/bootstrap/bootstrap.bundle.min.js`,
  `${BASE_PATH}/assets/vendor/bootstrap-icons/bootstrap-icons.min.css`,
  `${BASE_PATH}/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2`,
  `${BASE_PATH}/assets/css/custom.css`,
  `${BASE_PATH}/assets/css/theme.css`,
  `${BASE_PATH}/assets/js/custom.js`,
  `${BASE_PATH}/assets/js/pwa.js`,
  `${BASE_PATH}/assets/icons/icon-192x192.png`,
  `${BASE_PATH}/assets/icons/icon-512x512.png`
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  const allowedCaches = [STATIC_CACHE, DYNAMIC_CACHE];

  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key.startsWith('cariintern-') && !allowedCaches.includes(key))
          .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  if (url.pathname.includes('/api/') || url.searchParams.get('ajax') === 'true') {
    event.respondWith(fetch(request));
    return;
  }

  if (isStaticAsset(url)) {
    event.respondWith(cacheFirst(request));
    return;
  }

  if (request.mode === 'navigate' || url.pathname.endsWith('.php') || url.pathname === `${BASE_PATH}/`) {
    event.respondWith(networkFirstPage(request));
  }
});

self.addEventListener('push', (event) => {
  let payload = {
    title: 'CariinTern',
    body: 'Ada notifikasi baru.',
    icon: `${BASE_PATH}/assets/icons/icon-192x192.png`,
    badge: `${BASE_PATH}/assets/icons/icon-96x96.png`,
    url: `${BASE_PATH}/index.php`,
    vibrate: [200, 100, 200]
  };

  if (event.data) {
    try {
      payload = { ...payload, ...event.data.json() };
    } catch (error) {
      payload.body = event.data.text();
    }
  }

  event.waitUntil(
    self.registration.showNotification(payload.title, {
      body: payload.body,
      icon: payload.icon,
      badge: payload.badge,
      data: { url: payload.url },
      vibrate: payload.vibrate
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = event.notification.data?.url || `${BASE_PATH}/index.php`;

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if ('focus' in client && client.url.includes(BASE_PATH)) {
          client.navigate(targetUrl);
          return client.focus();
        }
      }

      return clients.openWindow(targetUrl);
    })
  );
});

function isStaticAsset(url) {
  return [
    '.css',
    '.js',
    '.png',
    '.jpg',
    '.jpeg',
    '.webp',
    '.svg',
    '.woff',
    '.woff2',
    '.json'
  ].some((extension) => url.pathname.endsWith(extension));
}

async function cacheFirst(request) {
  const cached = await caches.match(request);

  if (cached) {
    return cached;
  }

  const response = await fetch(request);

  if (response && response.ok) {
    const cache = await caches.open(DYNAMIC_CACHE);
    cache.put(request, response.clone());
    trimCache(DYNAMIC_CACHE, MAX_DYNAMIC_ITEMS);
  }

  return response;
}

async function networkFirstPage(request) {
  try {
    const response = await fetch(request);

    if (response && response.ok) {
      const cache = await caches.open(DYNAMIC_CACHE);
      cache.put(request, response.clone());
      trimCache(DYNAMIC_CACHE, MAX_DYNAMIC_ITEMS);
    }

    return response;
  } catch (error) {
    const cached = await caches.match(request);
    return cached || caches.match(OFFLINE_PAGE);
  }
}

async function trimCache(cacheName, maxItems) {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();

  if (keys.length <= maxItems) {
    return;
  }

  await cache.delete(keys[0]);
  return trimCache(cacheName, maxItems);
}
