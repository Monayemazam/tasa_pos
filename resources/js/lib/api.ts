/**
 * Tasa POS - Native Fetch API Client with CSRF Handling
 */

function getXsrfToken(): string | null {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]*)'));
    return match ? decodeURIComponent(match[2]) : null;
}

export async function apiRequest<T = any>(url: string, options: RequestInit = {}): Promise<T> {
    const token = getXsrfToken();
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...((options.headers as Record<string, string>) || {}),
    };

    if (token) {
        headers['X-XSRF-TOKEN'] = token;
    }

    if (!(options.body instanceof FormData) && options.body && typeof options.body === 'object' && !(options.body instanceof Blob)) {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
    }

    const response = await fetch(url, {
        ...options,
        headers,
    });

    if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        const message = errorData.message || `Request failed with status ${response.status}`;
        const error: any = new Error(message);
        error.response = { data: errorData, status: response.status };
        throw error;
    }

    return response.json();
}

export const api = {
    get: <T = any>(url: string) => apiRequest<T>(url, { method: 'GET' }),
    post: <T = any>(url: string, body?: any, headers?: Record<string, string>) =>
        apiRequest<T>(url, { method: 'POST', body, headers }),
    put: <T = any>(url: string, body?: any) => apiRequest<T>(url, { method: 'PUT', body }),
    patch: <T = any>(url: string, body?: any) => apiRequest<T>(url, { method: 'PATCH', body }),
    delete: <T = any>(url: string) => apiRequest<T>(url, { method: 'DELETE' }),
};
