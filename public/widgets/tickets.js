/*
 * Tickets Widget - punto de entrada
 *
 * <div id="tickets-widget"></div>
 * <script
 *   src="https://200.122.206.204:8081/widgets/tickets.js"
 *   data-container="tickets-widget"
 *   data-api="https://200.122.206.204:8081/api"
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

    const apiUrl =
        currentScript?.dataset.api ||
        "https://200.122.206.204:8081/api";

    const version =
        currentScript?.dataset.version || "";

    // Carpeta donde vive tickets.js (ej: https://host/widgets/).
    const baseUrl = new URL("./", currentScript?.src || location.href);

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
        .then(module => module.mountWidget(container, { apiUrl, version }))
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
