import { apiGet, apiPost, apiUpload, toList } from "./api.js";

/*
 * POST /widget/tickets
 * Si hay archivo se envía como multipart/form-data. La descripción viaja en
 * `Description` y el servidor la guarda como primer mensaje del chat.
 */
export async function crearTicket(apiUrl, data, file = null) {

    if (!file) {
        return await apiPost(apiUrl, "/tickets", data);
    }

    const formData = new FormData();

    Object.entries(data).forEach(([key, value]) => {
        formData.append(key, value ?? "");
    });

    formData.append("file", file);

    return await apiUpload(apiUrl, "/tickets", formData);
}

/*
 * GET /tickets
 * Devuelve: [{ id, title, status, priority }]
 */
export async function obtenerTickets(apiUrl) {

    const result = await apiGet(apiUrl, "/tickets");

    return toList(result).map(ticket => ({
        id: ticket.IdTicket ?? ticket.id ?? "",
        title: ticket.Subject ?? ticket.title ?? "Ticket",
        status: ticket.Status ?? ticket.status ?? "abierto",
        priority: ticket.Priority ?? ticket.priority ?? ""
    }));
}
