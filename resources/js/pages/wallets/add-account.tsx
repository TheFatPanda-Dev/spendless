import { Head, router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

type PlaidHandler = {
    open: () => void;
    destroy: () => void;
};

type PlaidCreateConfig = {
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

function getCsrfToken(): string {
    const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    return token ?? '';
}

async function loadPlaidScript(): Promise<void> {
    if (window.Plaid) {
        return;
    }

    await new Promise<void>((resolve, reject) => {
        const existingScript = document.querySelector(
            'script[src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"]',
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

        script.src = 'https://cdn.plaid.com/link/v2/stable/link-initialize.js';
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Plaid Link failed to load.'));

        document.head.appendChild(script);
    });
}

export default function AddAccountPopup() {
    const [isConnecting, setIsConnecting] = useState(false);
    const [isComplete, setIsComplete] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const isConnectingRef = useRef(false);
    const hasAutoStartedRef = useRef(false);

    const redirectUri = useMemo(() => {
        if (typeof window === 'undefined') {
            return null;
        }

        const url = new URL(window.location.href);

        return url.searchParams.has('oauth_state_id')
            ? window.location.href
            : null;
    }, []);

    const notifyParentAndClose = useCallback((): void => {
        if (window.opener && !window.opener.closed) {
            window.opener.postMessage(
                { type: 'plaid-account-linked' },
                window.location.origin,
            );

            window.setTimeout(() => {
                window.close();
            }, 500);

            return;
        }

        router.visit('/dashboard');
    }, []);

    const startConnection = useCallback(async (): Promise<void> => {
        if (isConnectingRef.current) {
            return;
        }

        isConnectingRef.current = true;
        setErrorMessage(null);
        setIsConnecting(true);

        try {
            await loadPlaidScript();

            const walletResponse = await fetch('/wallets', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    name: `Bank Wallet ${new Date().toLocaleDateString()}`,
                    type: 'bank',
                    currency: 'EUR',
                }),
            });

            if (!walletResponse.ok) {
                throw new Error('Unable to create wallet before Plaid connect.');
            }

            const walletPayload = (await walletResponse.json()) as { id: number };

            const linkTokenResponse = await fetch(
                `/wallets/${walletPayload.id}/bank-connections/plaid/link-token`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        redirect_uri: redirectUri,
                    }),
                },
            );

            if (!linkTokenResponse.ok) {
                throw new Error('Unable to initialize Plaid Link token.');
            }

            const linkPayload = (await linkTokenResponse.json()) as {
                link_token: string;
            };

            if (!window.Plaid) {
                throw new Error('Plaid Link is not available in this browser.');
            }

            const handler = window.Plaid.create({
                token: linkPayload.link_token,
                ...(redirectUri ? { receivedRedirectUri: redirectUri } : {}),
                onSuccess: async (publicToken: string) => {
                    try {
                        const exchangeResponse = await fetch(
                            `/wallets/${walletPayload.id}/bank-connections/plaid/exchange`,
                            {
                                method: 'POST',
                                headers: {
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': getCsrfToken(),
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({
                                    public_token: publicToken,
                                }),
                            },
                        );

                        if (!exchangeResponse.ok) {
                            throw new Error(
                                'Plaid linked but account exchange failed.',
                            );
                        }

                        setIsComplete(true);
                        isConnectingRef.current = false;
                        setIsConnecting(false);
                        notifyParentAndClose();
                    } catch {
                        setErrorMessage(
                            'Bank connected but importing the account failed. Please try again.',
                        );
                        isConnectingRef.current = false;
                        setIsConnecting(false);
                    }
                },
                onExit: () => {
                    isConnectingRef.current = false;
                    setIsConnecting(false);
                },
            });

            handler.open();
        } catch {
            setErrorMessage(
                'Could not start Plaid connection. Please verify Plaid credentials and try again.',
            );
            isConnectingRef.current = false;
            setIsConnecting(false);
        }
    }, [notifyParentAndClose, redirectUri]);

    useEffect(() => {
        if (hasAutoStartedRef.current) {
            return;
        }

        hasAutoStartedRef.current = true;
        void startConnection();
    }, [startConnection]);

    return (
        <>
            <Head title="Connect bank account" />

            <div className="flex min-h-dvh items-center justify-center bg-slate-50 p-4 dark:bg-slate-950">
                <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Connect your bank account
                    </h1>

                    <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        Plaid will open in this popup. Complete the secure flow to
                        import your accounts.
                    </p>

                    {errorMessage ? (
                        <p className="mt-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            {errorMessage}
                        </p>
                    ) : null}

                    {isComplete ? (
                        <p className="mt-4 rounded-md border border-emerald-300/40 bg-emerald-100/60 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-600/50 dark:bg-emerald-950/40 dark:text-emerald-300">
                            Account connected. This window will close automatically.
                        </p>
                    ) : null}

                    <div className="mt-5 flex gap-2">
                        <Button
                            type="button"
                            onClick={() => void startConnection()}
                            disabled={isConnecting}
                        >
                            {isConnecting ? (
                                <>
                                    <Loader2 className="size-4 animate-spin" />
                                    Opening Plaid...
                                </>
                            ) : (
                                'Try again'
                            )}
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.close()}
                        >
                            Close
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
