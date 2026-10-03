import { initializeApp } from 'https://www.gstatic.com/firebasejs/12.19.0/firebase-app.js';
import { getMessaging, getToken, onMessage } from 'https://www.gstatic.com/firebasejs/12.19.0/firebase-messaging.js';

// Activa las notificaciones push del panel. Configuración en <meta name="fcm-*">.
const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';
const button = document.querySelector('[data-enable-push]');

const config = JSON.parse(meta('fcm-config') || '{}');
const vapidKey = meta('fcm-vapid-key');
const swUrl = meta('fcm-sw-url');
const registerUrl = meta('fcm-register-url');

const supported = 'serviceWorker' in navigator && 'Notification' in window && 'PushManager' in window;

if (!supported || !config.projectId || !vapidKey) {
    button?.remove();
} else {
    const messaging = getMessaging(initializeApp(config));

    const csrf = () => ({
        name: document.querySelector('meta[name="csrf-token-name"]').content,
        value: document.querySelector('meta[name="csrf-token"]').content
    });

    async function enable() {
        const registration = await navigator.serviceWorker.register(swUrl);
        const token = await getToken(messaging, { vapidKey, serviceWorkerRegistration: registration });
        if (!token) return false;

        // Evita volver a enviar el mismo token en cada página.
        try {
            if (localStorage.getItem('panel-fcm-token') === token) return true;
        } catch (e) {}

        const body = new URLSearchParams({ token });
        body.set(csrf().name, csrf().value);

        const response = await fetch(registerUrl, {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json().catch(() => ({}));
        if (data.csrf) document.querySelector('meta[name="csrf-token"]').content = data.csrf;
        if (!response.ok) return false;

        try { localStorage.setItem('panel-fcm-token', token); } catch (e) {}
        return true;
    }

    // Con la pestaña abierta FCM no muestra nada; se avisa con una notificación local.
    onMessage(messaging, async (payload) => {
        const registration = await navigator.serviceWorker.getRegistration(swUrl);
        const { title, body } = payload.notification || {};
        registration?.showNotification(title || 'Mesa de Ayuda', {
            body,
            data: { url: payload.data?.url }
        });
    });

    const syncButton = () => {
        if (!button) return;
        button.hidden = Notification.permission === 'granted' || Notification.permission === 'denied';
    };

    if (Notification.permission === 'granted') {
        enable().catch((error) => console.warn('Push:', error));
    }
    syncButton();

    button?.addEventListener('click', async () => {
        const permission = await Notification.requestPermission();
        syncButton();
        if (permission !== 'granted') return;
        try {
            await enable();
        } catch (error) {
            console.warn('Push:', error);
            alert('No se pudieron activar las notificaciones.');
        }
    });
}
