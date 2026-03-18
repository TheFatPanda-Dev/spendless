import { Link } from '@inertiajs/react';
import ShieldCheck from 'lucide-react/dist/esm/icons/shield-check.js';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    hasStoredCookieConsent,
    writeCookieConsent,
} from '@/lib/site-prefs';
import type { ConsentChoice } from '@/lib/site-prefs';

export default function SitePrefsBanner() {
    const [isVisible, setIsVisible] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return !hasStoredCookieConsent();
    });

    const saveConsent = (choice: ConsentChoice): void => {
        writeCookieConsent(choice);
        setIsVisible(false);
    };

    if (!isVisible) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed inset-x-0 bottom-0 z-50 p-4 sm:p-6">
            <div className="pointer-events-auto mx-auto w-full max-w-3xl rounded-2xl border border-border/60 bg-background/95 p-4 shadow-xl ring-1 ring-black/5 backdrop-blur-sm">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-2">
                        <p className="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <ShieldCheck className="size-4" aria-hidden="true" />
                            Cookie consent
                        </p>
                        <p className="max-w-2xl text-sm leading-6 text-foreground">
                            We use essential cookies to keep SpendLess secure
                            and working properly. With your consent, we can
                            also enable optional cookies for analytics.
                        </p>
                        <p className="text-xs text-muted-foreground">
                            You can update your preference at any time in our{' '}
                            <Link
                                href="/privacy"
                                className="font-medium text-foreground underline underline-offset-4"
                            >
                                Privacy Policy
                            </Link>
                            .
                        </p>
                    </div>

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => saveConsent('essential')}
                        >
                            Essential only
                        </Button>
                        <Button
                            type="button"
                            onClick={() => saveConsent('accepted')}
                        >
                            Accept all
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
