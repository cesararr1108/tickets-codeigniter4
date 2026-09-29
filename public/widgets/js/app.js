/*
 * Controlador del widget.
 *
 * El HTML de cada pieza está en /widgets/templates/*.html y el CSS en
 * /widgets/css/widget.css. Este archivo solo conecta datos y eventos.
 */

import {
    escapeHtml,
    loadTemplate,
    loadText,
    render,
    renderList,
    setAssetVersion
} from "./template.js";

import { colorFor, iconFor, initials } from "./icons.js";
import { obtenerCompanias } from "./companies.js";
import { obtenerBranches } from "./branches.js";
import {
    obtenerCategorias,
    obtenerFormularios,
    obtenerSubcategorias,
    obtenerTodasSubcategorias
} from "./categories.js";
import { crearTicket } from "./tickets.js";
import { obtenerDetalleTicket, obtenerMisTickets, responderTicket } from "./my-tickets.js";
import { ProjectForm } from "./forms/project-form.js";
import { RequirementForm } from "./forms/requirement-form.js";
import { IncidentForm } from "./forms/incident-form.js";

/*
 * Formularios adicionales del paso Detalle. Se elige el primero cuyas
 * palabras clave (static keywords) aparezcan en:
 *   1. el nombre del formulario asociado a la subcategoría (SubCategory.IdForm), o
 *   2. el nombre de la categoría.
 */
const EXTRA_FORMS = [ProjectForm, RequirementForm, IncidentForm];
const TOTAL_STEPS = 4;

// Paso "Detalle": datos generales + formulario del tipo de solicitud.
const DETAIL_STEP = 3;
const SEARCH_THRESHOLD = 6;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// "Mis tickets": tickets por página y nombres de los estados.
const MINE_PER_PAGE = 5;

const STATUS_LABELS = {
    abierto: "Abierto",
    en_progreso: "En progreso",
    cerrado: "Resuelto"
};

const PRIORITY_LABELS = {
    alta: "Alta",
    media: "Media",
    baja: "Baja"
};

