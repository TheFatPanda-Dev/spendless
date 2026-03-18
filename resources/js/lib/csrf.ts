export function getCsrfToken(): string {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    return token ?? '';
}

export function getCookieValue(name: string): string | undefined {
    const match = document.cookie.match(
        new RegExp(
            `(?:^|; )${name.replace(/[-.$?*|{}()[\]\\/+^]/g, '\\$&')}=([^;]*)`,
        ),
    );

    return match ? decodeURIComponent(match[1]) : undefined;
}

export function getCsrfData(): { csrfToken?: string; xsrfToken?: string } {
    const csrfToken = getCsrfToken() || undefined;
    const xsrfToken = getCookieValue('XSRF-TOKEN');

    return { csrfToken, xsrfToken };
}

export function buildAjaxHeaders(
    extraHeaders: Record<string, string> = {},
): Record<string, string> {
    const { csrfToken, xsrfToken } = getCsrfData();

    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        ...extraHeaders,
    };
}
