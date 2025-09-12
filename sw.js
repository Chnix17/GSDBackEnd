'use strict';

self.addEventListener('install', (event) => {
  // Activate immediately so test page works right away
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  try {
    const data = event.data ? event.data.json() : {};
    const title = data.title || 'Notification';
    const body = data.body || 'You have a new notification';
    const options = {
      body,
      icon: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f514.svg',
      badge: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/1f514.svg',
      data: data.data || {},
      requireInteraction: false,
    };

    event.waitUntil(self.registration.showNotification(title, options));
  } catch (err) {
    event.waitUntil(self.registration.showNotification('Notification', {
      body: 'Received a message',
    }));
  }
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification && event.notification.data && event.notification.data.url) || '/coc/gsd/';

  event.waitUntil((async () => {
    const allClients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of allClients) {
      try {
        const clientUrl = new URL(client.url);
        if (clientUrl.pathname === url || clientUrl.pathname === new URL(url, clientUrl.origin).pathname) {
          await client.focus();
          return;
        }
      } catch {}
    }
    await self.clients.openWindow(url);
  })());
});
