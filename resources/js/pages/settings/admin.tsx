import { Deferred, Head, router, usePage } from '@inertiajs/react';
import CircleAlert from 'lucide-react/dist/esm/icons/circle-alert.js';
import CircleCheck from 'lucide-react/dist/esm/icons/circle-check.js';
import Loader2 from 'lucide-react/dist/esm/icons/loader-2.js';
import TriangleAlert from 'lucide-react/dist/esm/icons/triangle-alert.js';
import { useState } from 'react';
import BankLogo from '@/components/bank-logo';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { bankStatusLabel, bankStatusVariant } from '@/lib/bank-status';
import { formatDisplayDate, formatLocalizedDateTime } from '@/lib/date-filters';
import type { BankConnectionStatus, BreadcrumbItem } from '@/types';

type Check = {
    key: string;
    label: string;
    status: 'ok' | 'warn' | 'fail';
    detail: string;
};

type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    email_verified: boolean;
    two_factor: boolean;
    wallets_count: number;
    banks_count: number;
    created_at: string | null;
    is_you: boolean;
};

type AdminConnection = BankConnectionStatus & {
    owner: string | null;
    accounts_count: number;
};

type FailedJob = {
    id: number;
    job: string;
    error: string;
    failed_at: string;
};

type Props = {
    checks: Check[];
    bankChecks?: Check[];
    registrationOpen: boolean;
    syncSchedule: string;
    users: AdminUser[];
    connections: AdminConnection[];
    failedJobs: FailedJob[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin',
        href: '/settings/admin',
    },
];

function CheckIcon({ status }: { status: Check['status'] }) {
    if (status === 'ok') {
        return (
            <CircleCheck className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
        );
    }

    if (status === 'warn') {
        return (
            <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
        );
    }

    return <CircleAlert className="mt-0.5 size-4 shrink-0 text-destructive" />;
}

function CheckList({ checks }: { checks: Check[] }) {
    return (
        <ul className="divide-y divide-border rounded-lg border border-border bg-card/70">
            {checks.map((check) => (
                <li key={check.key} className="flex gap-3 px-3 py-2.5">
                    <CheckIcon status={check.status} />
                    <div className="min-w-0">
                        <p className="text-sm font-medium text-foreground">
                            {check.label}
                        </p>
                        <p className="text-xs break-words text-muted-foreground">
                            {check.detail}
                        </p>
                    </div>
                </li>
            ))}
        </ul>
    );
}

