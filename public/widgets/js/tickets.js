import {
    apiPost,
    apiUpload
} from "./api.js";

export async function crearTicket(
    apiUrl,
    data,
    file = null
) {

    /*
     * Si no hay archivo, enviamos JSON.
     */
    if (!file) {

        return await apiPost(
            apiUrl,
            "/tickets",
            data
        );
    }

    /*
     * Si hay archivo, usamos multipart/form-data.
     */
    const formData =
        new FormData();

    Object.entries(data).forEach(
        ([key, value]) => {
            formData.append(
                key,
                value ?? ""
            );
        }
    );

    formData.append(
        "file",
        file
    );

    return await apiUpload(
        apiUrl,
        "/tickets",
        formData
    );
}

export async function cargarMisTickets(
    shadow,
    apiUrl
) {

    const list =
        shadow.getElementById(
            "ticketsList"
        );

    if (!list) {
        return;
    }

    list.innerHTML = `
        <div class="tw-empty">
            Cargando tickets...
        </div>
    `;

    try {

        /*
         * Endpoint provisional.
         * Cuando tengas autenticación, aquí podemos
         * enviar el usuario/token correspondiente.
         */
        const response =
            await fetch(
                apiUrl.replace(/\/$/, "") +
                "/tickets",
                {
                    method: "GET",
                    headers: {
                        "Accept": "application/json"
                    }
                }
            );

        if (!response.ok) {
            throw new Error(
                `Error HTTP ${response.status}`
            );
        }

        const result =
            await response.json();

        console.log(
            "[Tickets Widget] Tickets:",
            result
        );

        const tickets =
            Array.isArray(result)
                ? result
                : Array.isArray(result.data)
                    ? result.data
                    : [];

        if (!tickets.length) {

            list.innerHTML = `
                <div class="tw-empty">
                    No tienes tickets registrados.
                </div>
            `;

            return;
        }

        list.innerHTML = "";

        tickets.forEach(ticket => {

            const item =
                document.createElement("div");

            item.className =
                "tw-ticket";

            const title =
                ticket.titulo ??
                ticket.title ??
                ticket.descripcion ??
                ticket.description ??
                "Ticket";

            const id =
                ticket.id ??
                ticket.Id ??
                ticket.ID ??
                "";

            const status =
                ticket.estado ??
                ticket.status ??
                "Pendiente";

            item.innerHTML = `
                <div class="tw-ticket-title">
                    ${escapeHtml(title)}
                </div>

                <div class="tw-ticket-meta">
                    Ticket #${escapeHtml(String(id))}
                </div>

                <span class="tw-status">
                    ${escapeHtml(String(status))}
                </span>
            `;

            list.appendChild(item);
        });

    } catch (error) {

        console.error(
            "[Tickets Widget] Error cargando tickets:",
            error
        );

        list.innerHTML = `
            <div class="tw-empty">
                No fue posible cargar tus tickets.
            </div>
        `;
    }
}

function escapeHtml(value) {

    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}