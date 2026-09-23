/*
 * Comportamiento del panel. Todo funciona sin JS (formularios normales);
 * este archivo solo mejora la experiencia.
 */
(() => {
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

    // ==========================================
    // CSRF (CodeIgniter regenera el token en cada POST)
    // ==========================================
    const csrf = {
        name: () => $('meta[name="csrf-token-name"]')?.content,
        value: () => $('meta[name="csrf-token"]')?.content,
        update(hash) {
            if (!hash) return;
            $('meta[name="csrf-token"]')?.setAttribute('content', hash);
            $$(`input[name="${csrf.name()}"]`).forEach(input => { input.value = hash; });
        }
    };

    // ==========================================
    // TEMA CLARO / OSCURO
    // ==========================================
    $$('[data-toggle-theme]').forEach(button => {
        button.addEventListener('click', () => {
            const root = document.documentElement;
            const isDark = root.dataset.theme
                ? root.dataset.theme === 'dark'
                : matchMedia('(prefers-color-scheme: dark)').matches;
            const next = isDark ? 'light' : 'dark';

            root.dataset.theme = next;
            try { localStorage.setItem('panel-theme', next); } catch (e) {}
        });
    });

    // ==========================================
    // MENÚ LATERAL (móvil) Y MENÚ DE USUARIO
    // ==========================================
    $$('[data-toggle-sidebar]').forEach(el =>
        el.addEventListener('click', () => document.body.classList.toggle('sidebar-open'))
    );

    const menuButton = $('[data-toggle-menu]');
    const menu = menuButton?.nextElementSibling;

    menuButton?.addEventListener('click', event => {
        event.stopPropagation();
        menu.hidden = !menu.hidden;
    });

    document.addEventListener('click', event => {
        if (menu && !menu.hidden && !menu.contains(event.target)) menu.hidden = true;
    });

    // Los avisos de éxito desaparecen solos.
    $$('.flash-success[data-flash]').forEach(flash =>
        setTimeout(() => flash.remove(), 5000)
    );

    // ==========================================
    // FILTROS: se envían al cambiar
    // ==========================================
    $$('form[data-autosubmit]').forEach(form => {
        form.addEventListener('change', event => {
            if (event.target.matches('select')) form.requestSubmit();
        });
    });

    // Fila de tabla clicable.
    $$('tr[data-href]').forEach(row => {
        row.addEventListener('click', event => {
            if (event.target.closest('a, button, input, select')) return;
            if (window.getSelection()?.toString()) return;
            location.href = row.dataset.href;
        });
    });

    // ==========================================
    // SELECTS DEPENDIENTES (compañía → sucursal, categoría → subcategoría)
    // ==========================================
    $$('select[data-dependent]').forEach(target => {
        const key = target.dataset.dependent;
        const source = $(`select[data-dependent-source="${key}"]`);

        if (!source) return;

        let selected = target.dataset.selected || '';

        const load = async () => {
            const placeholder = target.dataset.placeholder;

            if (!source.value) {
                target.innerHTML = `<option value="">${placeholder}</option>`;
                target.disabled = key === 'branches';
                return;
            }

            target.disabled = true;
            target.innerHTML = '<option value="">Cargando…</option>';

            try {
                const url = new URL(target.dataset.url, location.href);
                url.searchParams.set(target.dataset.param, source.value);

                const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const rows = await response.json();

                const first = key === 'branches'
                    ? (rows.length ? 'Selecciona…' : 'Sin sucursales')
                    : placeholder;

                target.innerHTML = `<option value="">${first}</option>`;

                rows.forEach(row => {
                    const option = new Option(row.name, row.id, false, String(row.id) === selected);
                    target.add(option);
                });

                if (key === 'branches' && rows.length === 1) target.value = rows[0].id;
            } catch (error) {
                target.innerHTML = '<option value="">No se pudo cargar</option>';
            } finally {
                target.disabled = false;
                selected = '';
            }
        };

        source.addEventListener('change', load);
        load();
    });

    // ==========================================
    // MATRIZ IMPACTO × URGENCIA
    // ==========================================
    const matrix = $('[data-matrix]');

    if (matrix) {
        const radios = $$('input[name="Priority"]');

        const highlightTarget = value => {
            $$('[data-target-for]').forEach(el => el.classList.toggle('active', el.dataset.targetFor === value));
        };

        matrix.addEventListener('click', event => {
            const cell = event.target.closest('.matrix-cell');
            if (!cell) return;

            $$('.matrix-cell', matrix).forEach(c => c.classList.toggle('selected', c === cell));

            const radio = radios.find(r => r.value === cell.dataset.priority);
            if (radio) radio.checked = true;
            highlightTarget(cell.dataset.priority);
        });

        radios.forEach(radio => radio.addEventListener('change', () => {
            $$('.matrix-cell', matrix).forEach(c => c.classList.remove('selected'));
            highlightTarget(radio.value);
        }));

        highlightTarget(radios.find(r => r.checked)?.value);
    }

    // ==========================================
    // CHAT DEL TICKET
    // ==========================================
    const thread = $('[data-chat]');
    const chatForm = $('[data-chat-form]');

    if (thread && chatForm) {
        const textarea = $('textarea', chatForm);
        const button = $('button[type="submit"]', chatForm);
        let lastId = Number(thread.dataset.lastId || 0);
        let polling = false;

        const scrollToBottom = (smooth = false) =>
            thread.scrollTo({ top: thread.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });

        const append = message => {
            if (!message || message.id <= lastId || $(`[data-message-id="${message.id}"]`, thread)) return;

            $('.chat-empty', thread)?.remove();

            const nearBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 120;
            const tpl = document.createElement('template');
            tpl.innerHTML = message.html.trim();

            const el = tpl.content.firstElementChild;
            el.classList.add('msg-new');
            thread.appendChild(el);

            lastId = Math.max(lastId, message.id);
            if (nearBottom) scrollToBottom(true);
        };

        const poll = async () => {
            if (polling || document.hidden) return;
            polling = true;

            try {
                const url = new URL(thread.dataset.pollUrl, location.href);
                url.searchParams.set('after', lastId);

                const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (response.ok) (await response.json()).messages.forEach(append);
            } catch (e) {
                // Sin conexión: se reintenta en el siguiente ciclo.
            } finally {
                polling = false;
            }
        };

        const autoresize = () => {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 180) + 'px';
        };

        textarea.addEventListener('input', autoresize);

        textarea.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                chatForm.requestSubmit();
            }
        });

        chatForm.addEventListener('submit', async event => {
            event.preventDefault();

            const text = textarea.value.trim();
            if (!text || button.disabled) return;

            button.disabled = true;

            try {
                const body = new FormData(chatForm);
                body.set(csrf.name(), csrf.value());

                const response = await fetch(chatForm.action, {
                    method: 'POST',
                    body,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const data = await response.json().catch(() => ({}));
                csrf.update(data.csrf);

                if (!response.ok) throw new Error(data.message || 'No se pudo enviar el mensaje.');

                textarea.value = '';
                autoresize();
                append(data.message);
                scrollToBottom(true);
            } catch (error) {
                alert(error.message);
            } finally {
                button.disabled = false;
                textarea.focus();
            }
        });

        scrollToBottom();
        setInterval(poll, Math.max(3, Number(thread.dataset.pollSeconds || 10)) * 1000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    }
})();
