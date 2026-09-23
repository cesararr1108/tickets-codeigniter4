import { apiGet, toList } from "./api.js";

/*
 * GET /companies
 * Devuelve: [{ id, name }]
 */
export async function obtenerCompanias(apiUrl) {

    const result = await apiGet(apiUrl, "/companies");

    return toList(result).map(company => ({
        id: company.CodCompanies,
        name: company.Companies ?? "Compañía"
    }));
}
