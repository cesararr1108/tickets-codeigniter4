/*
 * Base de los formularios adicionales del paso Detalle
 * (Proyecto, Requerimiento, Incidente...).
 *
 * Cada formulario es una clase que extiende ExtraForm y define:
 *   static key       -> clave que se guarda en TicketFormAnswers.FormKey
 *   static title     -> título que se muestra en la revisión y en el panel
 *   static template  -> nombre del .html en templates/
 *   static keywords  -> palabras que, si aparecen en el nombre de la
 *                       categoría (o del formulario de la subcategoría),
 *                       activan este formulario
 *   templateData()   -> variables para la plantilla
 *   variantFor(sub)  -> (opcional) bloque [data-variant] según subcategoría
 *
 * Convenciones del HTML:
 *   data-field="clave"        campo que se guarda
 *   required / data-required  obligatorio
 *   data-multiple="true"      grupo de chips con selección múltiple
 *   data-variant="nombre"     bloque que solo se muestra para ciertas
 *                             subcategorías (los ocultos no se validan ni
 *                             se guardan)
 *   data-dropzone             zona de archivos (input type=file multiple)
 */

import { escapeHtml, render, renderList } from "../template.js";
import { apiGet, toList } from "../api.js";

// Listas compartidas (ajústalas a tu empresa).
export const AREAS = [
    "Comercial",
    "Tecnología",
    "Cartera",
    "Financiera",
    "Contabilidad",
    "Logística",
    "Compras",
    "Talento Humano",
    "Gerencia"
];

// Los usuarios se piden una sola vez a la API.
let usersRequest = null;

export function loadUsers(apiUrl) {

    if (!usersRequest) {
        usersRequest = apiGet(apiUrl, "/users")
            .then(result => toList(result)
                .filter(user => String(user.IsActive ?? 1) !== "0")
                .map(user => user.FullName ?? user.Email ?? user.IdUser)
                .filter(Boolean)
                .sort((a, b) => a.localeCompare(b, "es")))
            .catch(error => {
                usersRequest = null;
                console.error("[Tickets Widget] Error cargando usuarios:", error);
                return [];
            });
    }

    return usersRequest;
}

/*
 * <option> para un <select>. Si "selected" no está en la lista se agrega.
 */
export function options(values, selected = "") {

    const list = selected && !values.includes(selected) ? [selected, ...values] : values;

    return list
        .map(value => {
            const v = escapeHtml(value);
            return `<option value="${v}"${value === selected ? " selected" : ""}>${v}</option>`;
        })
        .join("");
}

export async function chipsHtml(values) {

    const fragment = await renderList("chip", values, value => ({ value, label: value }));
    const box = document.createElement("div");

    box.append(fragment);

    return box.innerHTML;
}

export function normalize(text) {
    return String(text ?? "")
        .normalize("NFD")
        .replace(/[̀-ͯ]/g, "")
        .toLowerCase();
}

export class ExtraForm {

    static key = "";
    static title = "";
    static template = "";
    static keywords = [];

    /*
     * ¿Este formulario corresponde al texto (nombre de categoría o de
     * formulario)?
     */
    static matches(text) {
        const value = normalize(text);
        return value !== "" && this.keywords.some(word => value.includes(normalize(word)));
    }

    constructor(container, { apiUrl }) {
        this.container = container;
        this.apiUrl = apiUrl;
        this.root = null;
        this.files = [];
        this.subcategory = null;
        this.destroyed = false;

        this.onClick = this.onClick.bind(this);
    }

    /*
     * context: { requester, email, area, sede, perfil }
     */
    async templateData(context) {
        return context;
    }

    variantFor(subcategory) {
        return null;
    }

    async mount(context = {}) {

        const root = await render(this.constructor.template, await this.templateData(context));

        // Se cambió de categoría mientras cargaba.
        if (this.destroyed) {
            return;
        }

        this.root = root;
        this.container.replaceChildren(this.root);
        this.container.hidden = false;

        this.root.addEventListener("click", this.onClick);
        this.bindDropzone();
        this.setSubcategory(this.subcategory);
    }

    unmount() {
        this.destroyed = true;
        this.root?.removeEventListener("click", this.onClick);
        this.container.replaceChildren();
        this.container.hidden = true;
        this.root = null;
        this.files = [];
    }

    setField(key, value) {
        const input = this.root?.querySelector(`[data-field="${key}"]`);

        if (input && "value" in input) {
            input.value = value;
        }
    }

