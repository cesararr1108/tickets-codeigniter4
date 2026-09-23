/*
 * Colores e iconos para las cards.
 * Se eligen de forma estable a partir del índice o del nombre.
 */

export const PALETTE = [
    "#2f6fdf",
    "#ea6a2f",
    "#1faa7a",
    "#e8a200",
    "#e5779f",
    "#5b4fcf",
    "#0e9fb3",
    "#d64545"
];

export function colorFor(index) {
    return PALETTE[index % PALETTE.length];
}

export function initials(name) {
    return String(name ?? "")
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map(word => word[0].toUpperCase())
        .join("") || "?";
}

const svg = paths =>
    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${paths}</svg>`;

const ICONS = {
    hardware: svg('<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>'),
    software: svg('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 14l-2 2 2 2M12 18h4"/>'),
    network: svg('<path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M2 9a15 15 0 0 1 20 0"/><circle cx="12" cy="19.5" r="1"/>'),
    mail: svg('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>'),
    printer: svg('<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/>'),
    access: svg('<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3"/>'),
    database: svg('<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>'),
    phone: svg('<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>'),
    alert: svg('<path d="M12 3l10 18H2z"/><path d="M12 10v4M12 17.5v.5"/>'),
    tools: svg('<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>'),
    help: svg('<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17.5v.5"/>')
};

/*
 * Palabra clave (en minúsculas y sin tildes) -> icono.
 * Agrega aquí nuevas palabras según tus categorías reales.
 */
const KEYWORDS = [
    [/hardware|equipo|computador|pc|laptop|monitor/, "hardware"],
    [/software|aplicaci|programa|sistema/, "software"],
    [/red|internet|wifi|conexi|vpn/, "network"],
    [/correo|mail|outlook/, "mail"],
    [/impres|escan/, "printer"],
    [/acceso|usuario|contrase|clave|permiso/, "access"],
    [/sap|erp|base de datos|reporte/, "database"],
    [/telefon|celular|movil/, "phone"],
    [/incidente|falla|error|soporte/, "alert"],
    [/mantenimiento|ajuste|instalaci|configur/, "tools"]
];

export function iconFor(name) {

    const text = String(name ?? "")
        .toLowerCase()
        .normalize("NFD")
        .replace(/[̀-ͯ]/g, "");

    const match = KEYWORDS.find(([regex]) => regex.test(text));

    return ICONS[match ? match[1] : "help"];
}
