import { Head } from '@inertiajs/react';
import CircleAlert from 'lucide-react/dist/esm/icons/circle-alert.js';
import CircleCheck from 'lucide-react/dist/esm/icons/circle-check.js';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

type CompleteProps = {
    success: boolean;
    message: string;
};

/**
 * Shown inside the popup after the bank sends the user back. It tells the window that
 * opened the popup to refresh, then closes itself.
 */
export default function BankingComplete({ success, message }: CompleteProps) {
    const [hasOpener] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.opener !== null &&
            !window.opener.closed,
    );

    useEffect(() => {
        if (hasOpener) {
            try {
                window.opener.postMessage(
                    { type: 'bank-account-linked', success },
                    window.location.origin,
                );
            } catch {
                // The opener navigated away; nothing to notify.
            }

            if (success) {
                const timer = window.setTimeout(() => window.close(), 1200);

                return () => window.clearTimeout(timer);
            }

            return;
        }

        if (success) {
            const timer = window.setTimeout(() => {
                window.location.assign('/dashboard');
            }, 1500);

            return () => window.clearTimeout(timer);
        }
    }, [hasOpener, success]);

    return (
        <>
            <Head title={success ? 'Bank connected' : 'Bank not connected'} />

            <div className="flex min-h-dvh items-center justify-center bg-slate-50 p-4 dark:bg-slate-950">
                <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-start gap-3">
                        {success ? (
                            <CircleCheck className="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        ) : (
                            <CircleAlert className="mt-0.5 size-5 shrink-0 text-destructive" />
                        )}
                        <div>
                            <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                {success
                                    ? 'Bank connected'
                                    : 'Bank not connected'}
                            </h1>
                            <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                {message}
                            </p>
                            {success ? (
                                <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    {hasOpener
                                        ? 'This window closes automatically.'
                                        : 'Taking you to your dashboard…'}
                                </p>
                            ) : null}
                        </div>
                    </div>

                    <div className="mt-5 flex gap-2">
                        {hasOpener ? (
                            <Button
                                type="button"
                                variant={success ? 'outline' : 'default'}
                                onClick={() => window.close()}
                            >
                                Close
                            </Button>
                        ) : (
                            <Button asChild>
                                <a href="/dashboard">Go to dashboard</a>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
