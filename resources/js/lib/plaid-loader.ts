export type PlaidLinkMetadata = {
    institution?: { name?: string; institution_id?: string } | null;
};

export type PlaidLinkError = {
    error_code?: string;
    error_message?: string;
    display_message?: string | null;
} | null;

export type PlaidHandler = {
    open: () => void;
    exit: (options?: { force?: boolean }) => void;
    destroy: () => void;
};

export type PlaidCreateConfig = {
    token: string;
    receivedRedirectUri?: string;
    onSuccess: (publicToken: string, metadata: PlaidLinkMetadata) => void;
    onExit: (error: PlaidLinkError, metadata: PlaidLinkMetadata) => void;
};

declare global {
    interface Window {
        Plaid?: {
            create: (config: PlaidCreateConfig) => PlaidHandler;
        };
    }
}

const PLAID_SCRIPT_URL =
    'https://cdn.plaid.com/link/v2/stable/link-initialize.js';

/**
 * Loads Plaid Link once. Plaid requires loading it from their CDN (not bundled).
 */
export async function loadPlaidScript(): Promise<void> {
    if (window.Plaid) {
        return;
    }

    await new Promise<void>((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(
            `script[src="${PLAID_SCRIPT_URL}"]`,
        );
        const script = existing ?? document.createElement('script');

        script.addEventListener('load', () => resolve(), { once: true });
        script.addEventListener(
            'error',
            () => reject(new Error('Plaid Link failed to load.')),
            { once: true },
        );

        if (!existing) {
            script.src = PLAID_SCRIPT_URL;
            script.async = true;
            document.head.appendChild(script);
        }
    });
}
