import X from 'lucide-react/dist/esm/icons/x.js';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

type WarningState = {
    message: string;
    resourceUrl: string | null;
};

const LOCAL_DEV_HOST_PATTERN = /(\[::1\]|127\.0\.0\.1|localhost):5173/i;

const getMessageFromReason = (reason: unknown): string => {
    if (reason instanceof Error) {
        return reason.message;
    }

    if (typeof reason === 'string') {
        return reason;
    }

    try {
        return JSON.stringify(reason);
    } catch {
        return '';
    }
};

const buildWarning = (resourceUrl: string | null): WarningState => ({
    message:
        'A browser extension appears to be blocking a local app file (ERR_BLOCKED_BY_CLIENT). Disable blocking for this site or allow localhost in your extension, then hard refresh.',
    resourceUrl,
});

export default function RuntimeLoadWarning() {
    const [warning, setWarning] = useState<WarningState | null>(null);

    useEffect(() => {
        if (!import.meta.env.DEV) {
            return;
        }

        const handleError = (event: Event): void => {
            const errorEvent = event as ErrorEvent;
            const message = [errorEvent.message, errorEvent.filename]
                .filter(Boolean)
                .join(' ');

            if (/ERR_BLOCKED_BY_CLIENT/i.test(message)) {
                setWarning(buildWarning(errorEvent.filename || null));

                return;
            }

            const target = event.target;

            if (
                target instanceof HTMLScriptElement ||
                target instanceof HTMLLinkElement ||
                target instanceof HTMLImageElement
            ) {
                let resourceUrl = '';

                if (target instanceof HTMLScriptElement) {
                    resourceUrl = target.src;
                } else if (target instanceof HTMLLinkElement) {
                    resourceUrl = target.href;
                } else {
                    resourceUrl = target.src;
                }

                if (
                    typeof resourceUrl === 'string' &&
                    LOCAL_DEV_HOST_PATTERN.test(resourceUrl)
                ) {
                    setWarning(buildWarning(resourceUrl));
                }
            }
        };

        const handleRejection = (event: PromiseRejectionEvent): void => {
            const reasonMessage = getMessageFromReason(event.reason);

            if (
                /ERR_BLOCKED_BY_CLIENT/i.test(reasonMessage) ||
                (/Failed to fetch dynamically imported module/i.test(
                    reasonMessage,
                ) && LOCAL_DEV_HOST_PATTERN.test(reasonMessage))
            ) {
                setWarning(buildWarning(null));
            }
        };

        window.addEventListener('error', handleError, true);
        window.addEventListener('unhandledrejection', handleRejection);

        return () => {
            window.removeEventListener('error', handleError, true);
            window.removeEventListener('unhandledrejection', handleRejection);
        };
    }, []);

    if (!warning) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed inset-x-0 top-0 z-60 p-3 sm:p-4">
            <div className="pointer-events-auto mx-auto w-full max-w-3xl rounded-xl border border-warning/40 bg-warning/15 px-4 py-3 text-warning-foreground shadow-lg backdrop-blur-sm">
                <div className="flex items-start gap-3">
                    <div className="min-w-0 flex-1 space-y-1">
                        <p className="text-sm font-semibold">Local resource blocked</p>
                        <p className="text-xs leading-5 text-warning-foreground/90">
                            {warning.message}
                        </p>
                        {warning.resourceUrl ? (
                            <p className="break-all text-[11px] leading-5 text-warning-foreground/75">
                                Resource: {warning.resourceUrl}
                            </p>
                        ) : null}
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Dismiss blocked resource warning"
                        className="size-8 text-warning-foreground/80 hover:bg-warning/20 hover:text-warning-foreground"
                        onClick={() => setWarning(null)}
                    >
                        <X className="size-4" aria-hidden="true" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
