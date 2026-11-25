/* Service worker Portal Kos: khusus Web Push, tanpa cache HTML. */
self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (err) {
    data = { title: 'Portal Kos Sedhah Ayu', body: event.data ? event.data.text() : '' };
  }
  const title = data.title || 'Portal Kos Sedhah Ayu';
  const options = {
    body: data.body || 'Ada informasi baru dari pengelola kos.',
    tag: data.tag || 'portal-kos',
    data: { url: data.url || '/' },
    requireInteraction: !!data.requireInteraction,
    vibrate: data.requireInteraction ? [200, 100, 200] : [100],
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = (event.notification.data && event.notification.data.url) || '/';
  const absoluteUrl = new URL(targetUrl, self.location.origin).href;

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if ('focus' in client) {
          if ('navigate' in client) {
            client.navigate(absoluteUrl).catch(() => {});
          }
          return client.focus();
        }
      }
      return self.clients.openWindow(absoluteUrl);
    })
  );
});
