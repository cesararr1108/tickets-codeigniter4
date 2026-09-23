console.log("apisss")
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


// ==========================================
// GET
// ==========================================
export async function apiGet(apiUrl, endpoint) {

    const response = await fetch(
        apiUrl.replace(/\/$/, "") + endpoint,
        {
            method: "GET",
            headers: getHeaders()
        }
    );

    if (!response.ok) {
        throw new Error(
            `Error HTTP ${response.status}`
        );
    }

    return await response.json();
}


// ==========================================
// POST
// ==========================================
export async function apiPost(apiUrl, endpoint, body) {

    const response = await fetch(
        apiUrl.replace(/\/$/, "") + endpoint,
        {
            method: "POST",
            headers: {
                ...getHeaders(),
                "Content-Type": "application/json"
            },
            body: JSON.stringify(body)
        }
    );

    if (!response.ok) {
        throw new Error(
            `Error HTTP ${response.status}`
        );
    }

    return await response.json();
}


// ==========================================
// UPLOAD
// ==========================================
export async function apiUpload(apiUrl, endpoint, formData) {

    const response = await fetch(
        apiUrl.replace(/\/$/, "") + endpoint,
        {
            method: "POST",
            headers: getHeaders(),
            body: formData
        }
    );

    if (!response.ok) {
        throw new Error(
            `Error HTTP ${response.status}`
        );
    }

    return await response.json();
}

