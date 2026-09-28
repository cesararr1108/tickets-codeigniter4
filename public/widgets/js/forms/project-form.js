/*
 * Formulario adicional "Solicitud de proyecto".
 *
 * Se muestra en el paso Detalle cuando la categoría elegida es "Proyecto".
 * El HTML está en templates/project-form.html; esta clase lo llena,
 * maneja los chips, valida y devuelve las respuestas.
 */

import { escapeHtml, render, renderList } from "../template.js";
import { apiGet, toList } from "../api.js";

// Listas fijas del formulario (ajústalas a tu empresa).
const AREAS = [
    "Comercial",
    "Tecnología",
    "Cartera",
    "Financiera",
    "Logística",
    "Compras",
    "Talento Humano",
    "Gerencia"
];

const SYSTEMS = [
    "SAP",
    "Portal",
    "Página web",
    "Correo",
    "Power BI",
    "Otro"
];

// Los usuarios se piden una sola vez a la API.
let usersRequest = null;

function loadUsers(apiUrl) {

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

function options(values) {
    return values
        .map(value => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`)
        .join("");
}

async function chipsHtml(values) {

    const fragment = await renderList("chip", values, value => ({ value, label: value }));
    const box = document.createElement("div");
    console.log({
        fragment
    })
    box.append(fragment);

    return box.innerHTML;
}

function normalize(text) {
    return String(text ?? "")
        .normalize("NFD")
        .replace(/[̀-ͯ]/g, "")
        .toLowerCase();
}

export class ProjectForm {

    /*
     * ¿Esta categoría usa el formulario de proyecto?
     */
    static matches(category) {
        return normalize(category?.name).includes("proyecto");
    }

    constructor(container, { apiUrl }) {
        this.container = container;
        this.apiUrl = apiUrl;
        this.root = null;
        this.files = [];
        this.destroyed = false;

        this.onClick = this.onClick.bind(this);
    }

    async mount({ requester = "" } = {}) {

        const users = await loadUsers(this.apiUrl);

        const root = await render("project-form", {
            requester,
            areaOptions: options(AREAS),
            userOptions: options(users),
            areaChips: await chipsHtml(AREAS),
            systemChips: await chipsHtml(SYSTEMS)
        });

        // Se cambió de categoría mientras cargaba.
        if (this.destroyed) {
            return;
        }

        this.root = root;
        this.container.replaceChildren(this.root);
        this.container.hidden = false;

        this.root.addEventListener("click", this.onClick);
        this.bindDropzone();
    }

    unmount() {
        this.destroyed = true;
        this.root?.removeEventListener("click", this.onClick);
        this.container.replaceChildren();
        this.container.hidden = true;
        this.root = null;
        this.files = [];
    }

    setRequester(value) {
        const input = this.root?.querySelector('[data-field="solicitante"]');

        if (input) {
            input.value = value;
        }
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
    // Documentos anexos
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
            .filter(field => field.type !== "file");
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
            return "Espera a que cargue el formulario del proyecto.";
        }

        for (const field of this.fields()) {

            const required = field.required || field.dataset.required === "true";

            if (required && !this.valueOf(field)) {
                return `Completa el campo "${this.labelOf(field)}" del proyecto.`;
            }
        }

        return null;
    }

    /*
     * [{ label, value }] en el orden del formulario.
     */
    answers() {

        const list = this.fields().map(field => ({
            label: this.labelOf(field),
            value: this.valueOf(field)
        }));

        if (this.files.length) {
            list.push({
                label: "Documentos anexos",
                value: this.files.map(file => file.name).join(", ")
            });
        }

        return list;
    }

    /*
     * Texto plano para guardar en el ticket (se agrega a la descripción).
     */
    toText() {
        return "SOLICITUD DE PROYECTO\n" + this.answers()
            .map(({ label, value }) => `${label}: ${value || "—"}`)
            .join("\n");
    }

    /*
     * Bloque para el paso de revisión.
     */
    reviewNode() {

        const box = document.createElement("div");
        box.className = "tw-review-item tw-review-full";

        const title = document.createElement("span");
        title.className = "tw-review-label";
        title.textContent = "Solicitud de proyecto";
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
