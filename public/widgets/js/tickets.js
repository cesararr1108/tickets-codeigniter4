import { apiGet, apiPost, apiUpload, toList } from "./api.js";

/*
 * POST /tickets
 * Si hay archivo se envía como multipart/form-data.
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
 * POST /tickets/{id}/messages
 * Guarda la descripción como primer mensaje del ticket.
 */
export async function agregarMensaje(apiUrl, ticketId, senderName, message) {

    return await apiPost(
        apiUrl,
        "/tickets/" + encodeURIComponent(ticketId) + "/messages",
        {
            SenderType: "cliente",
            SenderName: senderName,
            Message: message
        }
    );
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
