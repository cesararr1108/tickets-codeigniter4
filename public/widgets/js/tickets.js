import { apiGet, apiPost, apiUpload, toList } from "./api.js";

/*
 * POST /tickets
 * data puede incluir Description, SenderName, FormKey y Answers
 * ([{ key, label, value }]); el backend guarda todo junto.
 * Si hay archivos se envía como multipart/form-data (files[]).
 */
export async function crearTicket(apiUrl, data, files = []) {

    const list = (Array.isArray(files) ? files : [files]).filter(Boolean);

    if (!list.length) {
        return await apiPost(apiUrl, "/tickets", data);
    }

    const formData = new FormData();

    Object.entries(data).forEach(([key, value]) => {
        formData.append(
            key,
            typeof value === "object" && value !== null ? JSON.stringify(value) : value ?? ""
        );
    });

    list.forEach(file => formData.append("files[]", file));

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
 * GET /tickets/mine?email=&status=pendientes|resueltos&page=&perPage=
 * Devuelve: { data: [...], page, pages, total, counts: { pendientes, resueltos } }
 */
export async function obtenerMisTickets(apiUrl, { email, status = "pendientes", page = 1, perPage = 5 }) {

    const query = new URLSearchParams({ email, status, page, perPage });

    return await apiGet(apiUrl, "/tickets/mine?" + query.toString());
}

/*
 * GET /tickets/{id}/detalle?email=
 * Devuelve: { ticket, answers, followUp, messages, attachments }
 */
export async function obtenerDetalleTicket(apiUrl, id, email) {

    return await apiGet(
        apiUrl,
        "/tickets/" + encodeURIComponent(id) + "/detalle?email=" + encodeURIComponent(email)
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
