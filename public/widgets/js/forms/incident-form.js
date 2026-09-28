/*
 * Formulario "Incidente - soporte" (hoja del Excel de requerimientos).
 * HTML: templates/incident-form.html
 *
 * Título, descripción ("¿Qué está sucediendo?") y urgencia (prioridad) ya
 * los pide el paso Detalle; Diagnóstico y Solución los registra TI en el
 * panel.
 */

import { AREAS, ExtraForm, options } from "./extra-form.js";

const SERVICIOS = [
    "Correo electrónico",
    "SAP",
    "Internet / red",
    "Equipo de cómputo",
    "Impresora",
    "Teléfono",
    "Portal / página web",
    "Carpetas compartidas",
    "Otro"
];

export class IncidentForm extends ExtraForm {

    static key = "incidente";
    static title = "Incidente / soporte";
    static template = "incident-form";
    static keywords = ["incidente", "soporte"];

    async templateData(context) {
        return {
            requester: context.requester,
            sede: context.sede,
            areaOptions: options(AREAS, context.area),
            servicioOptions: options(SERVICIOS)
        };
    }
}