export async function mountWidget(container, { apiUrl, version = "",perfil }) {
  //  console.log( { apiUrl, version = "",CompanieUrl,BarancheUrl })
    setAssetVersion(version);

    const [css, html] = await Promise.all([
        loadText("css/widget.css"),
        loadTemplate("widget")
    ]);


    const shadow = container.attachShadow({ mode: "open" });

    shadow.innerHTML = `<style>${css}</style>${html}`;

    const $ = id => shadow.getElementById(id);

    const el = {
        fab: $("ticketFab"),
        overlay: $("ticketOverlay"),
        close: $("ticketClose"),
        alert: $("ticketAlert"),
        tabs: shadow.querySelectorAll(".tw-tab"),
        panelNew: $("panelNew"),
        panelMine: $("panelMine"),
        stepper: $("ticketStepper"),
        content: shadow.querySelector("#panelNew .tw-content"),

        companyGrid: $("companyGrid"),
        companySearchWrap: $("companySearchWrap"),
        companySearch: $("companySearch"),
        branchBlock: $("branchBlock"),
        branchList: $("branchList"),

        categoryGrid: $("categoryGrid"),
        subcategoryBlock: $("subcategoryBlock"),
        subcategoryList: $("subcategoryList"),

        email: $("ticketEmail"),
        subject: $("ticketSubject"),
        priorityList: $("priorityList"),
        description: $("ticketDescription"),
        counter: $("descriptionCounter"),
        dropzone: $("ticketDropzone"),
        file: $("ticketFile"),
        fileName: $("ticketFileName"),
        extraForm: $("extraForm"),
        detailTitle: $("detailTitle"),
        priorityField: $("priorityField"),
        fileField: $("fileField"),
        review: $("reviewBox"),

        back: $("stepBack"),
        cancel: $("stepCancel"),
        next: $("stepNext"),
        nextLabel: $("stepNextLabel"),

        ticketsList: $("ticketsList"),
        refreshTickets: $("refreshTickets"),

        mineBadge: $("mineBadge"),
        mineFilter: $("mineFilter"),
        mineFrom: $("mineFrom"),
        mineTo: $("mineTo"),
        mineClear: $("mineClear"),
        mineTabs: $("mineTabs"),
        mineListView: $("mineListView"),
        mineDetailView: $("mineDetailView"),
        minePager: $("minePager"),
        minePrev: $("minePrev"),
        mineNext: $("mineNext"),
        minePageInfo: $("minePageInfo"),
        mineBack: $("mineBack"),
        countPendientes: $("countPendientes"),
        countResueltos: $("countResueltos"),

        success: $("ticketSuccess"),
        successId: $("ticketSuccessId"),
        successMine: $("successMine"),
        successNew: $("successNew")
    };

    const defaultFileLabel = el.fileName.textContent;

    const state = {
        step: 1,
        submitting: false,

        companies: null,
        branches: [],
        categories: null,
        subcategoriesByCategory: null,
        subcategories: [],

        company: null,
        branch: null,
        category: null,
        subcategory: null,
        priority: null,
        file: null,
        extraForm: null,
        formNames: new Map(),

        // Pestaña "Mis tickets"
        mine: { status: "pendientes", page: 1, pages: 1, from: "", to: "" }
    };

    // Datos del usuario que envía el script del anfitrión (data-*).
    perfil = perfil ?? {};

    // Solo el rol "Administrador" (data-rol) puede cambiar compañía y sucursal.
    // Los demás quedan con la compañía/sucursal enviadas por el anfitrión.
    const isAdminRole = String(perfil.role ?? "")
        .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
        .toLowerCase().includes("administrador");
    const lockedLocation = !isAdminRole && !!perfil.company;

    function prefillEmail() {
        if (perfil.email && !el.email.value) {
            el.email.value = perfil.email;
        }
    }

    // Evita pintar respuestas viejas si el usuario cambia rápido de opción.
    let branchRequest = 0;
    let subcategoryRequest = 0;

    // ==========================================
    // UTILIDADES
    // ==========================================

    function showAlert(message, type = "error") {
        el.alert.textContent = message;
        el.alert.className =
            "tw-alert tw-show " +
            (type === "success" ? "tw-alert-success" : "tw-alert-error");
    }

    function hideAlert() {
        el.alert.className = "tw-alert";
        el.alert.textContent = "";
    }

    async function showState(target, type, message) {
        target.replaceChildren(await render("state", { type, message }));
    }

    function markSelected(list, value) {
        list.querySelectorAll("[data-value]").forEach(item => {
            item.setAttribute(
                "aria-pressed",
                String(value !== null && item.dataset.value === String(value))
            );
        });
    }

    function findById(items, value) {
        return items.find(item => String(item.id) === String(value)) ?? null;
    }

    function onOptionClick(list, handler) {
        list.addEventListener("click", event => {
            const option = event.target.closest("[data-value]");

            if (option && list.contains(option)) {
                handler(option.dataset.value);
            }
        });
    }

    // ==========================================
    // PASO 1: COMPAÑÍAS
    // ==========================================

    async function loadCompanies( ) {

        if (state.companies) {
            return;
        }

        await showState(el.companyGrid, "loading", "Cargando compañías...");

        let companies;

        try {
            companies = await obtenerCompanias(apiUrl);

        } catch (error) {
            console.error("[Tickets Widget] Error cargando compañías:", error);
            await showState(el.companyGrid, "error", "No fue posible cargar las compañías.");
            return;
        }

        state.companies = companies;

        if (!companies.length) {
            await showState(el.companyGrid, "empty", "No hay compañías disponibles.");
            return;
        }

        el.companyGrid.replaceChildren(
            await renderList("option-card", companies, (company, index) => ({
                value: company.id,
                title: company.name,
                description: "",
                meta: "Código " + company.id,
                color: colorFor(index),
                icon: `<img src="https://200.122.206.204:8081/widgets/img/companies/${company.icon}" alt="">` /*escapeHtml(initials(company.name))*/,
                search: `${company.name} ${company.id}`.toLowerCase()
            }))
        );

        el.companySearchWrap.hidden = lockedLocation || companies.length <= SEARCH_THRESHOLD;
        el.companyGrid.classList.toggle("tw-locked", lockedLocation);
        el.branchList.classList.toggle("tw-locked", lockedLocation);

        if (companies.length === 1) {
            selectCompany(companies[0].id);
        }else{
             selectCompany(perfil.company,perfil.branch);//
             //precargo las ofcicinas branch
        }
    }

    function filterCompanies() {

        const term = el.companySearch.value.trim().toLowerCase();

        el.companyGrid.querySelectorAll(".tw-card").forEach(card => {
            card.hidden = term !== "" && !card.dataset.search.includes(term);
        });
    }

    async function selectCompany(value,branche ='') {

        const company = findById(state.companies ?? [], value);

        if (!company || company === state.company) {
            return;
        }

        hideAlert();

        state.company = company;
        state.branch = null;
        state.branches = [];

        markSelected(el.companyGrid, company.id);

        await loadBranches();

        if(branche!=''){
            selectBranch(branche)
        }
    }

    async function loadBranches() {

        const request = ++branchRequest;

        el.branchBlock.hidden = false;

        await showState(el.branchList, "loading", "Cargando sucursales...");

        let branches;

        try {
            branches = await obtenerBranches(apiUrl, state.company.id);
        } catch (error) {
            console.error("[Tickets Widget] Error cargando sucursales:", error);

            if (request === branchRequest) {
                await showState(el.branchList, "error", "No fue posible cargar las sucursales.");
            }
            return;
        }

        if (request !== branchRequest) {
            return;
        }

        state.branches = branches;

        if (!branches.length) {
            await showState(el.branchList, "empty", "Esta compañía no tiene sucursales registradas.");
            return;
        }

        el.branchList.replaceChildren(
            await renderList("chip", branches, branch => ({
                value: branch.id,
                label: branch.name
            }))
        );

        if (branches.length === 1) {
            selectBranch(branches[0].id);
        } else {
            el.branchBlock.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }
    }

    function selectBranch(value) {

        const branch = findById(state.branches, value);

        if (!branch) {
            return;
        }

        hideAlert();

        state.branch = branch;

        markSelected(el.branchList, branch.id);
    }

    // ==========================================
    // PASO 2: CATEGORÍAS
    // ==========================================

    async function loadCategories() {

        if (state.categories) {
            return;
        }

        await showState(el.categoryGrid, "loading", "Cargando categorías...");

        const [categoriesResult, subcategoriesResult, formsResult] = await Promise.allSettled([
            obtenerCategorias(apiUrl),
            obtenerTodasSubcategorias(apiUrl),
            obtenerFormularios(apiUrl)
        ]);

        // Nombres de TicketForms (para SubCategory.IdForm). Opcional.
        if (formsResult.status === "fulfilled") {
            state.formNames = formsResult.value;
        }

        if (categoriesResult.status === "rejected") {
            console.error("[Tickets Widget] Error cargando categorías:", categoriesResult.reason);
            await showState(el.categoryGrid, "error", "No fue posible cargar las categorías.");
            return;
        }

        const categories = categoriesResult.value;

        state.categories = categories;

        // Si /subcategories falla, se consultan por categoría al seleccionar.
        if (subcategoriesResult.status === "fulfilled") {

            state.subcategoriesByCategory = new Map();

            subcategoriesResult.value.forEach(sub => {
                const key = String(sub.categoryId);

                if (!state.subcategoriesByCategory.has(key)) {
                    state.subcategoriesByCategory.set(key, []);
                }

                state.subcategoriesByCategory.get(key).push(sub);
            });
        }

        if (!categories.length) {
            await showState(el.categoryGrid, "empty", "No hay categorías disponibles.");
            return;
        }

        el.categoryGrid.replaceChildren(
            await renderList("option-card", categories, (category, index) => {
                    console.log({
                        categories
                    })
                const subs = state.subcategoriesByCategory?.get(String(category.id));

                return {
                    value: category.id,
                    title: category.name,
                    description: subs ? summarize(subs) : category.description,
                    meta: subs ? countLabel(subs.length) : "",
                    color: colorFor(index),
                    icon: iconFor(category.name),
                    search: category.name.toLowerCase()
                };
            })
        );

        if (categories.length === 1) {
            selectCategory(categories[0].id);
        }
    }

    function summarize(subs) {

        const names = subs.slice(0, 3).map(sub => sub.name).join(", ");

        return subs.length > 3 ? names + "…" : names;
    }

    function countLabel(count) {

        if (!count) {
            return "Sin subcategorías";
        }

        return count === 1 ? "1 subcategoría" : `${count} subcategorías`;
    }

    async function selectCategory(value) {

        const category = findById(state.categories ?? [], value);

        if (!category || category === state.category) {
            return;
        }

        hideAlert();

        state.category = category;
        state.subcategory = null;
        state.subcategories = [];

        markSelected(el.categoryGrid, category.id);

        setExtraForm(category);

        await loadSubcategories();
    }

    /*
     * Clase de formulario adicional para la categoría/subcategoría elegidas.
     */
    function resolveExtraForm(category, subcategory) {

        const formName = subcategory?.formId != null
            ? state.formNames.get(String(subcategory.formId))
            : "";

        for (const text of [formName, category?.name]) {
            const FormClass = EXTRA_FORMS.find(form => form.matches(text));

            if (FormClass) {
                return FormClass;
            }
        }

        return null;
    }

    /*
     * Datos automáticos para los formularios (vienen del script anfitrión).
     */
    function formContext() {
        return {
            requester: perfil.name || el.email.value.trim() || perfil.email || "",
            email: el.email.value.trim() || perfil.email || "",
            area: perfil.area || "",
            sede: state.branch?.name || "",
            perfil
        };
    }

    /*
     * Monta (o quita) el formulario adicional en el paso 3.
     */
    function setExtraForm(category, subcategory = null) {

        const FormClass = resolveExtraForm(category, subcategory);

        // Misma clase de formulario: se conservan las respuestas.
        if (FormClass && state.extraForm instanceof FormClass) {
            state.extraForm.category = category;
            state.extraForm.setSubcategory(subcategory);
            return;
        }

        state.extraForm?.unmount();
        state.extraForm = null;

        if (!FormClass) {
            applyFormLayout();
            return;
        }

        const form = new FormClass(el.extraForm, { apiUrl });

        state.extraForm = form;
        form.category = category;
        form.subcategory = subcategory;

        applyFormLayout();

        form.mount(formContext()).catch(error => {
            console.error("[Tickets Widget] Error cargando el formulario adicional:", error);

            if (state.extraForm === form) {
                state.extraForm = null;
                form.unmount();
                applyFormLayout();
            }
        });
    }

    /*
     * Ajusta el paso Detalle al formulario activo:
     *  - título con el tipo de solicitud;
     *  - sin "Prioridad" si el formulario la trae (Proyecto: prioridad estratégica);
     *  - sin "Archivo adjunto" general si el formulario tiene su zona de archivos.
     */
    function applyFormLayout() {

        const FormClass = state.extraForm?.constructor ?? null;

        el.detailTitle.textContent = FormClass?.title ?? "Detalle de la solicitud";
        el.priorityField.hidden = Boolean(FormClass?.ownsPriority);
        el.fileField.hidden = Boolean(FormClass);

        if (FormClass) {
            el.file.value = "";
            setFile(null);
        }
    }

    /*
     * Prioridad del ticket: la del formulario si la define, o la elegida.
     */
    function ticketPriority() {
        return state.extraForm?.constructor.ownsPriority
            ? state.extraForm.priority()
            : state.priority;
    }

    async function loadSubcategories() {

        const request = ++subcategoryRequest;

        let subs = state.subcategoriesByCategory?.get(String(state.category.id));

        if (!subs && !state.subcategoriesByCategory) {

            el.subcategoryBlock.hidden = false;

            await showState(el.subcategoryList, "loading", "Cargando subcategorías...");

            try {
                subs = await obtenerSubcategorias(apiUrl, state.category.id);
            } catch (error) {
                console.error("[Tickets Widget] Error cargando subcategorías:", error);
                subs = [];
            }
        }

        if (request !== subcategoryRequest) {
            return;
        }

        state.subcategories = subs ?? [];

        if (!state.subcategories.length) {
            el.subcategoryBlock.hidden = true;
            el.subcategoryList.replaceChildren();
            return;
        }

        el.subcategoryList.replaceChildren(
            await renderList("chip", state.subcategories, sub => ({
                value: sub.id,
                label: sub.name
            }))
        );

        el.subcategoryBlock.hidden = false;
        el.subcategoryBlock.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }

    function selectSubcategory(value) {

        const sub = findById(state.subcategories, value);

        // Un segundo clic quita la selección (es opcional).
        state.subcategory = sub && sub !== state.subcategory ? sub : null;

        markSelected(el.subcategoryList, state.subcategory?.id ?? null);

        // La subcategoría puede cambiar el formulario o sus campos.
        setExtraForm(state.category, state.subcategory);
    }

    // ==========================================
    // PASO 3: DETALLE
    // ==========================================

    function selectPriority(value) {

        hideAlert();

        state.priority = value in PRIORITY_LABELS ? value : null;

        markSelected(el.priorityList, state.priority);
    }

    function updateCounter() {
        el.counter.textContent =
            `${el.description.value.length} / ${el.description.maxLength}`;
    }

    function setFile(file) {

        state.file = file ?? null;

        el.fileName.textContent = state.file
            ? `${state.file.name} (${formatSize(state.file.size)})`
            : defaultFileLabel;

        el.dropzone.classList.toggle("tw-has-file", Boolean(state.file));
    }

    function formatSize(bytes) {

        if (bytes < 1024 * 1024) {
            return Math.max(1, Math.round(bytes / 1024)) + " KB";
        }

        return (bytes / 1024 / 1024).toFixed(1) + " MB";
    }

    // ==========================================
    // PASO 4: REVISIÓN
    // ==========================================

    async function renderReview() {

        el.review.replaceChildren(
            await render("review", {
                company: state.company?.name,
                branch: state.branch?.name,
                category: state.category?.name,
                subcategory: state.subcategory?.name ?? "Sin subcategoría",
                email: el.email.value.trim(),
                subject: el.subject.value.trim(),
                priority: PRIORITY_LABELS[ticketPriority()],
                priorityClass: ticketPriority(),
                description: el.description.value.trim(),
                file: state.file?.name ?? "Sin archivo adjunto"
            })
        );

        // Respuestas del formulario adicional (ej. Proyecto).
        if (state.extraForm) {
            el.review.firstElementChild?.append(state.extraForm.reviewNode());
        }
    }

    // ==========================================
    // NAVEGACIÓN ENTRE PASOS
    // ==========================================
    function validateStep(step) {

        if (step === 1) {
            if (!state.company) return "Selecciona una compañía.";
            if (!state.branch) return "Selecciona una sucursal.";
        }

        if (step === 2) {
            if (!state.category) return "Selecciona una categoría.";
        }

        if (step === DETAIL_STEP) {
            if (!EMAIL_RE.test(el.email.value.trim())) return "Ingresa un correo válido.";
            if (!el.subject.value.trim()) return "Ingresa el asunto.";
            if (!state.extraForm?.constructor.ownsPriority && !state.priority) return "Selecciona la prioridad.";
            if (!el.description.value.trim()) return "Ingresa una descripción.";

            const extraError = state.extraForm?.validate();
            if (extraError) return extraError;
        }

        return null;
    }

    /*
     * Marca el paso actual y los completados.
     */
    function renderStepper() {
        el.stepper.querySelectorAll(".tw-stepper-item").forEach(item => {
            const itemStep = Number(item.dataset.step);

            item.classList.toggle("tw-current", itemStep === state.step);
            item.classList.toggle("tw-done", itemStep < state.step);
        });
    }

    function goTo(step) {

        state.step = step;

        hideAlert();

        shadow.querySelectorAll(".tw-step").forEach(section => {
            section.hidden = Number(section.dataset.step) !== step;
        });

        renderStepper();

        el.back.disabled = step === 1;
        el.nextLabel.textContent = step === TOTAL_STEPS ? "Crear ticket" : "Siguiente";
        el.content.scrollTop = 0;

        if (step === 2) {
            loadCategories();
        }

        // Si se volvió a cambiar la sucursal o el correo, se actualizan
        // los campos automáticos del formulario adicional.
        if (step === DETAIL_STEP && state.extraForm) {
            const context = formContext();
            state.extraForm.setField("sede", context.sede);
            state.extraForm.setField("solicitante", context.requester);
        }

        if (step === TOTAL_STEPS) {
            renderReview();
        }
    }

    function next() {

        if (state.step === TOTAL_STEPS) {
            submit();
            return;
        }

        const error = validateStep(state.step);

        if (error) {
            showAlert(error);
            return;
        }

        goTo(state.step + 1);
    }

    function resetForm() {

        branchRequest++;
        subcategoryRequest++;

        Object.assign(state, {
            branches: [],
            subcategories: [],
            company: null,
            branch: null,
            category: null,
            subcategory: null,
            priority: null
        });

        // Quita el formulario adicional (ej. Proyecto).
        state.extraForm?.unmount();
        state.extraForm = null;
        applyFormLayout();

        markSelected(el.companyGrid, null);
        markSelected(el.categoryGrid, null);
        markSelected(el.priorityList, null);

        el.branchBlock.hidden = true;
        el.branchList.replaceChildren();
        el.subcategoryBlock.hidden = true;
        el.subcategoryList.replaceChildren();

        el.companySearch.value = "";
        filterCompanies();

        el.email.value = "";
        el.subject.value = "";
        el.description.value = "";
        el.file.value = "";
        updateCounter();
        setFile(null);
        prefillEmail();

        goTo(1);

        // Devuelve la promesa para poder mostrar un mensaje después de la
        // preselección (que limpia las alertas).
        if (state.companies?.length === 1) {
            return selectCompany(state.companies[0].id);
        }

        if (perfil.company) {
            return selectCompany(perfil.company, perfil.branch);
        }

        return Promise.resolve();
    }

    async function submit() {

        if (state.submitting) {
            return;
        }

        for (let step = 1; step < TOTAL_STEPS; step++) {
            const error = validateStep(step);

            if (error) {
                goTo(step);
                showAlert(error);
                return;
            }
        }

        const email = el.email.value.trim();

        // Adjunto principal + documentos del formulario adicional.
        const files = [state.file, ...(state.extraForm?.files ?? [])].filter(Boolean);

        // El backend guarda en una sola transacción: ticket, descripción
        // (primer mensaje), respuestas del formulario y adjuntos.
        const data = {
            CodCompanies: state.company.id,
            CodBranches: state.branch.id,
            IdCategory: state.category.id,
            RequesterEmail: email,
            Subject: el.subject.value.trim(),
            Priority: ticketPriority(),
            Status: "abierto",
            Description: el.description.value.trim(),
            SenderName: perfil.name || email
        };

        if (state.subcategory) {
            data.IdSubCategory = state.subcategory.id;
        }

        if (state.extraForm) {
            data.FormKey = state.extraForm.constructor.key;
            data.Answers = state.extraForm.answers();
        }

        state.submitting = true;
        el.next.disabled = true;
        el.nextLabel.textContent = "Creando...";

        try {
            const result = await crearTicket(apiUrl, data, files);

            const ticketId =
                result?.IdTicket ?? result?.data?.IdTicket ?? result?.id;

            await resetForm();

            showSuccess(ticketId);
            loadMineCounts();

        } catch (error) {
            console.error("[Tickets Widget] Error creando ticket:", error);
            showAlert(error.message || "No fue posible crear el ticket.");

        } finally {
            state.submitting = false;
            el.next.disabled = false;

            if (state.step === TOTAL_STEPS) {
                el.nextLabel.textContent = "Crear ticket";
            }
        }
    }

    // ==========================================
    // MIS TICKETS
    // ==========================================

    /*
     * Correo con el que se buscan "Mis tickets": el del anfitrión o el
     * escrito en el formulario.
     */
    function mineEmail() {
        return (perfil.email || el.email.value || "").trim();
    }

    function formatDate(value) {

        if (!value) {
            return "";
        }

        // La BD guarda en UTC ("2026-09-28 15:57:44").
        const date = new Date(String(value).replace(" ", "T").replace(/(\.\d+)?$/, "") + "Z");

        return Number.isNaN(date.getTime())
            ? String(value)
            : date.toLocaleString("es-CO", { dateStyle: "medium", timeStyle: "short" });
    }

    function statusClass(status) {
        return String(status ?? "").toLowerCase().replace(/[^a-z_]/g, "");
    }

    /*
     * Contadores de las pestañas Pendientes / Resueltos (según las fechas).
     */
    function updateCounts(counts) {
        el.countPendientes.textContent = Number(counts?.pendientes ?? 0);
        el.countResueltos.textContent = Number(counts?.resueltos ?? 0);
    }

    /*
     * Badge de la pestaña "Mis tickets": todos los pendientes (sin fechas).
     */
    function updateBadge(counts) {

        const pending = Number(counts?.pendientes ?? 0);

        el.mineBadge.textContent = pending > 99 ? "99+" : pending;
        el.mineBadge.hidden = pending === 0;
    }

    /*
     * Solo el conteo (para el badge de la pestaña).
     */
    async function loadMineCounts() {

        const email = mineEmail();

        if (!EMAIL_RE.test(email)) {
            return;
        }

        try {
            const result = await obtenerMisTickets(apiUrl, { email, perPage: 1 });
            updateBadge(result?.counts);
        } catch (error) {
            console.error("[Tickets Widget] Error contando tickets:", error);
        }
    }

    async function loadTickets() {

        showMineList();

        const email = mineEmail();

        el.minePager.hidden = true;

        if (!EMAIL_RE.test(email)) {
            await showState(el.ticketsList, "empty", "Escribe tu correo en \"Nuevo ticket\" para ver tus solicitudes.");
            return;
        }

        if (state.mine.from && state.mine.to && state.mine.from > state.mine.to) {
            await showState(el.ticketsList, "error", "La fecha inicial no puede ser mayor que la final.");
            return;
        }

        await showState(el.ticketsList, "loading", "Cargando tickets...");

        let result;

        try {
            result = await obtenerMisTickets(apiUrl, {
                email,
                status: state.mine.status,
                from: state.mine.from,
                to: state.mine.to,
                page: state.mine.page,
                perPage: MINE_PER_PAGE
            });
        } catch (error) {
            console.error("[Tickets Widget] Error cargando tickets:", error);
            await showState(el.ticketsList, "error", "No fue posible cargar tus tickets.");
            return;
        }

        updateCounts(result?.counts);

        if (!state.mine.from && !state.mine.to) {
            updateBadge(result?.counts);
        }

        state.mine.page = Number(result?.page ?? 1);
        state.mine.pages = Number(result?.pages ?? 1);

        const tickets = result?.data ?? [];

        if (!tickets.length) {
            const range = state.mine.from || state.mine.to ? " en ese rango de fechas" : "";

            await showState(
                el.ticketsList,
                "empty",
                state.mine.status === "resueltos"
                    ? `No tienes tickets resueltos${range}.`
                    : `No tienes tickets pendientes${range}.`
            );
            return;
        }

        el.ticketsList.replaceChildren(
            await renderList("my-ticket-item", tickets, ticket => ({
                id: ticket.IdTicket,
                title: ticket.Subject,
                category: [ticket.Category, ticket.SubCategory].filter(Boolean).join(" / ") || "Sin categoría",
                date: formatDate(ticket.CreatedAt),
                assigned: ticket.AssignedName || "Por asignar",
                escalatedHtml: Number(ticket.Escalated) > 0
                    ? '<span class="tw-status tw-status-escalado" title="Escalado al administrador">Escalado</span>'
                    : "",
                priority: PRIORITY_LABELS[ticket.Priority] ?? ticket.Priority ?? "",
                priorityClass: statusClass(ticket.Priority),
                status: STATUS_LABELS[ticket.Status] ?? ticket.Status,
                statusClass: statusClass(ticket.Status)
            }))
        );

        el.minePager.hidden = state.mine.pages <= 1;
        el.minePageInfo.textContent = `Página ${state.mine.page} de ${state.mine.pages}`;
        el.minePrev.disabled = state.mine.page <= 1;
        el.mineNext.disabled = state.mine.page >= state.mine.pages;
    }

    function selectMineTab(status, load = true) {

        state.mine.status = status === "resueltos" ? "resueltos" : "pendientes";
        state.mine.page = 1;

        el.mineTabs.querySelectorAll("[data-status]").forEach(tab => {
            tab.classList.toggle("tw-active", tab.dataset.status === state.mine.status);
        });

        if (load) {
            loadTickets();
        }
    }

    function showMineList() {
        el.mineDetailView.hidden = true;
        el.mineDetailView.replaceChildren();
        el.mineListView.hidden = false;
        el.mineBack.hidden = true;
    }

    /*
     * Detalle de un ticket propio: datos, respuestas, seguimiento de TI,
     * conversación y adjuntos.
     */
    async function openTicketDetail(id) {

        el.mineListView.hidden = true;
        el.mineDetailView.hidden = false;
        el.mineBack.hidden = false;
        el.panelMine.querySelector(".tw-content").scrollTop = 0;

        await showState(el.mineDetailView, "loading", "Cargando ticket...");

        let detail;

        try {
            detail = await obtenerDetalleTicket(apiUrl, id, mineEmail());
        } catch (error) {
            console.error("[Tickets Widget] Error cargando el ticket:", error);
            await showState(el.mineDetailView, "error", "No fue posible cargar el ticket.");
            return;
        }

        const t = detail.ticket ?? {};

        const pairs = (title, items) => items?.length
            ? `<section class="tw-detail-block"><p class="tw-section-label">${escapeHtml(title)}</p><dl class="tw-answers">`
                + items.map(item => `<div><dt>${escapeHtml(item.label)}</dt><dd>${escapeHtml(item.value || "—")}</dd></div>`).join("")
                + "</dl></section>"
            : "";

        const messages = detail.messages?.length
            ? detail.messages.map(messageHtml).join("")
            : '<p class="tw-muted" data-empty>Sin mensajes.</p>';

        const closed = t.Status === "cerrado";

        const files = detail.attachments?.length
            ? `<section class="tw-detail-block"><p class="tw-section-label">Adjuntos</p><ul class="tw-file-list">`
                + detail.attachments.map(name => `<li><span>${escapeHtml(name)}</span></li>`).join("")
                + "</ul></section>"
            : "";

        el.mineDetailView.replaceChildren(
            await render("my-ticket-detail", {
                id: t.IdTicket,
                title: t.Subject,
                status: STATUS_LABELS[t.Status] ?? t.Status,
                statusClass: statusClass(t.Status),
                priority: PRIORITY_LABELS[t.Priority] ?? t.Priority ?? "",
                priorityClass: statusClass(t.Priority),
                company: t.Companies ?? "",
                branch: t.Branches ?? "",
                category: t.Category ?? "Sin categoría",
                subcategory: t.SubCategory ?? "",
                date: formatDate(t.CreatedAt),
                assigned: t.AssignedName || "Por asignar",
                escalationHtml: t.Escalated
                    ? `<div class="tw-escalated-note"><strong>Tu solicitud fue escalada al administrador</strong><span>Desde ${escapeHtml(formatDate(t.EscalatedAt))}. El equipo de TI la está revisando con un nivel superior.</span></div>`
                    : "",
                followUpHtml: pairs("Seguimiento de TI", detail.followUp),
                answersHtml: pairs("Información de la solicitud", detail.answers),
                messagesHtml: messages,
                filesHtml: files,
                replyHidden: closed ? "hidden" : "",
                closedHidden: closed ? "" : "hidden"
            })
        );
    }

    function messageHtml(m) {
        return `
            <div class="tw-msg tw-msg-${m.type === "agente" ? "agent" : "client"}">
                <div class="tw-msg-head"><strong>${escapeHtml(m.sender)}</strong><span>${escapeHtml(formatDate(m.createdAt))}</span></div>
                <p>${escapeHtml(m.message)}</p>
            </div>`;
    }

    /*
     * El solicitante responde en la conversación de su ticket.
     */
    async function sendReply(form) {

        const textarea = form.querySelector("textarea");
        const button = form.querySelector("button[type=submit]");
        const error = form.querySelector("[data-reply-error]");
        const message = textarea.value.trim();

        if (!message || button.disabled) {
            return;
        }

        button.disabled = true;
        error.textContent = "";

        try {
            const saved = await responderTicket(apiUrl, form.dataset.reply, {
                email: mineEmail(),
                name: perfil.name || mineEmail(),
                message
            });

            const thread = el.mineDetailView.querySelector("[data-messages]");
            thread.querySelector("[data-empty]")?.remove();
            thread.insertAdjacentHTML("beforeend", messageHtml(saved));

            textarea.value = "";
            thread.lastElementChild?.scrollIntoView({ behavior: "smooth", block: "nearest" });
        } catch (err) {
            console.error("[Tickets Widget] Error enviando el mensaje:", err);
            error.textContent = err.message || "No fue posible enviar el mensaje.";
        } finally {
            button.disabled = false;
            textarea.focus();
        }
    }

    // ==========================================
    // CONFIRMACIÓN
    // ==========================================

    function showSuccess(ticketId) {
        el.successId.textContent = ticketId ? "#" + ticketId : "";
        el.success.hidden = false;
        el.successMine.focus();
    }

    function hideSuccess() {
        el.success.hidden = true;
    }

    // ==========================================
    // MODAL Y PESTAÑAS
    // ==========================================

    function openModal() {
        el.overlay.classList.add("tw-open");
        el.overlay.setAttribute("aria-hidden", "false");

        loadCompanies();
        loadMineCounts();

    }

    function closeModal() {
        el.overlay.classList.remove("tw-open");
        el.overlay.setAttribute("aria-hidden", "true");
    }

    function switchTab(tab) {

        el.tabs.forEach(item => item.classList.toggle("tw-active", item === tab));

        const isNew = tab.dataset.tab === "new";

        el.panelNew.classList.toggle("tw-active", isNew);
        el.panelMine.classList.toggle("tw-active", !isNew);

        hideAlert();

        if (!isNew) {
            hideSuccess();
            loadTickets();
        }
    }

    function tabByName(name) {
        return Array.from(el.tabs).find(tab => tab.dataset.tab === name);
    }

    // ==========================================
    // BOTÓN FLOTANTE MOVIBLE
    // ==========================================

    /*
     * El botón se puede arrastrar (mouse o dedo) a cualquier parte de la
     * pantalla; la posición se recuerda en el navegador. Un clic sin
     * arrastrar abre el modal.
     */
    function makeFabDraggable() {

        const KEY = "tw-fab-position";
        const MARGIN = 8;
        const fab = el.fab;

        let start = null;
        let moved = false;

        const clamp = (x, y) => ({
            x: Math.min(Math.max(MARGIN, x), window.innerWidth - fab.offsetWidth - MARGIN),
            y: Math.min(Math.max(MARGIN, y), window.innerHeight - fab.offsetHeight - MARGIN)
        });

        const place = (x, y) => {
            const pos = clamp(x, y);
            fab.style.left = pos.x + "px";
            fab.style.top = pos.y + "px";
            fab.style.right = "auto";
            fab.style.bottom = "auto";
            return pos;
        };

        // Posición guardada (si el navegador permite localStorage).
        try {
            const saved = JSON.parse(localStorage.getItem(KEY) || "null");
            if (saved && Number.isFinite(saved.x) && Number.isFinite(saved.y)) {
                requestAnimationFrame(() => place(saved.x, saved.y));
            }
        } catch (e) {}

        fab.addEventListener("pointerdown", event => {
            if (event.button !== 0) return;

            const rect = fab.getBoundingClientRect();
            start = { px: event.clientX, py: event.clientY, x: rect.left, y: rect.top };
            moved = false;
            fab.setPointerCapture(event.pointerId);
        });

        fab.addEventListener("pointermove", event => {
            if (!start) return;

            const dx = event.clientX - start.px;
            const dy = event.clientY - start.py;

            // Umbral para no confundir un clic con un arrastre.
            if (!moved && Math.hypot(dx, dy) < 6) return;

            moved = true;
            fab.classList.add("tw-fab-dragging");
            place(start.x + dx, start.y + dy);
        });

        const end = () => {
            if (!start) return;

            start = null;
            fab.classList.remove("tw-fab-dragging");

            if (moved) {
                const rect = fab.getBoundingClientRect();
                try {
                    localStorage.setItem(KEY, JSON.stringify({ x: rect.left, y: rect.top }));
                } catch (e) {}
            }
        };

        fab.addEventListener("pointerup", end);
        fab.addEventListener("pointercancel", end);

        fab.addEventListener("click", event => {
            // Si se arrastró, no se abre el modal.
            if (moved) {
                event.preventDefault();
                moved = false;
                return;
            }
            openModal();
        });

        // Si cambia el tamaño de la ventana, que no quede fuera de la pantalla.
        window.addEventListener("resize", () => {
            if (fab.style.left) {
                const rect = fab.getBoundingClientRect();
                place(rect.left, rect.top);
            }
        });
    }

    // ==========================================
    // EVENTOS
    // ==========================================

    makeFabDraggable();
    el.close.addEventListener("click", closeModal);

    // El modal solo se cierra con la X o con "Cancelar" (no con clic afuera
    // ni con Escape), para no perder lo que se está escribiendo.

    el.tabs.forEach(tab =>
        tab.addEventListener("click", () => switchTab(tab))
    );

    onOptionClick(el.companyGrid, value => { if (!lockedLocation) selectCompany(value); });
    onOptionClick(el.branchList, value => { if (!lockedLocation) selectBranch(value); });
    onOptionClick(el.categoryGrid, selectCategory);
    onOptionClick(el.subcategoryList, selectSubcategory);
    onOptionClick(el.priorityList, selectPriority);

    el.companySearch.addEventListener("input", filterCompanies);
    el.description.addEventListener("input", updateCounter);

    // Si el anfitrión no envía el nombre, "Solicitante" sigue al correo.
    el.email.addEventListener("input", () =>
        state.extraForm?.setField("solicitante", formContext().requester)
    );

    el.file.addEventListener("change", () => setFile(el.file.files[0]));

    ["dragenter", "dragover"].forEach(type =>
        el.dropzone.addEventListener(type, event => {
            event.preventDefault();
            el.dropzone.classList.add("tw-dragging");
        })
    );

    ["dragleave", "drop"].forEach(type =>
        el.dropzone.addEventListener(type, event => {
            event.preventDefault();
            el.dropzone.classList.remove("tw-dragging");
        })
    );

    el.dropzone.addEventListener("drop", event => {
        const file = event.dataTransfer?.files?.[0];

        if (file) {
            setFile(file);
        }
    });

    el.stepper.addEventListener("click", event => {
        const item = event.target.closest(".tw-stepper-item.tw-done");

        if (item) {
            goTo(Number(item.dataset.step));
        }
    });

    el.back.addEventListener("click", () => goTo(Math.max(1, state.step - 1)));
    el.next.addEventListener("click", next);

    el.cancel.addEventListener("click", () => {
        resetForm();
        closeModal();
    });

    el.refreshTickets.addEventListener("click", () => {
        if (el.mineDetailView.hidden) {
            loadTickets();
        } else {
            openTicketDetail(state.mine.openId);
        }
    });

    el.mineFilter.addEventListener("submit", event => {
        event.preventDefault();
        state.mine.from = el.mineFrom.value;
        state.mine.to = el.mineTo.value;
        state.mine.page = 1;
        loadTickets();
    });

    el.mineClear.addEventListener("click", () => {
        el.mineFrom.value = "";
        el.mineTo.value = "";
        state.mine.from = "";
        state.mine.to = "";
        state.mine.page = 1;
        loadTickets();
    });

    el.mineTabs.addEventListener("click", event => {
        const tab = event.target.closest("[data-status]");
        if (tab) {
            selectMineTab(tab.dataset.status);
        }
    });

    el.ticketsList.addEventListener("click", event => {
        const item = event.target.closest("[data-ticket]");
        if (item) {
            state.mine.openId = item.dataset.ticket;
            openTicketDetail(item.dataset.ticket);
        }
    });

    el.minePrev.addEventListener("click", () => {
        state.mine.page = Math.max(1, state.mine.page - 1);
        loadTickets();
    });

    el.mineNext.addEventListener("click", () => {
        state.mine.page = Math.min(state.mine.pages, state.mine.page + 1);
        loadTickets();
    });

    el.mineBack.addEventListener("click", loadTickets);

    // Respuesta del solicitante en el detalle del ticket.
    el.mineDetailView.addEventListener("submit", event => {
        const form = event.target.closest("form[data-reply]");

        if (form) {
            event.preventDefault();
            sendReply(form);
        }
    });

    // Enter envía; Shift+Enter hace salto de línea.
    el.mineDetailView.addEventListener("keydown", event => {
        const form = event.target.closest("form[data-reply]");

        if (form && event.key === "Enter" && !event.shiftKey && event.target.tagName === "TEXTAREA") {
            event.preventDefault();
            sendReply(form);
        }
    });

    el.successMine.addEventListener("click", () => {
        selectMineTab("pendientes", false);
        switchTab(tabByName("mine"));
    });

    el.successNew.addEventListener("click", hideSuccess);

    updateCounter();
    prefillEmail();
    goTo(1);

    return {
        open: openModal,
        close: closeModal,
        reset: resetForm
    };
}
