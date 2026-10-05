// Notificaciones push del panel (FCM). Configuración en <meta name="fcm-*">.
// En iPhone/iPad (iOS 16.4+) las push solo funcionan con la app instalada en
// la pantalla de inicio; el botón guía al usuario y luego pide el permiso.
const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';
const button = document.querySelector('[data-enable-push]');

const config = JSON.parse(meta('fcm-config') || '{}');
const vapidKey = meta('fcm-vapid-key');
const swUrl = meta('fcm-sw-url');
const registerUrl = meta('fcm-register-url');
const userEmail = meta('fcm-user').toLowerCase();

// Sonido de los avisos (solo suena con el panel abierto; en segundo plano lo decide el sistema).
const soundUrl = meta('fcm-sound');
const soundButton = document.querySelector('[data-toggle-sound]');
const soundEnabled = () => {
    try { return localStorage.getItem('panel-sound') !== 'off'; } catch (e) { return true; }
};
const syncSoundLabel = () => {
    const label = soundButton?.querySelector('[data-sound-label]');
    if (label) label.textContent = soundEnabled() ? 'Sonido de avisos: activado' : 'Sonido de avisos: silenciado';
};
const playSound = () => {
    if (!soundUrl || !soundEnabled()) return;
    // Los navegadores bloquean el audio hasta que el usuario interactúa con la página.
    new Audio(soundUrl).play().catch(() => {});
};

syncSoundLabel();
soundButton?.addEventListener('click', () => {
    try { localStorage.setItem('panel-sound', soundEnabled() ? 'off' : 'on'); } catch (e) {}
    syncSoundLabel();
    playSound();
});

const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const isStandalone = window.navigator.standalone === true
    || window.matchMedia('(display-mode: standalone)').matches;
const canPush = 'serviceWorker' in navigator && 'Notification' in window && 'PushManager' in window;

const IOS_HELP = 'Para recibir notificaciones en iPhone/iPad:\n\n'
    + '1. Abre este panel en Safari.\n'
    + '2. Toca el botón Compartir (cuadro con flecha hacia arriba).\n'
    + '3. Elige "Añadir a pantalla de inicio" y confirma.\n'
    + '4. Abre "Mesa de Ayuda" desde el icono nuevo e inicia sesión.\n'
    + '5. Pulsa de nuevo "Activar notificaciones" y acepta el permiso.\n\n'
    + 'Requiere iOS 16.4 o superior.';

let messagingPromise = null;

function loadMessaging() {
    messagingPromise ??= (async () => {
        const base = 'https://www.gstatic.com/firebasejs/12.19.0';
        const [{ initializeApp, getApps }, fm] = await Promise.all([
            import(`${base}/firebase-app.js`),
            import(`${base}/firebase-messaging.js`)
        ]);
        const messaging = fm.getMessaging(getApps()[0] || initializeApp(config));
        return { fm, messaging };
    })();
    return messagingPromise;
}

const csrf = () => ({
    name: document.querySelector('meta[name="csrf-token-name"]').content,
    value: document.querySelector('meta[name="csrf-token"]').content
});

async function enable() {
    const registration = await navigator.serviceWorker.register(swUrl);
    const { fm, messaging } = await loadMessaging();
    const token = await fm.getToken(messaging, { vapidKey, serviceWorkerRegistration: registration });
    if (!token) return false;

    // Solo con fcm.debug = true en .env (desarrollo): muestra el token de este navegador.
    if (meta('fcm-debug')) console.info('[Push] token FCM de este navegador:', token);

    // Aviso con la pestaña abierta (FCM no lo muestra por sí solo). Debe registrarse en
    // CADA carga de página, antes de decidir si el token ya estaba guardado.
    fm.onMessage(messaging, (payload) => {
        playSound();
        const { title, body } = payload.notification || {};
        registration.showNotification(title || 'Mesa de Ayuda', {
            body,
            icon: meta('fcm-icon'),
            data: { url: payload.data?.url }
        });
    });

    // El token es del navegador, no del usuario: se recuerda junto con el correo para volver
    // a registrarlo cuando entra otro usuario en el mismo navegador.
    const signature = `${userEmail}|${token}`;

    try {
        if (localStorage.getItem('panel-fcm-token-v4') === signature) return true;
    } catch (e) {}

    const body = new URLSearchParams({ token });
    body.set(csrf().name, csrf().value);

    const response = await fetch(registerUrl, {
        method: 'POST',
        body,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await response.json().catch(() => ({}));
    if (data.csrf) {
        // El servidor rota el token CSRF en cada petición: se actualiza en toda la página
        // para que los formularios ya pintados no fallen con "The action you requested is not allowed".
        const { name } = csrf();
        document.querySelector('meta[name="csrf-token"]').content = data.csrf;
        document.querySelectorAll(`input[name="${name}"]`).forEach((input) => { input.value = data.csrf; });
    }
    if (!response.ok) return false;

    try { localStorage.setItem('panel-fcm-token-v4', signature); } catch (e) {}

    return true;
}

function syncButton() {
    if (!button) return;
    const denied = canPush && Notification.permission === 'denied';
    const granted = canPush && Notification.permission === 'granted';
    button.hidden = granted || denied;
}

if (canPush && Notification.permission === 'granted' && config.projectId && vapidKey) {
    enable().catch((error) => console.warn('Push:', error));
}

syncButton();

button?.addEventListener('click', async () => {
    // iOS fuera de la app instalada: Safari no expone push, solo se puede guiar.
    if (isIOS && (!isStandalone || !canPush)) {
        alert(IOS_HELP);
        return;
    }

    if (!canPush) {
        alert('Este navegador no soporta notificaciones push.');
        return;
    }

    // requestPermission debe llamarse directo desde el clic (exigencia de iOS).
    const permission = await Notification.requestPermission();
    syncButton();
    if (permission !== 'granted') {
        if (permission === 'denied') alert('Bloqueaste las notificaciones. Actívalas en los ajustes del navegador o de la app.');
        return;
    }

    try {
        if (!(await enable())) throw new Error('sin token');
        alert('Notificaciones activadas.');
    } catch (error) {
        console.warn('Push:', error);
        alert('No se pudieron activar las notificaciones.');
    }
});
