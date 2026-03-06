import { router } from '@inertiajs/react';
import { Loader2, Link2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

type PlaidLinkButtonProps = {
    walletId: number;
    onConnected?: () => void;
    autoStart?: boolean;
};

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

export default function PlaidLinkButton({
    walletId,
    onConnected,
    autoStart = false,
}: PlaidLinkButtonProps) {
    const [isConnecting, setIsConnecting] = useState(false);
    const hasAutoStarted = useRef(false);

    const redirectUri = useMemo(() => {
        if (typeof window === 'undefined') {
            return null;
        }

        const url = new URL(window.location.href);

        return url.searchParams.has('oauth_state_id')
            ? window.location.href
            : null;
    }, []);

    const handleConnect = useCallback(async () => {
        setIsConnecting(true);

        try {
            await loadPlaidScript();

            const linkTokenResponse = await fetch(
                `/wallets/${walletId}/bank-connections/plaid/link-token`,
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
                throw new Error('Unable to initialize Plaid Link.');
            }

            const linkPayload = (await linkTokenResponse.json()) as {
                link_token: string;
            };

            if (!window.Plaid) {
                throw new Error('Plaid Link is not available.');
            }

            const handler = window.Plaid.create({
                token: linkPayload.link_token,
                ...(redirectUri ? { receivedRedirectUri: redirectUri } : {}),
                onSuccess: async (publicToken: string) => {
                    try {
                        const exchangeResponse = await fetch(
                            `/wallets/${walletId}/bank-connections/plaid/exchange`,
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
                                'Failed to complete Plaid connection.',
                            );
                        }

                        router.reload({ only: ['wallet'] });
                        onConnected?.();
                    } finally {
                        setIsConnecting(false);
                    }
                },
                onExit: () => {
                    setIsConnecting(false);
                },
            });

            handler.open();
        } catch {
            setIsConnecting(false);
        }
    }, [onConnected, redirectUri, walletId]);

    useEffect(() => {
        if (!autoStart || hasAutoStarted.current) {
            return;
        }

        hasAutoStarted.current = true;
        void handleConnect();
    }, [autoStart, handleConnect]);

    return (
        <Button onClick={handleConnect} disabled={isConnecting}>
            {isConnecting ? (
                <>
                    <Loader2 className="size-4 animate-spin" />
                    Connecting...
                </>
            ) : (
                <>
                    <Link2 className="size-4" />
                    Bank Connection
                </>
            )}
        </Button>
    );
}
