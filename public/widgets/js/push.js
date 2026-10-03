/*
 * Notificaciones push (FCM) del widget.
 *
 * Registra el service worker del anfitrión, pide permiso, obtiene el token
 * y lo guarda en la API (POST /fcm-tokens -> tabla t_fcm_tokens) con el correo
 * del usuario. Reemplaza el script FCM que antes vivía en la página anfitriona.
 *
 * Requisito: el anfitrión debe tener un service worker con FCM en segundo plano
 * (por defecto /service-worker.js, configurable con data-sw en tickets.js).
 */
import { apiPost } from "./api.js";

const FIREBASE_VERSION = "12.19.0";

const FIREBASE_CONFIG = {
    apiKey: "AIzaSyCRaATvfSIIBR3DtGri3vL8tMMwj4AzECc",
    authDomain: "fcm-multiroma.firebaseapp.com",
    projectId: "fcm-multiroma",
    storageBucket: "fcm-multiroma.firebasestorage.app",
    messagingSenderId: "221123124298",
    appId: "1:221123124298:web:8f61d4371e9bc3083f9ab3"
};

const VAPID_KEY =
    "BOMCZUnVmsZv4nuhWXALeGC2m5AsebnR3tP15yDPIji51boQPr66bGEjZ_ZHfWbu68aPNJhOiqcNDeLXb6Olfvw";

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export async function iniciarNotificaciones({ apiUrl, perfil, swUrl = "/service-worker.js" }) {

    if (!("serviceWorker" in navigator) || !("Notification" in window) || !("PushManager" in window)) {
        console.warn("[Tickets Widget] Este navegador no soporta notificaciones push.");
        return;
    }

    if (!EMAIL_RE.test(perfil.email || "")) {
        return;
    }

    if (Notification.permission === "denied") {
        return;
    }

    try {
        const base = `https://www.gstatic.com/firebasejs/${FIREBASE_VERSION}`;
        const [{ initializeApp, getApps }, { getMessaging, getToken, onMessage }] = await Promise.all([
            import(`${base}/firebase-app.js`),
            import(`${base}/firebase-messaging.js`)
        ]);

        const registration = await navigator.serviceWorker.register(swUrl);

        if (Notification.permission !== "granted") {
            const permiso = await Notification.requestPermission();

            if (permiso !== "granted") {
                return;
            }
        }

        const app = getApps()[0] || initializeApp(FIREBASE_CONFIG);
        const messaging = getMessaging(app);

        const token = await getToken(messaging, {
            vapidKey: VAPID_KEY,
            serviceWorkerRegistration: registration
        });

        if (!token) {
            console.warn("[Tickets Widget] No se obtuvo token FCM.");
            return;
        }

        // Evita llamar a la API en cada carga si nada cambió.
        const firma = `${perfil.email.toLowerCase()}|${token}`;

        try {
            if (localStorage.getItem("tw-fcm") === firma) {
                escucharPrimerPlano(messaging, onMessage, registration);
                return;
            }
        } catch (e) { /* sin localStorage */ }

        await apiPost(apiUrl, "/fcm-tokens", {
            Email: perfil.email,
            Token: token,
            Navigator: navigator.userAgent.slice(0, 100),
            Companies: perfil.company,
            branches: perfil.branch,
            Rol: perfil.role
        });

        try { localStorage.setItem("tw-fcm", firma); } catch (e) { /* ignorar */ }

        escucharPrimerPlano(messaging, onMessage, registration);
    } catch (error) {
        console.error("[Tickets Widget] Error FCM:", error);
    }
}

// Con la pestaña abierta FCM no muestra nada: se avisa con una notificación local.
function escucharPrimerPlano(messaging, onMessage, registration) {
    onMessage(messaging, payload => {
        const n = payload.notification || {};
        const d = payload.data || {};

        registration.showNotification(n.title || d.title || "Notificación", {
            body: n.body || d.body || "",
            data: { url: d.url || d.link || payload.fcmOptions?.link || "/" }
        });
    });
}
