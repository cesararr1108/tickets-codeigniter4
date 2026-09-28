/*
 * Mini motor de plantillas del widget.
 *
 * Los componentes viven como archivos .html dentro de /widgets/templates/.
 * Este módulo los descarga (una sola vez), reemplaza los marcadores y
 * devuelve elementos DOM listos para insertar.
 *
 * Marcadores disponibles dentro de un .html:
 *
 *   {{campo}}    -> valor escapado (texto seguro)
 *   {{{campo}}}  -> valor sin escapar (para SVG/HTML de confianza)
 *
 * Ejemplo:
 *
 *   const card = await render("company-card", { name: "ACME", id: "01" });
 *   grid.appendChild(card);
 */

const cache = new Map();

// Versión opcional (?v=...) para invalidar la caché del navegador.
let version = "";

export function setAssetVersion(value) {
    version = value ? "?v=" + encodeURIComponent(value) : "";
}

/*
 * URL de un archivo del widget, relativa a la carpeta /widgets/.
 * Ej: assetUrl("css/widget.css")
 */
export function assetUrl(path) {
    return new URL("../" + path + version, import.meta.url);
}

/*
 * Descarga un archivo de texto del widget (una sola vez).
 */
export function loadText(path) {

    if (!cache.has(path)) {

        const request = fetch(assetUrl(path))
            .then(response => {
                if (!response.ok) {
                    throw new Error(
                        `No se pudo cargar "${path}" (HTTP ${response.status})`
                    );
                }
                return response.text();
            })
            .catch(error => {
                cache.delete(path);
                throw error;
            });

        cache.set(path, request);
    }

    return cache.get(path);
}

export function loadTemplate(name) {
    return loadText(`templates/${name}.html`);
}

export function fill(html, data = {}) {

    return html
        .replace(/\{\{\{\s*([\w.]+)\s*\}\}\}/g, (_, key) =>
            String(data[key] ?? "")
        )
        .replace(/\{\{\s*([\w.]+)\s*\}\}/g, (_, key) =>
            escapeHtml(data[key] ?? "")
        );
}

/*
 * Devuelve el primer elemento de la plantilla ya rellenado.
 */
export async function render(name, data = {}) {

    const html = await loadTemplate(name);
    const template = document.createElement("template");

    template.innerHTML = fill(html, data).trim();

    return template.content.firstElementChild;
}

/*
 * Rellena la plantilla por cada item y devuelve un DocumentFragment.
 */
export async function renderList(name, items, mapper = item => item) {

    const html = await loadTemplate(name);
    const template = document.createElement("template");

    template.innerHTML = items
        .map((item, index) => fill(html, mapper(item, index)).trim())
        .join("");

    return template.content;
}

export function escapeHtml(value) {

    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}
