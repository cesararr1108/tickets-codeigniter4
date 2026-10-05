/*
 * Notificaciones push (FCM) del widget.
 *
 * - Si el usuario ya dio permiso: registra el token en silencio.
 * - Si aún no lo ha decidido: muestra el botón "Activar notificaciones" en la
 *   cabecera del widget; al pulsarlo se pide el permiso (los navegadores,
 *   sobre todo Safari/iOS, exigen que sea por un clic) y se guarda el token
 *   en la API (POST /fcm-tokens -> tabla t_fcm_tokens) con el correo del usuario.
 *
 * Requisito: el anfitrión debe tener un service worker con FCM en segundo plano
 * (por defecto ../../service-worker.js, configurable con data-sw en tickets.js).
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

const IOS = /iphone|ipad|ipod/i.test(navigator.userAgent)
    || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
const STANDALONE = window.navigator.standalone === true
    || window.matchMedia?.("(display-mode: standalone)").matches;

const IOS_HELP = "Para recibir notificaciones en iPhone/iPad:\n\n"
    + "1. Abre esta página en Safari.\n"
    + "2. Toca Compartir y elige \"Añadir a pantalla de inicio\".\n"
    + "3. Abre la app desde el icono nuevo y pulsa de nuevo \"Activar notificaciones\".\n\n"
    + "Requiere iOS 16.4 o superior.";

export async function iniciarNotificaciones({ apiUrl, perfil, root, swUrl = "../../service-worker.js" }) {

    const button = root?.getElementById("ticketPush");
    const soportado = "serviceWorker" in navigator && "Notification" in window && "PushManager" in window;

    if (!EMAIL_RE.test(perfil.email || "")) {
        return;
    }

    if (!soportado && !(IOS && !STANDALONE)) {
        console.warn("[Tickets Widget] Este navegador no soporta notificaciones push.");
        return;
    }

    if (soportado && Notification.permission === "denied") {
        return;
    }

    // Permiso ya concedido: registro silencioso.
    if (soportado && Notification.permission === "granted") {
        activar(false).catch(error => console.error("[Tickets Widget] Error FCM:", error));
        return;
    }

    // Permiso pendiente: botón en la cabecera.
    if (!button) {
        return;
    }

    button.hidden = false;
    button.addEventListener("click", async () => {

        if (!soportado) {
            alert(IOS_HELP);
            return;
        }

        // requestPermission debe llamarse directo desde el clic.
        const permiso = await Notification.requestPermission();

        if (permiso !== "granted") {
            if (permiso === "denied") {
                button.hidden = true;
                alert("Bloqueaste las notificaciones. Puedes activarlas desde los ajustes del sitio en tu navegador.");
            }
            return;
        }

        try {
            await activar(true);
            button.hidden = true;
        } catch (error) {
            console.error("[Tickets Widget] Error FCM:", error);
            alert("No se pudieron activar las notificaciones.");
        }
    });

    async function activar(mostrarAviso) {
        const base = `https://www.gstatic.com/firebasejs/${FIREBASE_VERSION}`;
        const [{ initializeApp, getApps }, { getMessaging, getToken, onMessage }] = await Promise.all([
            import(`${base}/firebase-app.js`),
            import(`${base}/firebase-messaging.js`)
        ]);

        const registration = await navigator.serviceWorker.register(swUrl);
        await navigator.serviceWorker.ready;

        const messaging = getMessaging(getApps()[0] || initializeApp(FIREBASE_CONFIG));

        const token = await getToken(messaging, {
            vapidKey: VAPID_KEY,
            serviceWorkerRegistration: registration
        });

        if (!token) {
            throw new Error("No se obtuvo token FCM.");
        }

        // Evita llamar a la API en cada carga si nada cambió.
        const firma = `${perfil.email.toLowerCase()}|${perfil.company}|${perfil.branch}|${token}`;
        let guardado = false;

        try { guardado = localStorage.getItem("tw-fcm") === firma; } catch (e) { /* sin localStorage */ }

        if (!guardado) {
            await apiPost(apiUrl, "/fcm-tokens", {
                Email: perfil.email,
                Token: token,
                Navigator: navigator.userAgent.slice(0, 100),
                Companies: perfil.company,
                branches: perfil.branch,
                Rol: perfil.role
            });

            try { localStorage.setItem("tw-fcm", firma); } catch (e) { /* ignorar */ }
        }

        // Con la pestaña abierta FCM no muestra nada: aviso local.
        onMessage(messaging, payload => {
            const n = payload.notification || {};
            const d = payload.data || {};

            registration.showNotification(n.title || d.title || "Notificación", {
                body: n.body || d.body || "",
                data: { url: d.url || d.link || payload.fcmOptions?.link || "/" }
            });
        });

        if (mostrarAviso) {
            alert("Notificaciones activadas.");
        }
    }
}
