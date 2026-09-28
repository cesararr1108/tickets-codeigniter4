/*
 * Formulario "Requerimiento" (hoja del Excel de requerimientos).
 * HTML: templates/requirement-form.html
 *
 * El bloque "Usuario beneficiario" cambia según la subcategoría:
 *   Biométrico               -> nombre, cédula, cargo
 *   Creación de usuario ADG  -> identificación, nombres, apellidos, correo,
 *                               celular, rol y si ya está creado en SAP
 *   Creación de usuario SAP  -> identificación, nombres, apellidos, correo,
 *                               celular, rol
 *   Otras                    -> usuario beneficiario (texto)
 */

import { AREAS, ExtraForm, loadUsers, normalize, options } from "./extra-form.js";

const TIPOS = [
    "Acceso",
    "Creación / modificación de usuario",
    "Instalación de software",
    "Equipo / hardware",
    "Configuración",
    "Reporte / información",
    "Otro"
];

const IMPACTOS = [
    "Un usuario",
    "Varios usuarios",
    "Un área",
    "Toda la sede",
    "Toda la empresa"
];

export class RequirementForm extends ExtraForm {

    static key = "requerimiento";
    static title = "Requerimiento";
    static template = "requirement-form";
    static keywords = ["requerimiento"];

    async templateData(context) {

        const users = await loadUsers(this.apiUrl);

        return {
            requester: context.requester,
            sede: context.sede,
            areaOptions: options(AREAS, context.area),
            tipoOptions: options(TIPOS),
            impactoOptions: options(IMPACTOS),
            userOptions: options(users)
        };
    }

    variantFor(subcategory) {

        const name = normalize(subcategory?.name);

        if (name.includes("biometr")) return "biometrico";
        if (name.includes("adg")) return "usuario-adg";
        if (name.includes("sap")) return "usuario-sap";

        return "default";
    }
}
