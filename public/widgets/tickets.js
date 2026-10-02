/*
 * Tickets Widget - punto de entrada
 *
 * <div id="tickets-widget"></div>
 * <script
 *   src="https://api.pwmultiroma.com/widgets/tickets.js"
 *   data-container="tickets-widget"
 *   data-api="https://api.pwmultiroma.com/api"
 *   data-version="1"
 *   data-company="01" data-branch="001"          (opcionales: datos del
 *   data-email="ana@empresa.com"                  usuario que tiene sesión
 *   data-name="Ana Pérez" data-user="aperez"      en el anfitrión)
 *   data-rol="Administrador" data-area="Comercial"   (solo "Administrador"
 *                                   puede cambiar compañía y sucursal; los
 *                                   demás quedan fijos en data-company/branch)
 *   data-phone="3001234567">
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
        "https://api.pwmultiroma.com/api";

    const version =
        currentScript?.dataset.version || "";

    // Carpeta donde vive tickets.js (ej: https://host/widgets/).
    const baseUrl = new URL("./", currentScript?.src || location.href);

  

    // Datos del usuario que envía el anfitrión. Se usan para preseleccionar
    // compañía/sucursal, llenar el correo y los campos automáticos
    // (Solicitante, Área, Sede) de los formularios adicionales.
    const perfil = {
        company: currentScript?.dataset.company || "",
        branch:  currentScript?.dataset.branch  || "",
        email:   currentScript?.dataset.email   || "",
        name:    currentScript?.dataset.name    || "",
        phone:   currentScript?.dataset.phone   || "",
        user:    currentScript?.dataset.user    || "",
        role:    currentScript?.dataset.rol || currentScript?.dataset.role || "",
        area:    currentScript?.dataset.area    || "",
        //lock:    currentScript?.dataset.lock === "true"
    };


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
        .then(module => module.mountWidget(container, { apiUrl, version ,perfil}))
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
