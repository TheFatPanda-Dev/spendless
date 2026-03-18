export type PlaidHandler = {
    open: () => void;
    destroy: () => void;
};

export type PlaidCreateConfig = {
    token: string;
    receivedRedirectUri?: string;
    onSuccess: (publicToken: string) => void;
    onExit: () => void;
};

declare global {
    interface Window {
        Plaid?: {
            create: (config: PlaidCreateConfig) => PlaidHandler;
        };
    }
}

const PLAID_SCRIPT_URL = 'https://cdn.plaid.com/link/v2/stable/link-initialize.js';

export async function loadPlaidScript(): Promise<void> {
    if (window.Plaid) {
        return;
    }

    await new Promise<void>((resolve, reject) => {
        const existingScript = document.querySelector(
            `script[src="${PLAID_SCRIPT_URL}"]`,
        );

        if (existingScript) {
            existingScript.addEventListener('load', () => resolve(), {
                once: true,
            });
            existingScript.addEventListener(
                'error',
                () => reject(new Error('Plaid Link failed to load.')),
                { once: true },
            );

            return;
        }

        const script = document.createElement('script');

        script.src = PLAID_SCRIPT_URL;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Plaid Link failed to load.'));

        document.head.appendChild(script);
    });
}
