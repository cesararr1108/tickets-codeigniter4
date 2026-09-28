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
 *                       (y this.category)
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
        this.fileList = null;
        this.onPaste = null;
        this.subcategory = null;
        this.category = null;
        this.destroyed = false;

        this.onClick = this.onClick.bind(this);
    }

    /*
     * context: { requester, email, area, sede, perfil }
     */
    async templateData(context) {
        return context;
    }

    /*
     * Bloque [data-variant] a mostrar según subcategoría/categoría
     * (this.category). null = "default".
     */
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
        if (this.onPaste) {
            document.removeEventListener("paste", this.onPaste);
        }
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
    // Archivos (clic, arrastrar o pegar con Ctrl+V)
    // ------------------------------------------

    bindDropzone() {

        const zone = this.root.querySelector("[data-dropzone]");
        const input = zone?.querySelector('input[type="file"]');

        if (!zone || !input) {
            return;
        }

        this.fileList = this.root.querySelector("[data-file-list]");

        input.addEventListener("change", () => {
            this.addFiles(input.files);
            input.value = "";
        });

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

        zone.addEventListener("drop", event => this.addFiles(event.dataTransfer?.files));

        this.fileList?.addEventListener("click", event => {
            const button = event.target.closest("[data-remove]");

            if (button) {
                this.files.splice(Number(button.dataset.remove), 1);
                this.renderFiles();
            }
        });

        // Ctrl+V con una captura de pantalla (el evento llega al documento
        // aunque el widget esté dentro de un Shadow DOM).
        this.onPaste = event => this.handlePaste(event, zone);
        document.addEventListener("paste", this.onPaste);
    }

    handlePaste(event, zone) {

        // Solo si este formulario está a la vista.
        if (!this.root?.isConnected || this.root.offsetParent === null) {
            return;
        }

        const files = Array.from(event.clipboardData?.items ?? [])
            .filter(item => item.kind === "file")
            .map(item => item.getAsFile())
            .filter(Boolean);

        if (!files.length) {
            return;
        }

        event.preventDefault();

        const stamp = new Date().toTimeString().slice(0, 8).replaceAll(":", "");

        this.addFiles(files.map((file, i) => {
            const extension = (file.type.split("/")[1] || "png").replace("jpeg", "jpg");
            const name = `captura-${stamp}${files.length > 1 ? "-" + (i + 1) : ""}.${extension}`;

            return new File([file], name, { type: file.type });
        }));

        // Abre el bloque plegable para que se vea lo que se pegó.
        const details = zone.closest("details");
        if (details) {
            details.open = true;
        }
    }

    addFiles(list) {

        Array.from(list ?? []).forEach(file => {
            const exists = this.files.some(f => f.name === file.name && f.size === file.size);

            if (!exists) {
                this.files.push(file);
            }
        });

        this.renderFiles();
    }

    renderFiles() {

        const zone = this.root?.querySelector("[data-dropzone]");
        zone?.classList.toggle("tw-has-file", this.files.length > 0);

        if (!this.fileList) {
            return;
        }

        this.fileList.replaceChildren(...this.files.map((file, index) => {
            const item = document.createElement("li");

            const name = document.createElement("span");
            name.textContent = file.name;

            const size = document.createElement("small");
            size.textContent = file.size < 1024 * 1024
                ? Math.max(1, Math.round(file.size / 1024)) + " KB"
                : (file.size / 1024 / 1024).toFixed(1) + " MB";

            const remove = document.createElement("button");
            remove.type = "button";
            remove.dataset.remove = index;
            remove.setAttribute("aria-label", "Quitar " + file.name);
            remove.textContent = "×";

            item.append(name, size, remove);
            return item;
        }));
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

        // Los opcionales que se dejan vacíos no se guardan.
        const list = this.fields()
            .map(field => ({
                key: field.dataset.field,
                label: this.labelOf(field),
                value: this.valueOf(field)
            }))
            .filter(answer => answer.value !== "");

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