    /*
     * Muestra el bloque [data-variant] que corresponde a la subcategoría.
     */
    setSubcategory(subcategory) {

        this.subcategory = subcategory ?? null;

        if (!this.root) {
            return;
        }

        const blocks = this.root.querySelectorAll("[data-variant]");

        if (!blocks.length) {
            return;
        }

        const wanted = this.variantFor(this.subcategory) ?? "default";
        const exists = Array.from(blocks).some(block => block.dataset.variant === wanted);
        const variant = exists ? wanted : "default";

        blocks.forEach(block => {
            block.hidden = block.dataset.variant !== variant;
        });
    }

    // ------------------------------------------
    // Chips y tarjetas (selección única o múltiple)
    // ------------------------------------------

    onClick(event) {

        const option = event.target.closest("[data-value]");
        const group = option?.closest("[data-field]");

        if (!option || !group || !this.root.contains(group)) {
            return;
        }

        const pressed = option.getAttribute("aria-pressed") === "true";

        if (group.dataset.multiple !== "true") {
            group.querySelectorAll("[data-value]").forEach(item =>
                item.setAttribute("aria-pressed", "false")
            );
        }

        option.setAttribute("aria-pressed", String(!pressed));
    }

    // ------------------------------------------
    // Archivos
    // ------------------------------------------

    bindDropzone() {

        const zone = this.root.querySelector("[data-dropzone]");
        const input = zone?.querySelector('input[type="file"]');
        const label = zone?.querySelector("[data-file-names]");

        if (!zone || !input || !label) {
            return;
        }

        const defaultLabel = label.textContent;

        const setFiles = files => {
            this.files = Array.from(files ?? []);
            label.textContent = this.files.length
                ? this.files.map(file => file.name).join(", ")
                : defaultLabel;
            zone.classList.toggle("tw-has-file", this.files.length > 0);
        };

        input.addEventListener("change", () => setFiles(input.files));

        ["dragenter", "dragover"].forEach(type =>
            zone.addEventListener(type, event => {
                event.preventDefault();
                zone.classList.add("tw-dragging");
            })
        );

        ["dragleave", "drop"].forEach(type =>
            zone.addEventListener(type, event => {
                event.preventDefault();
                zone.classList.remove("tw-dragging");
            })
        );

        zone.addEventListener("drop", event => setFiles(event.dataTransfer?.files));
    }

    // ------------------------------------------
    // Lectura y validación
    // ------------------------------------------

    fields() {
        return Array.from(this.root?.querySelectorAll("[data-field]") ?? [])
            .filter(field => field.type !== "file")
            .filter(field => !field.closest("[data-variant][hidden]"));
    }

    labelOf(field) {
        const label = field.closest(".tw-field")?.querySelector(".tw-label");
        const clone = label?.cloneNode(true);

        clone?.querySelector(".tw-optional")?.remove();

        return clone?.textContent.trim() || field.dataset.field;
    }

    valueOf(field) {

        if (field.matches("input, select, textarea")) {
            if (field.tagName === "SELECT") {
                return field.value ? field.selectedOptions[0].textContent.trim() : "";
            }
            if (field.type === "datetime-local") {
                return field.value.replace("T", " ");
            }
            return field.value.trim();
        }

        // Grupo de chips / tarjetas
        return Array.from(field.querySelectorAll('[aria-pressed="true"]'))
            .map(item =>
                (item.querySelector(".tw-priority-title") ?? item).textContent.trim()
            )
            .join(", ");
    }

    /*
     * Devuelve el primer error o null.
     */
    validate() {

        if (!this.root) {
            return "Espera a que cargue el formulario.";
        }

        for (const field of this.fields()) {

            const required = field.required || field.dataset.required === "true";

            if (required && !this.valueOf(field)) {
                return `Completa el campo "${this.labelOf(field)}".`;
            }

            if (field.type === "email" && field.value.trim() && !field.checkValidity()) {
                return `Revisa el campo "${this.labelOf(field)}".`;
            }
        }

        return null;
    }

    /*
     * [{ key, label, value }] en el orden del formulario (lo que se guarda).
     */
    answers() {

        const list = this.fields().map(field => ({
            key: field.dataset.field,
            label: this.labelOf(field),
            value: this.valueOf(field)
        }));

        if (this.files.length) {
            list.push({
                key: "documentos",
                label: "Documentos anexos",
                value: this.files.map(file => file.name).join(", ")
            });
        }

        return list;
    }

    /*
     * Bloque para el paso de revisión.
     */
    reviewNode() {

        const box = document.createElement("div");
        box.className = "tw-review-item tw-review-full";

        const title = document.createElement("span");
        title.className = "tw-review-label";
        title.textContent = this.constructor.title;
        box.append(title);

        this.answers()
            .filter(({ value }) => value)
            .forEach(({ label, value }) => {
                const row = document.createElement("span");
                row.className = "tw-review-sub";

                const strong = document.createElement("strong");
                strong.textContent = label + ": ";

                row.append(strong, value);
                box.append(row);
            });

        return box;
    }
}
