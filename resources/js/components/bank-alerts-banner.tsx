import { usePage } from '@inertiajs/react';
import TriangleAlert from 'lucide-react/dist/esm/icons/triangle-alert.js';
import { useState } from 'react';
import BankLogo from '@/components/bank-logo';
import { Button } from '@/components/ui/button';
import { openBankPopup } from '@/lib/bank-popup';
import { formatDisplayDate } from '@/lib/date-filters';
import type { BankConnectionStatus } from '@/types';

/**
 * Banks whose access expired or ends within a week, with a Reconnect button.
 * The list comes from the shared `bankAlerts` prop.
 */
export default function BankAlertsBanner() {
    const page = usePage<{
        bankAlerts?: BankConnectionStatus[];
        number_locale?: string;
    }>();
    const alerts = page.props.bankAlerts ?? [];
    const locale = page.props.number_locale ?? 'en-GB';
    const [popupBlocked, setPopupBlocked] = useState(false);

    if (alerts.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            {alerts.map((alert) => {
                const name = alert.institution_name ?? 'Your bank';
                const expired = alert.status === 'needs_reauth';

                return (
                    <div
                        key={alert.id}
                        className="flex flex-col gap-3 rounded-xl border border-amber-300/70 bg-amber-50 px-3 py-2.5 text-sm text-amber-900 sm:flex-row sm:items-center dark:border-amber-700/50 dark:bg-amber-950/30 dark:text-amber-200"
                    >
                        <div className="flex min-w-0 flex-1 items-center gap-3">
                            <TriangleAlert className="size-4 shrink-0" />
                            <BankLogo
                                src={alert.institution_logo}
                                name={name}
                                className="size-7"
                            />
                            <p className="min-w-0">
                                {expired ? (
                                    <>
                                        <span className="font-semibold">
                                            {name}
                                        </span>{' '}
                                        is no longer syncing: bank access
                                        expired or was revoked.
                                    </>
                                ) : (
                                    <>
                                        Access to{' '}
                                        <span className="font-semibold">
                                            {name}
                                        </span>{' '}
                                        ends on{' '}
                                        {alert.consent_valid_until
                                            ? formatDisplayDate(
                                                  alert.consent_valid_until.slice(
                                                      0,
                                                      10,
                                                  ),
                                                  locale,
                                              )
                                            : 'soon'}
                                        . Reconnect to keep syncing.
                                    </>
                                )}
                            </p>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            className="shrink-0"
                            onClick={() =>
                                setPopupBlocked(!openBankPopup(alert.id))
                            }
                        >
                            Reconnect
                        </Button>
                    </div>
                );
            })}
            {popupBlocked ? (
                <p className="text-xs text-destructive">
                    Popup was blocked. Please allow popups for this site and try
                    again.
                </p>
            ) : null}
        </div>
    );
}
