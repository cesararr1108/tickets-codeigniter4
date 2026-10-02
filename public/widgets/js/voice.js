/*
 * Dictado por voz para los campos de texto del widget.
 *
 * Usa la Web Speech API del navegador (Chrome, Edge, Safari). Si el
 * navegador no la soporta no se muestra ningún botón. Los campos se
 * detectan solos (también los de formularios que se montan después), y se
 * excluyen los de solo lectura, numéricos, correo, teléfono, fecha, etc.
 * Para excluir uno a mano: atributo data-no-voice.
 */

const SpeechRecognition =
    window.SpeechRecognition || window.webkitSpeechRecognition;

const LANG = "es-CO";
const TEXT_TYPES = new Set(["", "text"]);
const MIC = `<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v4"/></svg>`;

let active = null; // { stop() } del dictado en curso (solo uno a la vez)

function isEligible(field) {

    if (field.dataset.voice || field.dataset.noVoice !== undefined) return false;
    if (field.readOnly || field.disabled) return false;
    if (field.tagName === "TEXTAREA") return true;
    if (field.tagName !== "INPUT") return false;
    if (!TEXT_TYPES.has(field.type)) return false;

    const mode = field.getAttribute("inputmode");
    return !mode || mode === "text";
}

function attach(field) {

    field.dataset.voice = "1";

    const wrap = document.createElement("div");
    wrap.className = "tw-voice";
    field.replaceWith(wrap);
    wrap.appendChild(field);

    const button = document.createElement("button");
    button.type = "button";
    button.className = "tw-voice-btn";
    button.innerHTML = MIC;
    button.title = "Dictar con la voz";
    button.setAttribute("aria-label", "Dictar con la voz");
    button.setAttribute("aria-pressed", "false");
    wrap.appendChild(button);

    field.classList.add("tw-voice-field");

    let recognition = null;
    let base = "";
    let finalText = "";

    const write = interim => {

        const spoken = (finalText + interim).trim();
        const sep = base && spoken && !/\s$/.test(base) ? " " : "";
        let value = base + sep + spoken;

        if (field.maxLength > 0) value = value.slice(0, field.maxLength);

        field.value = value;
        field.dispatchEvent(new Event("input", { bubbles: true }));
    };

    const stop = () => recognition?.stop();

    const setListening = on => {
        button.classList.toggle("is-listening", on);
        button.setAttribute("aria-pressed", String(on));
        button.title = on ? "Detener dictado" : "Dictar con la voz";
    };

    const start = () => {

        active?.stop();

        recognition = new SpeechRecognition();
        recognition.lang = LANG;
        recognition.continuous = true;
        recognition.interimResults = true;

        base = field.value;
        finalText = "";

        recognition.onresult = event => {

            let interim = "";

            for (let i = event.resultIndex; i < event.results.length; i++) {
                const chunk = event.results[i][0].transcript;
                if (event.results[i].isFinal) finalText += chunk + " ";
                else interim += chunk;
            }

            write(interim);
        };

        recognition.onerror = event => {
            if (event.error === "not-allowed" || event.error === "service-not-allowed") {
                button.title = "Permite el acceso al micrófono para dictar";
            }
        };

        recognition.onend = () => {
            setListening(false);
            if (active?.stop === stop) active = null;
            recognition = null;
        };

        try {
            recognition.start();
            setListening(true);
            active = { stop };
        } catch {
            setListening(false);
        }
    };

    button.addEventListener("click", () => recognition ? stop() : start());
}

export function enableVoiceInput(root) {

    if (!SpeechRecognition) return;

    const scan = node => {
        if (node.nodeType !== 1) return;
        if (node.matches?.("input, textarea") && isEligible(node)) attach(node);
        node.querySelectorAll?.("input, textarea").forEach(field => {
            if (isEligible(field)) attach(field);
        });
    };

    scan(root);

    new MutationObserver(mutations => {
        mutations.forEach(m => m.addedNodes.forEach(scan));
    }).observe(root, { childList: true, subtree: true });
}
