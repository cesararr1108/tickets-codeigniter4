/* Service worker de notificaciones push (FCM) del panel.
 * Debe servirse desde la raíz pública para cubrir /panel. */
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

// Con el payload "notification" que envía el servidor, FCM ya muestra la
// notificación en segundo plano; aquí solo se maneja el clic.
firebase.messaging();

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const data = event.notification.data || {};
    const url = (data.FCM_MSG && data.FCM_MSG.data && data.FCM_MSG.data.url) || data.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const client of list) {
                if (client.url === url && 'focus' in client) return client.focus();
            }
            return clients.openWindow(url);
        })
    );
});
