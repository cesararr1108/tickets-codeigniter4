import { apiGet } from "./api.js";

export async function cargarBranches(
    shadow,
    apiUrl,
    companyId
) {

    const select =
        shadow.getElementById("ticketBranch");

    if (!select) {
        return;
    }

    if (!companyId) {

        select.innerHTML = `
            <option value="">
                Selecciona primero una compañía
            </option>
        `;

        select.disabled = true;

        return;
    }

    try {

        select.disabled = true;

        select.innerHTML = `
            <option value="">
                Cargando sucursales...
            </option>
        `;

        /*
         * Por ahora se usa:
         * GET /branches/{companyId}
         *
         * Si tu API realmente utiliza:
         * /branches?company=1
         * solamente cambiamos esta línea.
         */
        const result =
            await apiGet(
                apiUrl,
                "/branches/" +
                encodeURIComponent(companyId)
            );

        console.log(
            "[Tickets Widget] Branches:",
            result
        );

        const branches =
            Array.isArray(result)
                ? result
                : Array.isArray(result.data)
                    ? result.data
                    : [];

        select.innerHTML = `
            <option value="">
                Selecciona una sucursal
            </option>
        `;

        if (!branches.length) {

            select.innerHTML = `
                <option value="">
                    No hay sucursales disponibles
                </option>
            `;

            return;
        }

        branches.forEach(branch => {

            const option =
                document.createElement("option");

            option.value =branch.CodBranches;

            option.textContent =branch.Branches;

            select.appendChild(option);
        });

    } catch (error) {

        console.error(
            "[Tickets Widget] Error cargando sucursales:",
            error
        );

        select.innerHTML = `
            <option value="">
                Error cargando sucursales
            </option>
        `;

    } finally {

        select.disabled = false;
    }
}