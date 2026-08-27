/**
 * Service worker de FichaTime.
 * Gestiona las notificaciones push (llegan con la app cerrada) y una
 * pantalla de respaldo cuando no hay conexión.
 */

const CACHE_VERSION = 'fichatime-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_VERSION)
            .then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

// Solo interceptamos navegaciones para poder mostrar la pantalla offline.
self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => caches.match(OFFLINE_URL)),
    );
});

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch {
        payload = { title: 'FichaTime', body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'FichaTime';
    const options = {
        body: payload.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/badge-72.png',
        tag: payload.tag || 'fichatime',
        renotify: true,
        data: {
            url: payload.url || '/dashboard',
            id: payload.id || null,
            category: payload.category || 'general',
        },
        // iOS ignora buena parte de estas opciones, pero no molestan.
        vibrate: [120, 60, 120],
        timestamp: Date.now(),
    };

    event.waitUntil(
        self.registration.showNotification(title, options).then(() => {
            if (self.navigator && 'setAppBadge' in self.navigator) {
                self.navigator.setAppBadge().catch(() => {});
            }
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/dashboard';

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                for (const client of clientList) {
                    if ('focus' in client) {
                        client.navigate(targetUrl).catch(() => {});
                        return client.focus();
                    }
                }

                if (self.clients.openWindow) {
                    return self.clients.openWindow(targetUrl);
                }

                return undefined;
            })
            .then(() => {
                if (self.navigator && 'clearAppBadge' in self.navigator) {
                    self.navigator.clearAppBadge().catch(() => {});
                }
            }),
    );
});

/**
 * El navegador puede rotar la suscripción por su cuenta. Cuando pasa,
 * la volvemos a registrar en el servidor para no perder al usuario.
 */
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(
        (async () => {
            try {
                const response = await fetch('/api/push/config', { credentials: 'same-origin' });
                const config = await response.json();

                if (!config.public_key) return;

                const subscription = await self.registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: config.public_key,
                });

                await fetch('/api/push/subscribe', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(subscription.toJSON()),
                });
            } catch {
                // Sin sesión activa no podemos re-suscribir; se hará al abrir la app.
            }
        })(),
    );
});
