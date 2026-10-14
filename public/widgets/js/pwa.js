/*
 * PWA "Mis tickets" (/widgets/).
 *
 * - El perfil (nombre, correo, compañía, sede, área...) llega del anfitrión por la
 *   URL (?email=&name=&company=&branch=&area=&user=&phone=&rol=) y se guarda en el
 *   dispositivo. Quien entra sin pasar por el anfitrión lo llena una sola vez.
 * - Muestra los tickets del solicitante (pendientes / resueltos) y el botón
 *   "Montar ticket", que abre el widget de siempre (tickets.js) ya con sus datos.
 */
import { obtenerMisTickets } from "./my-tickets.js";
import { obtenerCompanias } from "./companies.js";
import { obtenerBranches } from "./branches.js";

const baseUrl = new URL("../", import.meta.url);            // /widgets/
const apiUrl = new URL("../api", baseUrl).href.replace(/\/$/, "");
const STORE = "tw-pwa-profile";
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PER_PAGE = 10;
const REFRESH_MS = 60000;
const PARAMS = { email: "email", name: "name", nombre: "name", company: "company", branch: "branch",
    area: "area", user: "user", phone: "phone", rol: "role", role: "role" };

const app = document.getElementById("app");
const esc = value => String(value ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
const initials = name => String(name || "?").trim().split(/\s+/).slice(0, 2).map(w => w[0]).join("").toUpperCase() || "?";

const STATUS = { abierto: "Abierto", en_progreso: "En progreso", cerrado: "Resuelto" };
const PRIO = { alta: "Alta", media: "Media", baja: "Baja" };

// ---------------- Perfil ----------------

function readStore() {
    try { return JSON.parse(localStorage.getItem(STORE) || "{}") || {}; } catch { return {}; }
}

function saveProfile(profile) {
    try { localStorage.setItem(STORE, JSON.stringify(profile)); } catch { /* modo privado */ }
}

function loadProfile() {
    const profile = readStore();
    const query = new URLSearchParams(location.search);
    let fromHost = false;

    for (const [param, key] of Object.entries(PARAMS)) {
        const value = (query.get(param) || "").trim();
        if (value) { profile[key] = value; fromHost = true; }
    }

    if (fromHost) {
        saveProfile(profile);
        history.replaceState(null, "", location.pathname);   // quita los datos de la barra
    }

    return profile;
}

let profile = loadProfile();
const hasProfile = () => EMAIL_RE.test(profile.email || "") && !!profile.name;

// ---------------- Widget (montar ticket) ----------------

let widgetPromise = null;

function mountWidget() {
    widgetPromise ??= new Promise((resolve, reject) => {
        const script = document.createElement("script");
        script.src = new URL("tickets.js", baseUrl).href;
        script.dataset.container = "tickets-widget";
        script.dataset.api = apiUrl;
        script.dataset.version = "pwa1";
        script.dataset.push = "true";
        script.dataset.sw = new URL("../firebase-messaging-sw.js", baseUrl).href;
        for (const [k, v] of Object.entries({ company: profile.company, branch: profile.branch, email: profile.email,
            name: profile.name, phone: profile.phone, user: profile.user, rol: profile.role, area: profile.area })) {
            if (v) script.dataset[k] = v;
        }
        script.onerror = () => reject(new Error("No se pudo cargar el formulario de tickets."));
        document.head.appendChild(script);

        const started = Date.now();
        const wait = setInterval(() => {
            const widget = window.TicketsWidget;
            if (widget?.openMine) {
                clearInterval(wait);
                // Esta página tiene su propio botón: se oculta el flotante del widget.
                const style = document.createElement("style");
                style.textContent = ".tw-fab { display: none !important; }";
                widget.root.appendChild(style);
                resolve(widget);
            } else if (Date.now() - started > 15000) {
                clearInterval(wait);
                widgetPromise = null;
                reject(new Error("El formulario tardó demasiado en cargar."));
            }
        }, 150);
    });

    return widgetPromise;
}

// ---------------- Pantalla de perfil ----------------

async function renderSetup() {
    app.innerHTML = `
    <div class="wrap">
        <form class="card" id="setup" novalidate>
            <h1>${hasProfile() ? "Mis datos" : "Bienvenido"}</h1>
            <p>${hasProfile() ? "Actualiza tus datos de contacto." : "Indica quién eres para montar y seguir tus tickets de TI. Solo se pide una vez."}</p>
            <label class="field"><span>Nombre completo</span><input name="name" autocomplete="name" required></label>
            <label class="field"><span>Correo</span><input name="email" type="email" autocomplete="email" inputmode="email" required></label>
            <label class="field"><span>Compañía</span><select name="company" required><option value="">Cargando…</option></select></label>
            <label class="field"><span>Sede</span><select name="branch" required disabled><option value="">Elige primero la compañía</option></select></label>
            <label class="field"><span>Área</span><input name="area" placeholder="Ej.: Comercial, Tecnología"></label>
            <label class="field"><span>Teléfono (opcional)</span><input name="phone" type="tel" autocomplete="tel" inputmode="tel"></label>
            <div class="form-error" id="setupError" hidden></div>
            <div class="row">
                ${hasProfile() ? '<button type="button" class="btn-outline" id="setupCancel">Cancelar</button>' : ""}
                <button class="cta" type="submit">Guardar</button>
            </div>
        </form>
    </div>`;

    const form = document.getElementById("setup");
    const f = form.elements;
    const error = document.getElementById("setupError");
    f.name.value = profile.name || "";
    f.email.value = profile.email || "";
    f.area.value = profile.area || "";
    f.phone.value = profile.phone || "";

    const fill = (select, items, placeholder, selected) => {
        select.innerHTML = `<option value="">${esc(placeholder)}</option>` +
            items.map(i => `<option value="${esc(i.id)}"${String(i.id) === String(selected) ? " selected" : ""}>${esc(i.name)}</option>`).join("");
    };

    const loadBranches = async selected => {
        f.branch.disabled = true;
        if (!f.company.value) { fill(f.branch, [], "Elige primero la compañía"); return; }
        fill(f.branch, [], "Cargando…");
        try {
            fill(f.branch, await obtenerBranches(apiUrl, f.company.value), "Elige tu sede", selected);
            f.branch.disabled = false;
        } catch { fill(f.branch, [], "No se pudieron cargar las sedes"); }
    };

    obtenerCompanias(apiUrl)
        .then(items => { fill(f.company, items, "Elige tu compañía", profile.company); return loadBranches(profile.branch); })
        .catch(() => fill(f.company, [], "No se pudieron cargar las compañías"));

    f.company.addEventListener("change", () => loadBranches(""));
    document.getElementById("setupCancel")?.addEventListener("click", renderHome);

    form.addEventListener("submit", event => {
        event.preventDefault();
        const next = { ...profile, name: f.name.value.trim(), email: f.email.value.trim(), company: f.company.value,
            branch: f.branch.value, area: f.area.value.trim(), phone: f.phone.value.trim() };

        if (!next.name || !EMAIL_RE.test(next.email) || !next.company || !next.branch) {
            error.textContent = "Completa nombre, un correo válido, compañía y sede.";
            error.hidden = false;
            return;
        }

        const changed = next.email !== profile.email || next.company !== profile.company || next.branch !== profile.branch || next.name !== profile.name;
        profile = next;
        saveProfile(profile);

        // El widget ya montado conserva los datos viejos: se recarga para tomar los nuevos.
        if (changed && widgetPromise) { location.reload(); return; }
        renderHome();
    });
}

// ---------------- Inicio (dashboard) ----------------

const state = { status: "pendientes", rows: [], page: 1, pages: 1, counts: { pendientes: 0, resueltos: 0 }, loading: false, error: "" };
let timer = null;
let installEvent = null;

function renderHome() {
    clearInterval(timer);

    app.innerHTML = `
    <div class="wrap">
        <header class="top">
            <div class="avatar">${esc(initials(profile.name))}</div>
            <div class="who">
                <strong>${esc(profile.name)}</strong>
                <span>${esc([profile.area, profile.email].filter(Boolean).join(" · "))}</span>
            </div>
            <button class="icon-btn" id="editProfile" aria-label="Mis datos" title="Mis datos">⚙</button>
        </header>

        <button class="cta" id="newTicket">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Montar ticket
        </button>

        <div id="installBox"></div>
        <div id="offline" class="offline" hidden>Sin conexión: mostrando lo último guardado.</div>

        <div class="kpis">
            <button class="kpi pend" data-status="pendientes"><small>Pendientes</small><b id="cPend">–</b></button>
            <button class="kpi done" data-status="resueltos"><small>Resueltos</small><b id="cDone">–</b></button>
        </div>

        <div class="section-head">
            <h2 id="listTitle"></h2>
            <button class="link" id="refresh">Actualizar</button>
        </div>
        <div id="list"></div>
        <button class="link" id="more" hidden style="display:block;margin:6px auto">Ver más</button>
    </div>`;

    document.getElementById("editProfile").addEventListener("click", renderSetup);
    document.getElementById("refresh").addEventListener("click", () => load(true));
    document.getElementById("more").addEventListener("click", () => { state.page++; load(false); });
    document.querySelectorAll(".kpi").forEach(btn => btn.addEventListener("click", () => {
        state.status = btn.dataset.status;
        load(true);
    }));

    const newBtn = document.getElementById("newTicket");
    newBtn.addEventListener("click", async () => {
        newBtn.disabled = true;
        try { (await mountWidget()).open(); } catch (e) { alert(e.message); } finally { newBtn.disabled = false; }
    });

    renderInstall();
    mountWidget().catch(() => { /* se reintenta al pulsar el botón */ });
    load(true);
    timer = setInterval(() => { if (!document.hidden) load(true, true); }, REFRESH_MS);
}

function renderInstall() {
    const box = document.getElementById("installBox");
    const standalone = matchMedia("(display-mode: standalone)").matches || navigator.standalone === true;
    if (!box || standalone) return;

    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);

    if (installEvent) {
        box.innerHTML = `<div class="install"><span>Instala la app para abrirla como cualquier otra y recibir avisos.</span><button class="link" id="doInstall">Instalar</button></div>`;
        document.getElementById("doInstall").addEventListener("click", async () => {
            installEvent.prompt();
            await installEvent.userChoice.catch(() => {});
            installEvent = null;
            box.innerHTML = "";
        });
    } else if (ios) {
        box.innerHTML = `<div class="install"><span>Para instalarla: toca <b>Compartir</b> y luego <b>Añadir a pantalla de inicio</b>.</span></div>`;
    }
}

