import { apiGet, toList } from "./api.js";

/*
 * GET /branches?company={companyId}
 * Devuelve: [{ id, name }]
 */
export async function obtenerBranches(apiUrl, companyId) {

    const result = await apiGet(
        apiUrl,
        "/branches?company=" + encodeURIComponent(companyId)
    );

    return toList(result)
        // Por si el backend ignora el filtro, se filtra también aquí.
        .filter(branch =>
            branch.CodCompanies === undefined ||
            String(branch.CodCompanies) === String(companyId)
        )
        .map(branch => ({
            id: branch.CodBranches,
            name: branch.Branches ?? "Sucursal"
        }));
}
