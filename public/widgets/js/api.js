/*
 * Comunicación con la API pública del widget (/widget).
 *
 * IMPORTANTE: aquí NO hay claves ni secretos. El widget se identifica con un
 * token firmado que genera el SERVIDOR de la página anfitriona:
 *
 *   data-token      token ya generado (se envía en el header X-Widget-Token)
 *   data-token-url  URL del anfitrión que devuelve {"token": "..."}; se usa
 *                   para pedir uno nuevo cuando el actual expira.
 */

const auth = {
    token: "",
    tokenUrl: "",
    pending: null
};

export function configureAuth({ token = "", tokenUrl = "" } = {}) {
    auth.token = token;
    auth.tokenUrl = tokenUrl;
}

export function setToken(token) {
    auth.token = token || "";
}

// ¿Al token le quedan menos de 30 segundos (o ya expiró)?
function expiresSoon(token) {

    try {
        const payload = JSON.parse(
            atob(token.split(".")[1].replace(/-/g, "+").replace(/_/g, "/"))
        );

        return payload.exp * 1000 - Date.now() < 30000;

    } catch (error) {
        return false;
    }
}

async function fetchToken() {

    if (!auth.pending) {

        auth.pending = fetch(auth.tokenUrl, {
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error("No se pudo renovar la sesión del widget.");
                }
                return response.json();
            })
            .then(data => {
                auth.token = data.token || "";
                return auth.token;
            })
            .finally(() => {
                auth.pending = null;
            });
    }

    return auth.pending;
}

async function getToken(force = false) {

    if (auth.tokenUrl && (force || !auth.token || expiresSoon(auth.token))) {
        return fetchToken();
    }

    return auth.token;
}

function buildUrl(apiUrl, endpoint) {
    return apiUrl.replace(/\/$/, "") + endpoint;
}

// ==========================================
// PETICIÓN (con un reintento si el token expiró)
// ==========================================
async function request(apiUrl, endpoint, options = {}) {

    for (let attempt = 0; attempt < 2; attempt++) {

        const token = await getToken(attempt > 0);

        const response = await fetch(buildUrl(apiUrl, endpoint), {
            ...options,
            headers: {
                "Accept": "application/json",
                ...(options.headers || {}),
                "X-Widget-Token": token
            }
        });

        if (response.status === 401 && attempt === 0 && auth.tokenUrl) {
            continue;
        }

        return handleResponse(response);
    }
}

// ==========================================
// RESPUESTA
// Errores de validación: { messages: { campo: "..." } }
// ==========================================
async function handleResponse(response) {

    const body = await response.json().catch(() => null);

    if (!response.ok) {

        if (response.status === 401) {
            throw new Error(
                "La sesión del widget expiró. Recarga la página e inténtalo de nuevo."
            );
        }

        if (response.status === 429) {
            throw new Error(
                body?.message || "Demasiadas solicitudes. Espera unos minutos."
            );
        }

        const messages = body?.messages
            ? Object.values(body.messages).join(" ")
            : "";

        throw new Error(
            messages || body?.message || `Error HTTP ${response.status}`
        );
    }

    return body;
}

// ==========================================
// GET / POST / UPLOAD
// ==========================================
export function apiGet(apiUrl, endpoint) {
    return request(apiUrl, endpoint, { method: "GET" });
}

export function apiPost(apiUrl, endpoint, body) {
    return request(apiUrl, endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body)
    });
}

export function apiUpload(apiUrl, endpoint, formData) {
    return request(apiUrl, endpoint, { method: "POST", body: formData });
}

// ==========================================
// NORMALIZA RESPUESTAS ( [..] o { data: [..] } )
// ==========================================
export function toList(result) {

    if (Array.isArray(result)) {
        return result;
    }

    if (result && Array.isArray(result.data)) {
        return result.data;
    }

    return [];
}
