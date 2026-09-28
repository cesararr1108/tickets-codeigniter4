/*
 * Formulario "Proyectos - Desarrollos" (hoja del Excel de requerimientos).
 * HTML: templates/project-form.html
 *
 * Si es un DESARROLLO (subcategoría o categoría con "desarrollo"):
 *   - se ocultan "Líder del proyecto" y "Presupuesto estimado";
 *   - Objetivo, Justificación, Alcance, Áreas involucradas y Requerimientos
 *     funcionales pasan a opcionales (bloque plegable).
 * En un PROYECTO todos esos campos son obligatorios.
 * (bloques data-variant="proyecto" / "desarrollo" del HTML)
 * El estado del proyecto lo actualiza el área desde el panel.
 */

import { AREAS, ExtraForm, chipsHtml, normalize, options } from "./extra-form.js";

const SYSTEMS = [
    "SAP",
    "Portal",
    "Página web",
    "Correo",
    "Power BI",
    "Otro"
];

/*
 * "proyecto" o "desarrollo" según el nombre (null si no lo dice).
 */
function kindOf(text) {

    const value = normalize(text);

    if (value.includes("desarrollo") && !value.includes("proyecto")) return "desarrollo";
    if (value.includes("proyecto") && !value.includes("desarrollo")) return "proyecto";

    return null;
}

export class ProjectForm extends ExtraForm {

    static key = "proyecto";
    static title = "Solicitud de proyecto / desarrollo";
    static template = "project-form";
    static keywords = ["proyecto", "desarrollo"];

    // La prioridad del ticket es la "Prioridad estratégica" del formulario.
    static ownsPriority = true;

    priority() {
        return this.root
            ?.querySelector('[data-field="prioridad_estrategica"] [aria-pressed="true"]')
            ?.dataset.value ?? null;
    }

    async templateData(context) {
        return {
            requester: context.requester,
            areaOptions: options(AREAS, context.area),
            areaChips: await chipsHtml(AREAS),
            systemChips: await chipsHtml(SYSTEMS)
        };
    }

    /*
     * Primero decide la subcategoría; si no lo aclara, la categoría.
     * Por defecto es proyecto.
     */
    variantFor(subcategory) {
        return kindOf(subcategory?.name) ?? kindOf(this.category?.name) ?? "proyecto";
    }
}
