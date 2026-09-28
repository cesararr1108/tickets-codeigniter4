/*
 * Funciones genéricas para comunicarse con CodeIgniter.
 */

// ==========================================
// HEADERS GENERALES
// ==========================================
function getHeaders() {
    return {
        "Accept": "application/json",
        "Authorization": "Bearer C354r",
        "X-Api-Key": "C354r*11"
    };
}

function buildUrl(apiUrl, endpoint) {
    return apiUrl.replace(/\/$/, "") + endpoint;
}

// ==========================================
// RESPUESTA
// Si CodeIgniter devuelve errores de validación
// ({ messages: { campo: "..." } }) se muestran al usuario.
// ==========================================
async function handleResponse(response) {

    const body = await response.json().catch(() => null);

    if (!response.ok) {

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
// GET
// ==========================================
export async function apiGet(apiUrl, endpoint) {

    const response = await fetch(buildUrl(apiUrl, endpoint), {
        method: "GET",
        headers: getHeaders()
    });

    return handleResponse(response);
}


// ==========================================
// POST
// ==========================================
export async function apiPost(apiUrl, endpoint, body) {

    const response = await fetch(buildUrl(apiUrl, endpoint), {
        method: "POST",
        headers: {
            ...getHeaders(),
            "Content-Type": "application/json"
        },
        body: JSON.stringify(body)
    });

    return handleResponse(response);
}


// ==========================================
// UPLOAD
// ==========================================
export async function apiUpload(apiUrl, endpoint, formData) {

    const response = await fetch(buildUrl(apiUrl, endpoint), {
        method: "POST",
        headers: getHeaders(),
        body: formData
    });

    return handleResponse(response);
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
