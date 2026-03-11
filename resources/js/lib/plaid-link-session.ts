type PlaidLinkSession = {
    token: string;
    walletId?: number;
};

function getStorageKey(scope: string): string {
    return `plaid-link-session:${scope}`;
}

export function readPlaidLinkSession(scope: string): PlaidLinkSession | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const storedValue = window.sessionStorage.getItem(getStorageKey(scope));

    if (!storedValue) {
        return null;
    }

    try {
        const parsedValue = JSON.parse(storedValue) as PlaidLinkSession;

        if (typeof parsedValue.token !== 'string' || parsedValue.token === '') {
            return null;
        }

        if (
            parsedValue.walletId !== undefined &&
            typeof parsedValue.walletId !== 'number'
        ) {
            return null;
        }

        return parsedValue;
    } catch {
        return null;
    }
}

export function writePlaidLinkSession(
    scope: string,
    session: PlaidLinkSession,
): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem(getStorageKey(scope), JSON.stringify(session));
}

export function clearPlaidLinkSession(scope: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.removeItem(getStorageKey(scope));
}
