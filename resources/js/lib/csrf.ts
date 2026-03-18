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
