import { apiGet } from "./api.js";

export async function cargarCompanias(shadow, apiUrl) {

    const select =
        shadow.getElementById("ticketCompany");

    if (!select) {
        return;
    }

    try {

        select.disabled = true;

        select.innerHTML = `
            <option value="">
                Cargando compañías...
            </option>
        `;

        const result =
            await apiGet(
                apiUrl,
                "/companies"
            );

        console.log(
            "[Tickets Widget] Companies:",
            result
        );

        const companies =
            Array.isArray(result)
                ? result
                : Array.isArray(result.data)
                    ? result.data
                    : [];

        select.innerHTML = `
            <option value="">
                Selecciona una compañía
            </option>
        `;

        if (!companies.length) {

            select.innerHTML = `
                <option value="">
                    No hay compañías disponibles
                </option>
            `;

            return;
        }

        companies.forEach(company => {

            const option =
                document.createElement("option");

            option.value =
                company.CodCompanies;

            option.textContent =
                company.Companies ??
                "Compañía";

            select.appendChild(option);
        });

    } catch (error) {

        console.error(
            "[Tickets Widget] Error cargando compañías:",
            error
        );

        select.innerHTML = `
            <option value="">
                Error cargando compañías
            </option>
        `;

    } finally {

        select.disabled = false;
    }
}