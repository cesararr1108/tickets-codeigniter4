/*
 * API de "Mis tickets" (tickets del solicitante).
 *
 * Va en un archivo propio (no en tickets.js) para que, al actualizar el
 * servidor, un tickets.js viejo guardado en la caché del navegador no rompa
 * el widget.
 */

import { apiGet } from "./api.js";

/*
 * GET /tickets/mine?email=&status=pendientes|resueltos&from=&to=&page=&perPage=
 * from / to: fechas "AAAA-MM-DD" (opcionales).
 * Devuelve: { data: [...], page, pages, total, counts: { pendientes, resueltos } }
 */
export async function obtenerMisTickets(apiUrl, { email, status = "pendientes", from = "", to = "", page = 1, perPage = 5 }) {

    const query = new URLSearchParams({ email, status, page, perPage });

    if (from) query.set("from", from);
    if (to) query.set("to", to);

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
