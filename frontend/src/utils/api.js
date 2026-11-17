// En WAMP la raíz web apunta a frontend/, mientras que el backend está fuera
// del DocumentRoot. api.php actúa como puente local al router PHP real.
const apiUrl = new URL('../../api.php', import.meta.url);

const buildUrl = (endpoint) => {
    const url = new URL(apiUrl);
    url.searchParams.set('route', String(endpoint).replace(/^\/+/, ''));
    return url;
};

const request = async (endpoint, options = {}) => {
    let response;
    const token = (() => {
        try { return JSON.parse(localStorage.getItem('sheap-shopping.session') || 'null')?.token; }
        catch { return null; }
    })();
    const headers = new Headers(options.headers || {});
    if (token) headers.set('Authorization', `Bearer ${token}`);

    try {
        response = await fetch(buildUrl(endpoint), {
            ...options,
            headers,
            credentials: 'same-origin',
        });
    } catch (error) {
        if (error instanceof TypeError) {
            throw new Error('No se pudo comunicar con el API. Verifica que WAMP esté activo y que el servidor permita solicitudes desde el frontend.');
        }
        throw error;
    }

    const responseText = await response.text();
    let data = null;

    if (responseText) {
        try {
            data = JSON.parse(responseText);
        } catch {
            if (response.ok) {
                throw new Error('El API devolvió una respuesta que no es JSON válido.');
            }
        }
    }

    if (!response.ok) {
        throw new Error(data?.message || data?.error || `Error HTTP: ${response.status}`);
    }

    return data;
};

export const api = {
    get: (endpoint) => request(endpoint),

    upload: (endpoint, formData) => request(endpoint, {
        method: 'POST',
        body: formData,
    }),

    post: (endpoint, payload) => request(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    }),

    put: (endpoint, payload) => request(endpoint, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    }),

    del: (endpoint) => request(endpoint, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
    }),
};
