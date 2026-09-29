/*
 * Tickets Widget - punto de entrada
 *
 * <div id="tickets-widget"></div>
 * <script
 *   src="https://200.122.206.204:8081/widgets/tickets.js"
 *   data-container="tickets-widget"
 *   data-token="<token firmado por tu servidor>"
 *   data-version="1">
 * </script>
 *
 * Este archivo solo carga js/app.js. El HTML está en templates/*.html
 * y el CSS en css/widget.css (todo relativo a la ubicación de este script).
 */

(() => {
    const currentScript = document.currentScript;

    const containerId =
        currentScript?.dataset.container || "tickets-widget";

    const version =
        currentScript?.dataset.version || "";

    // Token firmado por el servidor del sitio anfitrión (ver README).
    const token =
        currentScript?.dataset.token || "";

    // URL del anfitrión que devuelve {"token": "..."} (renueva el token).
    const tokenUrl =
        currentScript?.dataset.tokenUrl || "";

    // Carpeta donde vive tickets.js (ej: https://host/widgets/).
    const baseUrl = new URL("./", currentScript?.src || location.href);

    // API pública del widget. Por defecto: /widget en el mismo servidor.
    const apiUrl =
        currentScript?.dataset.api ||
        new URL("../widget", baseUrl).href;

    if (!token && !tokenUrl) {
        console.warn(
            "[Tickets Widget] Falta data-token o data-token-url: la API rechazará las peticiones."
        );
    }

    const container = document.getElementById(containerId);

    if (!container) {
        console.error(
            "[Tickets Widget] No se encontró el contenedor:",
            containerId
        );
        return;
    }

    // Evita inicializar dos veces el mismo widget.
    if (container.shadowRoot || container.dataset.twLoading) {
        console.warn("[Tickets Widget] Ya está inicializado.");
        return;
    }

    container.dataset.twLoading = "1";

    const appUrl = new URL(
        "js/app.js" + (version ? "?v=" + encodeURIComponent(version) : ""),
        baseUrl
    );

    import(appUrl.href)
        .then(module => module.mountWidget(container, { apiUrl, version, token, tokenUrl }))
        .then(widget => {
            // API pública opcional.
            window.TicketsWidget = { ...widget, api: apiUrl };
        })
        .catch(error => {
            console.error("[Tickets Widget] No se pudo iniciar:", error);
        })
        .finally(() => {
            delete container.dataset.twLoading;
        });
})();
