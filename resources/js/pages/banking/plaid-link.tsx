import { Head, router } from '@inertiajs/react';
import Loader2 from 'lucide-react/dist/esm/icons/loader-2.js';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { loadPlaidScript } from '@/lib/plaid-loader';
import type { PlaidHandler } from '@/lib/plaid-loader';

type Props = {
    linkToken: string | null;
    isOAuthReturn: boolean;
    mode: 'connect' | 'reconnect';
    institutionName: string | null;
    error: string | null;
};

type Phase = 'loading' | 'open' | 'saving' | 'exited' | 'error';

/**
 * Runs Plaid Link inside the bank popup. UK banks send the user to their own login
 * (OAuth) and back to /banking/plaid/oauth, which renders this page again to finish.
 */
export default function PlaidLink({
    linkToken,
    isOAuthReturn,
    mode,
    institutionName,
    error,
}: Props) {
    const [phase, setPhase] = useState<Phase>(error ? 'error' : 'loading');
    const [message, setMessage] = useState<string | null>(error);
    const handlerRef = useRef<PlaidHandler | null>(null);

    const openLink = useCallback(async (): Promise<void> => {
        if (linkToken === null) {
            return;
        }

        try {
            await loadPlaidScript();
        } catch {
            setPhase('error');
            setMessage(
                'Plaid could not load. An ad or privacy blocker often causes this: allow cdn.plaid.com and try again.',
            );

            return;
        }

        if (!window.Plaid) {
            setPhase('error');
            setMessage('Plaid is not available in this browser.');

            return;
        }

        handlerRef.current?.destroy();
        handlerRef.current = window.Plaid.create({
            token: linkToken,
            ...(isOAuthReturn
                ? { receivedRedirectUri: window.location.href }
                : {}),
            onSuccess: (publicToken, metadata) => {
                setPhase('saving');
                router.post('/banking/plaid/exchange', {
                    public_token: publicToken,
                    institution_id:
                        metadata.institution?.institution_id ?? null,
                    institution_name: metadata.institution?.name ?? null,
                });
            },
            onExit: (exitError) => {
                setPhase('exited');
                setMessage(
                    exitError?.display_message ??
                        exitError?.error_message ??
                        null,
                );
            },
        });
        handlerRef.current.open();
        setPhase('open');
    }, [isOAuthReturn, linkToken]);

    const retry = (): void => {
        setPhase('loading');
        setMessage(null);
        void openLink();
    };

    // Open Link once the page has rendered (a timer, so a remount can't open it twice).
    useEffect(() => {
        if (linkToken === null) {
            return;
        }

        const timer = window.setTimeout(() => void openLink(), 0);

        return () => window.clearTimeout(timer);
    }, [linkToken, openLink]);

    const title =
        mode === 'reconnect'
            ? `Reconnect ${institutionName ?? 'your bank'}`
            : 'Connect a UK bank';

    return (
        <>
            <Head title={title} />

            <div className="flex min-h-dvh items-center justify-center bg-slate-50 p-4 dark:bg-slate-950">
                <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {title}
                    </h1>
                    <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        {mode === 'reconnect'
                            ? 'Log in to your bank again through Plaid. Your transactions, categories and tags are kept.'
                            : 'Plaid connects UK banks such as Revolut, Barclays, Nationwide and Santander. You log in at your bank and approve read-only access; SpendLess never sees your bank password.'}
                    </p>

                    {phase === 'loading' || phase === 'saving' ? (
                        <p className="mt-5 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <Loader2 className="size-4 animate-spin" />
                            {phase === 'saving'
                                ? 'Saving the connection…'
                                : isOAuthReturn
                                  ? 'Finishing the bank login…'
                                  : 'Opening Plaid…'}
                        </p>
                    ) : null}

                    {phase === 'open' ? (
                        <p className="mt-5 text-sm text-slate-600 dark:text-slate-300">
                            Continue in the Plaid window.
                        </p>
                    ) : null}

                    {message ? (
                        <p
                            className={`mt-4 rounded-md border px-3 py-2 text-sm ${
                                phase === 'error'
                                    ? 'border-destructive/30 bg-destructive/10 text-destructive'
                                    : 'border-slate-200 text-slate-600 dark:border-slate-800 dark:text-slate-300'
                            }`}
                        >
                            {message}
                        </p>
                    ) : null}

                    {phase === 'exited' || phase === 'error' ? (
                        <div className="mt-5 flex justify-end gap-2">
                            <Button
                                variant="outline"
                                onClick={() => window.close()}
                            >
                                Close
                            </Button>
                            {linkToken !== null && !isOAuthReturn ? (
                                <Button onClick={retry}>Try again</Button>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </div>
        </>
    );
}
