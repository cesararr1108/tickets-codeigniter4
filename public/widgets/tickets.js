/*
 * Tickets Widget - punto de entrada
 * Uso:
 *
 * <div id="tickets-widget"></div>
 * <script
 *   src="https://200.122.206.204:8081/widget/tickets.js"
 *   data-container="tickets-widget"
 *   data-api="https://200.122.206.204:8081/api">
 * </script>
 */

(() => {
    const currentScript = document.currentScript;

    const containerId =
        currentScript?.dataset.container || "tickets-widget";

    const apiUrl =
        currentScript?.dataset.api ||
        "https://200.122.206.204:8081/api";

    const container = document.getElementById(containerId);

    if (!container) {
        console.error(
            "[Tickets Widget] No se encontró el contenedor:",
            containerId
        );
        return;
    }

    // Evita inicializar dos veces el mismo widget.
    if (container.shadowRoot) {
        console.warn("[Tickets Widget] Ya está inicializado.");
        return;
    }

    const shadow = container.attachShadow({ mode: "open" });

    /*
     * Todo el HTML y CSS queda dentro del Shadow DOM.
     * De esta forma no afecta los estilos de la página anfitriona.
     */
    shadow.innerHTML = `
        <style>
            :host {
                all: initial;
            }

            * {
                box-sizing: border-box;
            }

            .tw-root {
                font-family:
                    Inter,
                    ui-sans-serif,
                    system-ui,
                    -apple-system,
                    BlinkMacSystemFont,
                    "Segoe UI",
                    sans-serif;
                color: #172033;
            }

            .tw-fab {
                position: fixed;
                right: 24px;
                bottom: 24px;
                width: 58px;
                height: 58px;
                border: 0;
                border-radius: 50%;
                background: #2563eb;
                color: white;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow:
                    0 10px 25px rgba(37, 99, 235, .28),
                    0 4px 8px rgba(0, 0, 0, .12);
                transition: .2s ease;
                z-index: 2147483000;
            }

            .tw-fab:hover {
                transform: translateY(-2px) scale(1.03);
                background: #1d4ed8;
            }

            .tw-fab svg {
                width: 25px;
                height: 25px;
            }

            .tw-overlay {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, .42);
                backdrop-filter: blur(3px);
                display: none;
                align-items: flex-end;
                justify-content: flex-end;
                padding: 24px;
                z-index: 999;
                heigth :90hv
            }

            .tw-overlay.tw-open {
                display: flex;
            }

            .tw-modal {
                width:100%;
                min-height: calc(100vh - 48px);
                max-height: calc(100vh - 48px);
                background: white;
                border-radius: 20px;
                overflow: hidden;
                box-shadow:
                    0 30px 80px rgba(15, 23, 42, .28),
                    0 8px 20px rgba(15, 23, 42, .12);
                display: flex;
                flex-direction: column;
                animation: tw-slide-up .2s ease-out;
                margin-right:70px;
                z-index:999999999999999999
            }

            @keyframes tw-slide-up {
                from {
                    opacity: 0;
                    transform: translateY(15px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .tw-header {
                padding: 20px 22px 16px;
                border-bottom: 1px solid #e5e7eb;
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 15px;
            }

            .tw-title {
                margin: 0;
                font-size: 20px;
                font-weight: 700;
                color: #111827;
            }

            .tw-subtitle {
                margin: 5px 0 0;
                color: #6b7280;
                font-size: 13px;
            }

            .tw-close {
                width: 34px;
                height: 34px;
                border: 0;
                border-radius: 9px;
                background: #f3f4f6;
                color: #4b5563;
                cursor: pointer;
                font-size: 20px;
                line-height: 1;
            }

            .tw-close:hover {
                background: #e5e7eb;
            }

            .tw-tabs {
                display: flex;
                gap: 5px;
                padding: 10px 18px 0;
                border-bottom: 1px solid #e5e7eb;
            }

            .tw-tab {
                flex: 1;
                border: 0;
                background: transparent;
                padding: 11px 10px;
                color: #6b7280;
                cursor: pointer;
                font-size: 14px;
                font-weight: 600;
                border-bottom: 2px solid transparent;
            }

            .tw-tab.tw-active {
                color: #2563eb;
                border-bottom-color: #2563eb;
            }

            .tw-content {
                overflow-y: auto;
                padding: 20px;
            }

            .tw-panel {
                display: none;
            }

            .tw-panel.tw-active {
                display: block;
            }

            .tw-field {
                margin-bottom: 15px;
            }

            .tw-label {
                display: block;
                margin-bottom: 7px;
                color: #374151;
                font-size: 13px;
                font-weight: 600;
            }

            .tw-input,
            .tw-select,
            .tw-textarea {
                width: 100%;
                border: 1px solid #d1d5db;
                border-radius: 10px;
                background: #fff;
                color: #111827;
                font-size: 14px;
                outline: none;
                transition: .15s ease;
            }

            .tw-input,
            .tw-select {
                height: 43px;
                padding: 0 12px;
            }

            .tw-textarea {
                min-height: 115px;
                resize: vertical;
                padding: 11px 12px;
            }

            .tw-input:focus,
            .tw-select:focus,
            .tw-textarea:focus {
                border-color: #60a5fa;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
            }

            .tw-input:disabled,
            .tw-select:disabled {
                background: #f3f4f6;
                color: #9ca3af;
                cursor: not-allowed;
            }

            .tw-file {
                width: 100%;
                border: 1px dashed #cbd5e1;
                border-radius: 10px;
                padding: 12px;
                background: #f8fafc;
                font-size: 13px;
            }

            .tw-actions {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                padding-top: 5px;
            }

            .tw-btn {
                min-height: 42px;
                border: 0;
                border-radius: 10px;
                padding: 0 17px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: .15s ease;
            }

            .tw-btn-primary {
                background: #2563eb;
                color: white;
            }

            .tw-btn-primary:hover {
                background: #1d4ed8;
            }

            .tw-btn-primary:disabled {
                opacity: .6;
                cursor: wait;
            }

            .tw-btn-secondary {
                background: #f3f4f6;
                color: #374151;
            }

            .tw-alert {
                display: none;
                margin-bottom: 15px;
                padding: 11px 12px;
                border-radius: 9px;
                font-size: 13px;
            }

            .tw-alert.tw-show {
                display: block;
            }

            .tw-alert-error {
                background: #fef2f2;
                color: #b91c1c;
                border: 1px solid #fecaca;
            }

            .tw-alert-success {
                background: #f0fdf4;
                color: #15803d;
                border: 1px solid #bbf7d0;
            }

            .tw-empty {
                text-align: center;
                padding: 35px 15px;
                color: #6b7280;
                font-size: 14px;
            }

            .tw-ticket {
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 14px;
                margin-bottom: 10px;
            }

            .tw-ticket-title {
                font-weight: 700;
                color: #111827;
                font-size: 14px;
            }

            .tw-ticket-meta {
                color: #6b7280;
                font-size: 12px;
                margin-top: 5px;
            }

            .tw-status {
                display: inline-flex;
                margin-top: 9px;
                padding: 4px 8px;
                border-radius: 999px;
                background: #eff6ff;
                color: #1d4ed8;
                font-size: 11px;
                font-weight: 700;
            }

            @media (max-width: 640px) {
                .tw-overlay {
                    padding: 0;
                    align-items: flex-end;
                }

                .tw-modal {
                    width: 100%;
                    max-height: 92vh;
                    border-radius: 20px 20px 0 0;
                }

                .tw-fab {
                    right: 18px;
                    bottom: 18px;
                }
            }
        </style>

        <div class="tw-root">

            <button
                type="button"
                id="ticketFab"
                class="tw-fab"
                aria-label="Abrir soporte">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2">
                    <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>
                    <path d="M8 10h8"/>
                    <path d="M8 14h5"/>
                </svg>
            </button>

            <div
                id="ticketOverlay"
                class="tw-overlay"
                aria-hidden="true">

                <section
                    class="tw-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="ticketTitle">

                    <header class="tw-header">
                        <div>
                            <h2 id="ticketTitle" class="tw-title">
                                Centro de soporte
                            </h2>

                            <p class="tw-subtitle">
                                Crea y consulta tus solicitudes
                            </p>
                        </div>

                        <button
                            type="button"
                            id="ticketClose"
                            class="tw-close"
                            aria-label="Cerrar">
                            ×
                        </button>
                    </header>

                    <nav class="tw-tabs">
                        <button
                            type="button"
                            class="tw-tab tw-active"
                            data-tab="new">
                            Nuevo ticket
                        </button>

                        <button
                            type="button"
                            class="tw-tab"
                            data-tab="mine">
                            Mis tickets
                        </button>
                    </nav>

                    <div class="tw-content">

                        <div
                            id="ticketAlert"
                            class="tw-alert">
                        </div>

                        <!-- NUEVO TICKET -->
                        <div
                            id="panelNew"
                            class="tw-panel tw-active">

                            <form id="ticketForm">

                                <div class="tw-field">
                                    <label class="tw-label">
                                        Compañía
                                    </label>

                                    <select
                                        id="ticketCompany"
                                        class="tw-select"
                                        required>
                                        <option value="">
                                            Cargando compañías...
                                        </option>
                                    </select>
                                </div>

                                <div class="tw-field">
                                    <label class="tw-label">
                                        Sucursal
                                    </label>

                                    <select
                                        id="ticketBranch"
                                        class="tw-select"
                                        disabled
                                        required>
                                        <option value="">
                                            Selecciona primero una compañía
                                        </option>
                                    </select>
                                </div>

                                <div class="tw-field">
                                    <label class="tw-label">
                                        Categoría
                                    </label>

                                    <select
                                        id="ticketCategory"
                                        class="tw-select"
                                        required>
                                        <option value="">
                                            Selecciona una categoría
                                        </option>
                                    </select>
                                </div>

                                <div class="tw-field">
                                    <label class="tw-label">
                                        Subcategoría
                                    </label>

                                    <select
                                        id="ticketSubcategory"
                                        class="tw-select"
                                        disabled>
                                        <option value="">
                                            Selecciona primero una categoría
                                        </option>
                                    </select>
                                </div>

                                <div class="tw-field">
                                    <label class="tw-label">
                                        Descripción
                                    </label>

                                    <textarea
                                        id="ticketDescription"
                                        class="tw-textarea"
                                        placeholder="Describe detalladamente tu solicitud..."
                                        required></textarea>
                                </div>

                                <div class="tw-field">
                                    <label class="tw-label">
                                        Archivo adjunto
                                    </label>

                                    <input
                                        id="ticketFile"
                                        class="tw-file"
                                        type="file">
                                </div>

                                <div class="tw-actions">
                                    <button
                                        type="reset"
                                        class="tw-btn tw-btn-secondary"
                                        id="ticketReset">
                                        Limpiar
                                    </button>

                                    <button
                                        type="submit"
                                        class="tw-btn tw-btn-primary"
                                        id="ticketSubmit">
                                        Crear ticket
                                    </button>
                                </div>

                            </form>
                        </div>

                        <!-- MIS TICKETS -->
                        <div
                            id="panelMine"
                            class="tw-panel">

                            <div id="ticketsList">
                                <div class="tw-empty">
                                    Abre esta pestaña para consultar tus tickets.
                                </div>
                            </div>

                            <div class="tw-actions">
                                <button
                                    type="button"
                                    id="refreshTickets"
                                    class="tw-btn tw-btn-secondary">
                                    Actualizar
                                </button>
                            </div>

                        </div>

                    </div>
                </section>
            </div>
        </div>
    `;

    const fab = shadow.getElementById("ticketFab");
    const overlay = shadow.getElementById("ticketOverlay");
    const close = shadow.getElementById("ticketClose");
    const form = shadow.getElementById("ticketForm");
    const company = shadow.getElementById("ticketCompany");
    const tabs = shadow.querySelectorAll(".tw-tab");
    const alertBox = shadow.getElementById("ticketAlert");

    async function openModal() {
        overlay.classList.add("tw-open");
        overlay.setAttribute("aria-hidden", "false");

        if (!company.dataset.loaded) {
            await cargarCompanias(shadow, apiUrl);
            company.dataset.loaded = "1";
        }
    }

    function closeModal() {
        overlay.classList.remove("tw-open");
        overlay.setAttribute("aria-hidden", "true");
    }

    function showAlert(message, type = "error") {
        alertBox.textContent = message;
        alertBox.className =
            "tw-alert tw-show " +
            (type === "success"
                ? "tw-alert-success"
                : "tw-alert-error");
    }

    function hideAlert() {
        alertBox.className = "tw-alert";
        alertBox.textContent = "";
    }

    fab.addEventListener("click", openModal);
    close.addEventListener("click", closeModal);

    overlay.addEventListener("click", event => {
        if (event.target === overlay) {
            closeModal();
        }
    });

    tabs.forEach(tab => {
        tab.addEventListener("click", async () => {
            tabs.forEach(item =>
                item.classList.remove("tw-active")
            );

            tab.classList.add("tw-active");

            const isNew =
                tab.dataset.tab === "new";

            shadow
                .getElementById("panelNew")
                .classList.toggle("tw-active", isNew);

            shadow
                .getElementById("panelMine")
                .classList.toggle("tw-active", !isNew);

            hideAlert();

            if (!isNew) {
                await cargarMisTickets(
                    shadow,
                    apiUrl
                );
            }
        });
    });

    company.addEventListener("change", async () => {
        hideAlert();

        await cargarBranches(
            shadow,
            apiUrl,
            company.value
        );
    });

    form.addEventListener("submit", async event => {
        event.preventDefault();
        hideAlert();

        const submit =
            shadow.getElementById("ticketSubmit");

        const data = {
            company_id:
                shadow.getElementById("ticketCompany").value,

            branch_id:
                shadow.getElementById("ticketBranch").value,

            category_id:
                shadow.getElementById("ticketCategory").value,

            subcategory_id:
                shadow.getElementById("ticketSubcategory").value,

            description:
                shadow.getElementById("ticketDescription").value.trim()
        };

        if (!data.company_id) {
            showAlert("Selecciona una compañía.");
            return;
        }

        if (!data.branch_id) {
            showAlert("Selecciona una sucursal.");
            return;
        }

        if (!data.category_id) {
            showAlert("Selecciona una categoría.");
            return;
        }

        if (!data.description) {
            showAlert("Ingresa una descripción.");
            return;
        }

        try {
            submit.disabled = true;
            submit.textContent = "Creando...";

            await crearTicket(
                apiUrl,
                data,
                shadow.getElementById("ticketFile").files[0]
            );

            showAlert(
                "El ticket fue creado correctamente.",
                "success"
            );

            form.reset();

            shadow.getElementById("ticketBranch").disabled = true;
            shadow.getElementById("ticketSubcategory").disabled = true;

        } catch (error) {
            console.error(
                "[Tickets Widget] Error creando ticket:",
                error
            );

            showAlert(
                error.message ||
                "No fue posible crear el ticket."
            );

        } finally {
            submit.disabled = false;
            submit.textContent = "Crear ticket";
        }
    });

    shadow.getElementById("ticketReset")
        .addEventListener("click", () => {
            hideAlert();

            shadow.getElementById("ticketBranch").innerHTML = `
                <option value="">
                    Selecciona primero una compañía
                </option>
            `;

            shadow.getElementById("ticketBranch").disabled = true;

            shadow.getElementById("ticketSubcategory").innerHTML = `
                <option value="">
                    Selecciona primero una categoría
                </option>
            `;

            shadow.getElementById("ticketSubcategory").disabled = true;
        });

    shadow.getElementById("refreshTickets")
        .addEventListener("click", () => {
            cargarMisTickets(shadow, apiUrl);
        });

    // API pública opcional.
    window.TicketsWidget = {
        open: openModal,
        close: closeModal,
        api: apiUrl
    };
})();

/*
 * IMPORTANTE:
 * Este archivo se mantiene como único script público.
 * Los módulos se cargan dinámicamente para que la página
 * anfitriona solo tenga que incluir tickets.js.
 */

async function cargarCompanias(shadow, apiUrl) {
    const module =
        await import(
            apiUrl.replace(/\/api\/?$/, "") +
            "/widgets/js/companies.js"
        );

    return module.cargarCompanias(shadow, apiUrl);
}

async function cargarBranches(shadow, apiUrl, companyId) { console.log("cargarBranches")
    const module =
        await import(
            apiUrl.replace(/\/api\/?$/, "") +
            "/widgets/js/branches.js"
        );

    return module.cargarBranches(
        shadow,
        apiUrl,
        companyId
    );
}

async function crearTicket(apiUrl, data, file) {
    const module =
        await import(
            apiUrl.replace(/\/api\/?$/, "") +
            "/widgets/js/tickets.js"
        );

    return module.crearTicket(apiUrl, data, file);
}

async function cargarMisTickets(shadow, apiUrl) {
    const module =
        await import(
            apiUrl.replace(/\/api\/?$/, "") +
            "/widgets/js/tickets.js"
        );

    return module.cargarMisTickets(
        shadow,
        apiUrl
    );
}