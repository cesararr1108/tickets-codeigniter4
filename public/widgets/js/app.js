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
import { crearTicket, obtenerTickets } from "./tickets.js";
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
const SEARCH_THRESHOLD = 6;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

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
        review: $("reviewBox"),

        back: $("stepBack"),
        cancel: $("stepCancel"),
        next: $("stepNext"),
        nextLabel: $("stepNextLabel"),

        ticketsList: $("ticketsList"),
        refreshTickets: $("refreshTickets")
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
        formNames: new Map()
    };

    // Datos del usuario que envía el script del anfitrión (data-*).
    perfil = perfil ?? {};

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

        el.companySearchWrap.hidden = companies.length <= SEARCH_THRESHOLD;

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
            state.extraForm.setSubcategory(subcategory);
            return;
        }

        state.extraForm?.unmount();
        state.extraForm = null;

        if (!FormClass) {
            return;
        }

        const form = new FormClass(el.extraForm, { apiUrl });

        state.extraForm = form;
        form.subcategory = subcategory;

        form.mount(formContext()).catch(error => {
            console.error("[Tickets Widget] Error cargando el formulario adicional:", error);

            if (state.extraForm === form) {
                state.extraForm = null;
                form.unmount();
            }
        });
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
                priority: PRIORITY_LABELS[state.priority],
                priorityClass: state.priority,
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

        if (step === 3) {
            if (!EMAIL_RE.test(el.email.value.trim())) return "Ingresa un correo válido.";
            if (!el.subject.value.trim()) return "Ingresa el asunto.";
            if (!state.priority) return "Selecciona la prioridad.";
            if (!el.description.value.trim()) return "Ingresa una descripción.";

            const extraError = state.extraForm?.validate();
            if (extraError) return extraError;
        }

        return null;
    }

    function goTo(step) {

        state.step = step;

        hideAlert();

        shadow.querySelectorAll(".tw-step").forEach(section => {
            section.hidden = Number(section.dataset.step) !== step;
        });

        el.stepper.querySelectorAll(".tw-stepper-item").forEach(item => {
            const itemStep = Number(item.dataset.step);

            item.classList.toggle("tw-current", itemStep === step);
            item.classList.toggle("tw-done", itemStep < step);
        });

        el.back.disabled = step === 1;
        el.nextLabel.textContent = step === TOTAL_STEPS ? "Crear ticket" : "Siguiente";
        el.content.scrollTop = 0;

        if (step === 2) {
            loadCategories();
        }

        // Si se volvió a cambiar la sucursal o el correo, se actualizan
        // los campos automáticos del formulario adicional.
        if (step === 3 && state.extraForm) {
            const context = formContext();
            state.extraForm.setField("sede", context.sede);
            state.extraForm.setField("solicitante", context.requester);
        }

        if (step === 4) {
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
            Priority: state.priority,
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

            showAlert(
                ticketId
                    ? `El ticket #${ticketId} fue creado correctamente.`
                    : "El ticket fue creado correctamente.",
                "success"
            );

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

    async function loadTickets() {

        await showState(el.ticketsList, "loading", "Cargando tickets...");

        let tickets;

        try {
            tickets = await obtenerTickets(apiUrl);
        } catch (error) {
            console.error("[Tickets Widget] Error cargando tickets:", error);
            await showState(el.ticketsList, "error", "No fue posible cargar tus tickets.");
            return;
        }

        if (!tickets.length) {
            await showState(el.ticketsList, "empty", "No tienes tickets registrados.");
            return;
        }

        el.ticketsList.replaceChildren(
            await renderList("ticket-item", tickets, ticket => ({
                id: ticket.id,
                title: ticket.title,
                meta: ticket.priority
                    ? "Prioridad " + String(ticket.priority).toLowerCase()
                    : "Sin prioridad",
                status: String(ticket.status).replaceAll("_", " "),
                statusClass: String(ticket.status).toLowerCase().replace(/[^a-z_]/g, "")
            }))
        );
    }

    // ==========================================
    // MODAL Y PESTAÑAS
    // ==========================================

    function onKeydown(event) {
        if (event.key === "Escape") {
            closeModal();
        }
    }

    function openModal() {
        el.overlay.classList.add("tw-open");
        el.overlay.setAttribute("aria-hidden", "false");
        document.addEventListener("keydown", onKeydown);

        loadCompanies();

    }

    function closeModal() {
        el.overlay.classList.remove("tw-open");
        el.overlay.setAttribute("aria-hidden", "true");
        document.removeEventListener("keydown", onKeydown);
    }

    function switchTab(tab) {

        el.tabs.forEach(item => item.classList.toggle("tw-active", item === tab));

        const isNew = tab.dataset.tab === "new";

        el.panelNew.classList.toggle("tw-active", isNew);
        el.panelMine.classList.toggle("tw-active", !isNew);

        if (!isNew) {
            loadTickets();
        }
    }

    // ==========================================
    // EVENTOS
    // ==========================================

    el.fab.addEventListener("click", openModal);
    el.close.addEventListener("click", closeModal);

    el.overlay.addEventListener("click", event => {
        if (event.target === el.overlay) {
            closeModal();
        }
    });

    el.tabs.forEach(tab =>
        tab.addEventListener("click", () => switchTab(tab))
    );

    onOptionClick(el.companyGrid, selectCompany);
    onOptionClick(el.branchList, selectBranch);
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

    el.refreshTickets.addEventListener("click", loadTickets);

    updateCounter();
    prefillEmail();
    goTo(1);

    return {
        open: openModal,
        close: closeModal,
        reset: resetForm
    };
}
