/**
 * Small helpers for non-Inertia JSON/multipart calls (e.g. the synchronous AI
 * endpoint, in-drawer document upload/delete) that need Laravel CSRF protection.
 * Reads the XSRF-TOKEN cookie that Laravel sets and sends it as X-XSRF-TOKEN,
 * exactly like Inertia's axios does under the hood.
 */
export function xsrfToken(): string {
    const match = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

/** fetch() with same-origin credentials + CSRF + JSON Accept. Do NOT set
 * Content-Type for FormData bodies (the browser sets the multipart boundary). */
export async function apiFetch(url: string, options: RequestInit = {}): Promise<Response> {
    const isFormData = options.body instanceof FormData;
    return fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
            ...(options.headers || {}),
        },
    });
}
