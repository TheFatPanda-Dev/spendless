import { router } from '@inertiajs/react';
import { Loader2, Link2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { getCsrfToken } from '@/lib/csrf';
import {
    clearPlaidLinkSession,
    readPlaidLinkSession,
    writePlaidLinkSession,
} from '@/lib/plaid-link-session';
import { loadPlaidScript } from '@/lib/plaid-loader';

type PlaidLinkButtonProps = {
    walletId: number;
    onConnected?: () => void;
    autoStart?: boolean;
};

export default function PlaidLinkButton({
    walletId,
    onConnected,
    autoStart = false,
}: PlaidLinkButtonProps) {
    const plaidSessionScope = `wallet-${walletId}`;
    const [isConnecting, setIsConnecting] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
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
        setErrorMessage(null);

        try {
            await loadPlaidScript();

            const storedSession = redirectUri
                ? readPlaidLinkSession(plaidSessionScope)
                : null;

            let linkToken = storedSession?.token ?? null;

            if (linkToken === null) {
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

                linkToken = linkPayload.link_token;

                writePlaidLinkSession(plaidSessionScope, {
                    token: linkToken,
                });
            }

            if (!window.Plaid) {
                throw new Error('Plaid Link is not available.');
            }

            const handler = window.Plaid.create({
                token: linkToken,
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

                        clearPlaidLinkSession(plaidSessionScope);
                        router.reload({ only: ['wallet'] });
                        onConnected?.();
                    } finally {
                        setIsConnecting(false);
                    }
                },
                onExit: () => {
                    clearPlaidLinkSession(plaidSessionScope);
                    setIsConnecting(false);
                },
            });

            handler.open();
        } catch {
            clearPlaidLinkSession(plaidSessionScope);
            setErrorMessage(
                'Plaid could not load. This is often caused by an ad/privacy blocker (ERR_BLOCKED_BY_CLIENT). Please allow cdn.plaid.com and try again.',
            );
            setIsConnecting(false);
        }
    }, [onConnected, plaidSessionScope, redirectUri, walletId]);

    useEffect(() => {
        if (!autoStart || hasAutoStarted.current) {
            return;
        }

        hasAutoStarted.current = true;
        void handleConnect();
    }, [autoStart, handleConnect]);

    return (
        <div className="space-y-2">
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

            {errorMessage ? (
                <p className="text-xs text-destructive">{errorMessage}</p>
            ) : null}
        </div>
    );
}
