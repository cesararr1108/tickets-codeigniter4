/* Service worker de notificaciones push (FCM) del panel.
 * Se sirve desde la raíz pública para cubrir /panel. */
importScripts('https://www.gstatic.com/firebasejs/12.19.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/12.19.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: 'AIzaSyCRaATvfSIIBR3DtGri3vL8tMMwj4AzECc',
    authDomain: 'fcm-multiroma.firebaseapp.com',
    projectId: 'fcm-multiroma',
    storageBucket: 'fcm-multiroma.firebasestorage.app',
    messagingSenderId: '221123124298',
    appId: '1:221123124298:web:8f61d4371e9bc3083f9ab3'
});

// El servidor envía el payload "notification": FCM lo muestra solo en segundo plano.
// iOS exige que cada push muestre una notificación visible; por eso no se usa data-only.
firebase.messaging();

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const data = event.notification.data || {};
    const url = (data.FCM_MSG && data.FCM_MSG.data && data.FCM_MSG.data.url) || data.url || self.registration.scope;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const client of list) {
                if ('focus' in client) {
                    client.focus();
                    if ('navigate' in client) return client.navigate(url);
                    return;
                }
            }
            return clients.openWindow(url);
        })
    );
});
