/*
 * Formulario "Proyectos - Desarrollos" (hoja del Excel de requerimientos).
 * HTML: templates/project-form.html
 */

import { AREAS, ExtraForm, chipsHtml, loadUsers, options } from "./extra-form.js";

const SYSTEMS = [
    "SAP",
    "Portal",
    "Página web",
    "Correo",
    "Power BI",
    "Otro"
];

export class ProjectForm extends ExtraForm {

    static key = "proyecto";
    static title = "Solicitud de proyecto / desarrollo";
    static template = "project-form";
    static keywords = ["proyecto", "desarrollo"];

    async templateData(context) {

        const users = await loadUsers(this.apiUrl);

        return {
            requester: context.requester,
            areaOptions: options(AREAS, context.area),
            userOptions: options(users),
            areaChips: await chipsHtml(AREAS),
            systemChips: await chipsHtml(SYSTEMS)
        };
    }
}