window.addEventListener("beforeinstallprompt", event => { event.preventDefault(); installEvent = event; renderInstall(); });

async function load(reset, silent = false) {
    if (state.loading) return;
    if (reset) state.page = 1;
    state.loading = true;
    if (!silent) { state.error = ""; if (reset) renderList(true); }

    try {
        const result = await obtenerMisTickets(apiUrl, { email: profile.email, status: state.status, page: state.page, perPage: PER_PAGE });
        state.rows = reset ? (result.data ?? []) : state.rows.concat(result.data ?? []);
        state.pages = result.pages ?? 1;
        state.counts = result.counts ?? state.counts;
        state.error = "";
        document.getElementById("offline").hidden = true;
    } catch (error) {
        if (!navigator.onLine || error instanceof TypeError) {
            document.getElementById("offline").hidden = false;
        } else {
            state.error = error.message || "No se pudieron cargar tus tickets.";
        }
    } finally {
        state.loading = false;
        renderList(false);
    }
}

function renderList(loading) {
    const list = document.getElementById("list");
    if (!list) return;

    document.getElementById("cPend").textContent = state.counts.pendientes;
    document.getElementById("cDone").textContent = state.counts.resueltos;
    document.getElementById("listTitle").textContent = state.status === "pendientes" ? "Tickets pendientes" : "Tickets resueltos";
    document.querySelectorAll(".kpi").forEach(b => b.classList.toggle("is-active", b.dataset.status === state.status));
    document.getElementById("more").hidden = loading || state.page >= state.pages;

    if (loading) { list.innerHTML = '<div class="skeleton"></div>'.repeat(3); return; }
    if (state.error) { list.innerHTML = `<p class="error">${esc(state.error)}</p>`; return; }
    if (!state.rows.length) {
        list.innerHTML = `<p class="empty">${state.status === "pendientes"
            ? "No tienes tickets pendientes. ¡Todo al día!" : "Aún no tienes tickets resueltos."}</p>`;
        return;
    }

    list.innerHTML = state.rows.map(t => {
        const date = t.CreatedAt ? new Date(String(t.CreatedAt).replace(" ", "T") + "Z").toLocaleDateString("es", { day: "2-digit", month: "short", year: "numeric" }) : "";
        const code = "TK-" + String(t.IdTicket).padStart(5, "0");
        return `<button class="ticket" data-id="${esc(t.IdTicket)}">
            <div class="ticket-title">${esc(t.Subject)}</div>
            <div class="ticket-meta">${esc(code)} · ${esc([t.Category, t.SubCategory].filter(Boolean).join(" › ") || "Sin categoría")} · ${esc(date)}</div>
            <div class="ticket-meta">Atiende: <b>${esc(t.AssignedName || "Sin asignar")}</b></div>
            <div class="ticket-foot">
                <span class="pill ${esc(t.Status)}">${esc(STATUS[t.Status] || t.Status)}</span>
                ${Number(t.Escalated) > 0 ? '<span class="pill escalado">Escalado</span>' : ""}
                <span class="prio prio-${esc(t.Priority)}">Prioridad ${esc(PRIO[t.Priority] || t.Priority)}</span>
            </div></button>`;
    }).join("");

    list.querySelectorAll(".ticket").forEach(btn => btn.addEventListener("click", async () => {
        try { (await mountWidget()).openMine(btn.dataset.id); } catch (e) { alert(e.message); }
    }));
}

// Al volver a la app se refresca; al recuperar internet también.
document.addEventListener("visibilitychange", () => { if (!document.hidden && hasProfile() && document.getElementById("list")) load(true, true); });
window.addEventListener("online", () => { if (document.getElementById("list")) load(true, true); });

// ---------------- Arranque ----------------

if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register(new URL("sw.js", baseUrl).href, { scope: baseUrl.pathname }).catch(() => {});
}

if (hasProfile()) renderHome(); else renderSetup();
