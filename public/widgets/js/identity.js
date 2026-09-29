import { apiGet } from "./api.js";

/*
 * GET /widget/me
 *
 * Perfil del usuario según el token que firmó el servidor anfitrión (no se
 * puede alterar desde el navegador). Devuelve el mismo formato que antes
 * llegaba en los atributos data-*:
 *
 *   { company, branch, email, name, phone, user, role, area }
 *
 * company y branch son códigos (CodCompanies / CodBranches). Si no se pudo
 * consultar (token inválido o vencido) devuelve un perfil vacío.
 */
export async function obtenerPerfil(apiUrl) {

    try {
        const me = await apiGet(apiUrl, "/me");

        return {
            company: me?.company?.id ?? "",
            branch: me?.branch?.id ?? "",
            email: me?.email ?? "",
            name: me?.name ?? "",
            phone: me?.phone ?? "",
            user: me?.user ?? "",
            role: me?.role ?? "",
            area: me?.area ?? ""
        };

    } catch (error) {
        console.error("[Tickets Widget] No se pudo leer el perfil del token:", error);
        return {};
    }
}
