import { apiGet } from "./api.js";

/*
 * GET /widget/me
 * Datos que el sitio anfitrión firmó en el token: correo, nombre, teléfono,
 * compañía y sucursal. Devuelve null si no se pudo consultar.
 */
export async function obtenerIdentidad(apiUrl) {

    try {
        const me = await apiGet(apiUrl, "/me");

        return {
            email: me?.email || "",
            name: me?.name || "",
            phone: me?.phone || "",
            company: me?.company || null,
            branch: me?.branch || null
        };

    } catch (error) {
        console.error("[Tickets Widget] No se pudieron leer los datos del token:", error);
        return null;
    }
}