export default function Admin({
    checks,
    bankChecks,
    registrationOpen,
    syncSchedule,
    users,
    connections,
    failedJobs,
}: Props) {
    const page = usePage<{ number_locale?: string }>();
    const locale = page.props.number_locale ?? 'en-GB';
    const [busy, setBusy] = useState<string | null>(null);

    const run = (key: string, action: (onFinish: () => void) => void): void => {
        if (busy !== null) {
            return;
        }

        setBusy(key);
        action(() => setBusy(null));
    };

    const failing = [...checks, ...(bankChecks ?? [])].filter(
        (check) => check.status === 'fail',
    ).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin" />

            <SettingsLayout>
                <div className="space-y-8">
                    <section className="space-y-3">
                        <Heading
                            variant="small"
                            title="Setup check"
                            description={
                                failing > 0
                                    ? `${failing} problem${failing === 1 ? '' : 's'} to fix. Red items stop banks from syncing.`
                                    : 'Everything SpendLess needs on this server.'
                            }
                        />
                        <CheckList checks={checks} />

                        <Deferred
                            data="bankChecks"
                            fallback={
                                <div className="flex items-center gap-2 rounded-lg border border-border bg-card/70 px-3 py-3 text-sm text-muted-foreground">
                                    <Loader2 className="size-4 animate-spin" />
                                    Checking Enable Banking…
                                </div>
                            }
                        >
                            <CheckList checks={bankChecks ?? []} />
                        </Deferred>
                    </section>

                    <section className="space-y-3">
                        <Heading
                            variant="small"
                            title="Sign-ups"
                            description="Whether new people can create an account (with email or Google/GitHub)."
                        />
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card/70 px-3 py-3">
                            <p className="text-sm">
                                New sign-ups are{' '}
                                <span className="font-semibold">
                                    {registrationOpen ? 'open' : 'closed'}
                                </span>
                                .
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy !== null}
                                onClick={() =>
                                    run('registration', (onFinish) =>
                                        router.patch(
                                            '/settings/admin/registration',
                                            { open: !registrationOpen },
                                            { preserveScroll: true, onFinish },
                                        ),
                                    )
                                }
                            >
                                {registrationOpen
                                    ? 'Close sign-ups'
                                    : 'Open sign-ups'}
                            </Button>
                        </div>
                    </section>

                    <section className="space-y-3">
                        <Heading
                            variant="small"
                            title="Users"
                            description={`${users.length} account${users.length === 1 ? '' : 's'}`}
                        />
                        <div className="space-y-2">
                            {users.map((user) => (
                                <div
                                    key={user.id}
                                    className="flex flex-col gap-3 rounded-lg border border-border bg-card/70 p-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0">
                                        <p className="flex flex-wrap items-center gap-2 text-sm font-semibold">
                                            {user.name}
                                            {user.is_admin ? (
                                                <Badge>Admin</Badge>
                                            ) : null}
                                            {user.is_you ? (
                                                <Badge variant="outline">
                                                    You
                                                </Badge>
                                            ) : null}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {user.email}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Joined{' '}
                                            {user.created_at
                                                ? formatDisplayDate(
                                                      user.created_at.slice(
                                                          0,
                                                          10,
                                                      ),
                                                      locale,
                                                  )
                                                : '–'}
                                            {' • '}
                                            {user.email_verified
                                                ? 'Email verified'
                                                : 'Email not verified'}
                                            {' • '}
                                            {user.two_factor
                                                ? '2FA on'
                                                : '2FA off'}
                                            {' • '}
                                            {user.banks_count} bank
                                            {user.banks_count === 1
                                                ? ''
                                                : 's'}, {user.wallets_count}{' '}
                                            wallet
                                            {user.wallets_count === 1
                                                ? ''
                                                : 's'}
                                        </p>
                                    </div>
                                    {!user.is_you ? (
                                        <div className="flex shrink-0 gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={busy !== null}
                                                onClick={() =>
                                                    run(
                                                        `admin-${user.id}`,
                                                        (onFinish) =>
                                                            router.patch(
                                                                `/settings/admin/users/${user.id}`,
                                                                {
                                                                    is_admin:
                                                                        !user.is_admin,
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                    onFinish,
                                                                },
                                                            ),
                                                    )
                                                }
                                            >
                                                {user.is_admin
                                                    ? 'Remove admin'
                                                    : 'Make admin'}
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="destructive"
                                                disabled={busy !== null}
                                                onClick={() => {
                                                    if (
                                                        !window.confirm(
                                                            `Delete ${user.email} and all their wallets, banks and transactions? This cannot be undone.`,
                                                        )
                                                    ) {
                                                        return;
                                                    }

                                                    run(
                                                        `delete-${user.id}`,
                                                        (onFinish) =>
                                                            router.delete(
                                                                `/settings/admin/users/${user.id}`,
                                                                {
                                                                    preserveScroll: true,
                                                                    onFinish,
                                                                },
                                                            ),
                                                    );
                                                }}
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="space-y-3">
                        <Heading
                            variant="small"
                            title="Bank connections"
                            description={`All users' banks. Background syncs run at ${syncSchedule}; "Sync now" runs immediately.`}
                        />
                        {connections.length === 0 ? (
                            <p className="rounded-lg border border-dashed border-border px-3 py-6 text-center text-sm text-muted-foreground">
                                No banks connected yet.
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {connections.map((connection) => (
                                    <div
                                        key={connection.id}
                                        className="rounded-lg border border-border bg-card/70 p-3"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <BankLogo
                                                src={
                                                    connection.institution_logo
                                                }
                                                name={
                                                    connection.institution_name ??
                                                    'Bank'
                                                }
                                                className="size-7"
                                            />
                                            <span className="text-sm font-semibold">
                                                {connection.institution_name}
                                            </span>
                                            <Badge
                                                variant={bankStatusVariant(
                                                    connection.status,
                                                )}
                                            >
                                                {bankStatusLabel(
                                                    connection.status,
                                                )}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {connection.owner} •{' '}
                                            {connection.accounts_count} account
                                            {connection.accounts_count === 1
                                                ? ''
                                                : 's'}{' '}
                                            • Last sync{' '}
                                            {formatLocalizedDateTime(
                                                connection.last_synced_at,
                                                locale,
                                            )}
                                            {connection.next_sync_at
                                                ? ` • Next ${formatLocalizedDateTime(connection.next_sync_at, locale)}`
                                                : ''}
                                            {connection.consent_valid_until
                                                ? ` • Access until ${formatDisplayDate(connection.consent_valid_until.slice(0, 10), locale)}`
                                                : ''}
                                        </p>
                                        {connection.error_message ? (
                                            <p className="mt-1 text-xs text-destructive">
                                                {connection.error_message}
                                            </p>
                                        ) : null}
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    <section className="space-y-3">
                        <Heading
                            variant="small"
                            title="Failed background jobs"
                            description="Syncs that crashed. Bank errors are retried automatically at the next slot."
                        />
                        {failedJobs.length === 0 ? (
                            <p className="rounded-lg border border-dashed border-border px-3 py-6 text-center text-sm text-muted-foreground">
                                None.
                            </p>
                        ) : (
                            <>
                                <ul className="space-y-2">
                                    {failedJobs.map((job) => (
                                        <li
                                            key={job.id}
                                            className="rounded-lg border border-border bg-card/70 p-3 text-xs"
                                        >
                                            <p className="font-medium text-foreground">
                                                {job.job} ·{' '}
                                                {formatLocalizedDateTime(
                                                    job.failed_at,
                                                    locale,
                                                )}
                                            </p>
                                            <p className="mt-1 break-words text-muted-foreground">
                                                {job.error}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={busy !== null}
                                        onClick={() =>
                                            run('retry', (onFinish) =>
                                                router.post(
                                                    '/settings/admin/failed-jobs/retry',
                                                    {},
                                                    {
                                                        preserveScroll: true,
                                                        onFinish,
                                                    },
                                                ),
                                            )
                                        }
                                    >
                                        Retry all
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={busy !== null}
                                        onClick={() =>
                                            run('clear', (onFinish) =>
                                                router.delete(
                                                    '/settings/admin/failed-jobs',
                                                    {
                                                        preserveScroll: true,
                                                        onFinish,
                                                    },
                                                ),
                                            )
                                        }
                                    >
                                        Clear
                                    </Button>
                                </div>
                            </>
                        )}
                    </section>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
