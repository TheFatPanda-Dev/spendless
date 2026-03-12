import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Building2,
    CalendarDays,
    Loader2,
    RefreshCw,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import AddNewWalletMenu from '@/components/add-new-wallet-menu';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePlaidAccountLinkedRefresh } from '@/hooks/use-plaid-account-linked-refresh';
import { useSyncAllConnections } from '@/hooks/use-sync-all-connections';
import AppLayout from '@/layouts/app-layout';
import {
    buildDashboardHref,
    formatDisplayDate,
    shiftMonthRange,
} from '@/lib/date-filters';
import { openCenteredPopup } from '@/lib/popup';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type DashboardAccount = {
    id: number;
    display_name: string | null;
    name: string | null;
    official_name: string | null;
    institution_name: string | null;
    mask: string | null;
    type: string | null;
    subtype: string | null;
    currency: string;
    current_balance: number;
    available_balance: number | null;
    last_synced_at: string | null;
};

type DashboardSummary = {
    total_balance: number;
    period_change: number;
    period_expenses: number;
    period_income: number;
};

type DashboardFilters = {
    start_date: string;
    end_date: string;
};

type Props = {
    accounts: DashboardAccount[];
    summary: DashboardSummary;
    filters: DashboardFilters;
};

const dashboardShellClasses =
    'relative flex h-full flex-1 flex-col gap-6 overflow-x-hidden rounded-[28px] border border-brand/25 bg-linear-to-br from-brand/12 via-card to-card p-3 shadow-[0_32px_90px_-54px_rgba(16,185,129,0.42)] sm:p-5 lg:p-7 dark:border-brand/20 dark:from-brand/12 dark:via-card dark:to-card dark:shadow-none';

const dashboardPanelClasses =
    'rounded-2xl border border-brand/15 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(244,250,247,0.98))] shadow-[0_14px_36px_-24px_rgba(15,23,42,0.18)] dark:border-brand/15 dark:bg-linear-to-br dark:from-brand/6 dark:via-card dark:to-card dark:shadow-[0_16px_36px_-24px_rgba(2,6,23,0.85)]';

const dashboardControlClasses =
    'border-brand/20 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(247,251,249,0.98))] text-foreground shadow-sm hover:bg-brand/6 dark:border-brand/15 dark:bg-linear-to-br dark:from-brand/6 dark:via-card dark:to-card dark:text-foreground dark:hover:bg-card';

function formatCurrency(value: number, currency: string): string {
    const sign = value > 0 ? '+' : '';

    return `${sign}${value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} ${currency}`;
}

function applyDateFilter(startDate: string, endDate: string): void {
    router.get(
        dashboard(),
        {
            start_date: startDate,
            end_date: endDate,
        },
        {
            preserveState: false,
            preserveScroll: true,
            replace: true,
        },
    );
}

export default function Dashboard({ accounts, summary, filters }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: buildDashboardHref(dashboard()),
        },
    ];

    const [connectError, setConnectError] = useState<string | null>(null);
    const [showPendingNewAccount, setShowPendingNewAccount] = useState(false);
    const previousMonthRange = shiftMonthRange(filters.start_date, -1);
    const nextMonthRange = shiftMonthRange(filters.start_date, 1);

    const { isRefreshing } = usePlaidAccountLinkedRefresh({
        only: ['accounts', 'summary'],
        onLinked: () => {
            setShowPendingNewAccount(true);
        },
        onRefreshFinished: () => {
            setShowPendingNewAccount(false);
        },
    });

    const {
        isSyncing,
        successMessage: syncSuccessMessage,
        errorMessage: syncErrorMessage,
        startSyncAll,
        clearSuccessMessage,
        clearErrorMessage,
    } = useSyncAllConnections({ only: ['accounts', 'summary'] });

    const handleConnectBank = useCallback((): void => {
        setConnectError(null);

        const popup = openCenteredPopup(
            '/wallets/add-account',
            'plaid-add-account',
            {
                width: 520,
                height: 760,
            },
        );

        if (!popup) {
            setConnectError(
                'Popup was blocked. Please allow popups for this site and try again.',
            );

            return;
        }

        popup.focus();
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className={dashboardShellClasses}>
                <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(16,185,129,0.12)_0%,rgba(16,185,129,0.05)_28%,rgba(16,185,129,0.02)_52%,transparent_78%)] dark:bg-[linear-gradient(180deg,rgba(16,185,129,0.1)_0%,rgba(16,185,129,0.05)_24%,rgba(16,185,129,0.02)_48%,transparent_74%)]" />
                <div className="pointer-events-none absolute top-0 left-0 h-56 w-full bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.1),transparent_60%)] lg:bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.08),transparent_62%)] dark:bg-[radial-gradient(circle_at_top_left,rgba(16,185,129,0.08),transparent_64%)]" />
                <div className="pointer-events-none absolute inset-x-[18%] top-12 h-40 bg-[radial-gradient(circle,rgba(16,185,129,0.08),transparent_68%)] blur-2xl dark:bg-[radial-gradient(circle,rgba(16,185,129,0.06),transparent_70%)]" />

                <section className="relative animate-in duration-500 fade-in-0 slide-in-from-top-2">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <h2 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                            Accounts
                        </h2>

                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className={dashboardControlClasses}
                                onClick={() => {
                                    void startSyncAll();
                                }}
                                disabled={isSyncing}
                            >
                                <RefreshCw
                                    className={`size-4 ${isSyncing ? 'animate-spin' : ''}`}
                                />
                                Sync All
                            </Button>

                            <AddNewWalletMenu
                                onConnectBank={handleConnectBank}
                            />
                        </div>
                    </div>

                    {connectError ? (
                        <div className="mb-3 rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            {connectError}
                        </div>
                    ) : null}

                    {syncErrorMessage ? (
                        <div className="mb-3 flex items-center justify-between gap-2 rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            <span>{syncErrorMessage}</span>
                            <button
                                type="button"
                                className="text-xs font-semibold"
                                onClick={clearErrorMessage}
                            >
                                Dismiss
                            </button>
                        </div>
                    ) : null}

                    {syncSuccessMessage ? (
                        <div className="mb-3 flex items-center justify-between gap-2 rounded-lg border border-emerald-300/60 bg-emerald-100/60 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <span>{syncSuccessMessage}</span>
                            <button
                                type="button"
                                className="text-xs font-semibold"
                                onClick={clearSuccessMessage}
                            >
                                Dismiss
                            </button>
                        </div>
                    ) : null}

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {showPendingNewAccount && isRefreshing ? (
                            <Card className="gap-0 rounded-2xl border border-dashed border-emerald-300/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(241,252,247,0.98))] py-4 dark:border-emerald-700/45 dark:bg-linear-to-br dark:from-emerald-500/8 dark:via-card dark:to-card">
                                <CardContent className="flex items-center gap-3 px-4">
                                    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                        <Loader2 className="size-5 animate-spin" />
                                    </div>

                                    <div className="min-w-0">
                                        <p className="truncate text-[15px] font-medium text-foreground">
                                            Syncing new account...
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            Importing balances and latest
                                            transactions
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        ) : null}

                        {accounts.map((account, index) => {
                            const isPositive = account.current_balance > 0;
                            const isNegative = account.current_balance < 0;
                            const title =
                                account.display_name ??
                                account.name ??
                                account.official_name ??
                                'Linked Account';
                            const subtitle =
                                account.institution_name ??
                                account.subtype ??
                                account.type ??
                                'Bank account';

                            return (
                                <Link
                                    key={account.id}
                                    href={`/accounts/${account.id}?start_date=${filters.start_date}&end_date=${filters.end_date}`}
                                    className="block"
                                >
                                    <Card
                                        className={`gap-0 py-4 transition hover:-translate-y-0.5 hover:border-brand/35 ${dashboardPanelClasses}`}
                                        style={{
                                            animationDelay: `${index * 40}ms`,
                                        }}
                                    >
                                        <CardContent className="flex items-center gap-3 px-4">
                                            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                <Building2 className="size-5" />
                                            </div>

                                            <div className="min-w-0">
                                                <p className="truncate text-[15px] font-medium text-foreground">
                                                    {title}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {subtitle}
                                                    {account.mask
                                                        ? ` • **** ${account.mask}`
                                                        : ''}
                                                </p>
                                                <p
                                                    className={`mt-1.5 text-2xl leading-none font-semibold tracking-tight ${
                                                        isPositive
                                                            ? 'text-emerald-600 dark:text-emerald-400'
                                                            : isNegative
                                                              ? 'text-[#ff6f61] dark:text-[#ff8b80]'
                                                              : 'text-muted-foreground dark:text-slate-400'
                                                    }`}
                                                >
                                                    {formatCurrency(
                                                        account.current_balance,
                                                        account.currency,
                                                    )}
                                                </p>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </Link>
                            );
                        })}

                        {accounts.length === 0 && (
                            <Card className="col-span-full gap-0 rounded-2xl border border-dashed border-brand/20 bg-[linear-gradient(180deg,rgba(255,255,255,0.94),rgba(244,250,247,0.9))] py-6 dark:border-brand/15 dark:bg-linear-to-br dark:from-brand/6 dark:via-card dark:to-card">
                                <CardContent className="px-4 text-center text-sm text-muted-foreground">
                                    No connected bank accounts yet. Use Add New
                                    Wallet to connect one with Plaid.
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </section>

                <section className="relative animate-in duration-500 fade-in-0 [animation-delay:120ms] slide-in-from-bottom-2">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                            Current Period
                        </h2>

                        <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                            <Button
                                variant="outline"
                                size="icon"
                                className={`size-9 rounded-xl ${dashboardControlClasses}`}
                                onClick={() => {
                                    applyDateFilter(
                                        previousMonthRange.startDate,
                                        previousMonthRange.endDate,
                                    );
                                }}
                            >
                                <ArrowLeft className="size-4" />
                            </Button>

                            <div
                                className={`flex min-w-57.5 items-center gap-2 rounded-xl border px-3 py-2 text-sm shadow-sm ${dashboardControlClasses}`}
                            >
                                <CalendarDays className="size-4 text-muted-foreground" />
                                <span>
                                    {formatDisplayDate(filters.start_date)} -{' '}
                                    {formatDisplayDate(filters.end_date)}
                                </span>
                            </div>

                            <Button
                                variant="outline"
                                size="icon"
                                className={`size-9 rounded-xl ${dashboardControlClasses}`}
                                onClick={() => {
                                    applyDateFilter(
                                        nextMonthRange.startDate,
                                        nextMonthRange.endDate,
                                    );
                                }}
                            >
                                <ArrowRight className="size-4" />
                            </Button>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div
                            className={`rounded-xl p-4 ${dashboardPanelClasses}`}
                        >
                            <p className="text-sm font-medium text-muted-foreground">
                                Current Wallet Balance
                            </p>
                            <p className="mt-1 text-4xl font-semibold tracking-tight text-emerald-600 dark:text-emerald-400">
                                {formatCurrency(summary.total_balance, 'EUR')}
                            </p>
                        </div>

                        <div
                            className={`rounded-xl p-4 ${dashboardPanelClasses}`}
                        >
                            <p className="text-sm font-medium text-muted-foreground">
                                Total Period Change
                            </p>
                            <p
                                className={`mt-1 text-4xl font-semibold tracking-tight ${
                                    summary.period_change >= 0
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-[#ff6f61] dark:text-[#ff8b80]'
                                }`}
                            >
                                {formatCurrency(summary.period_change, 'EUR')}
                            </p>
                        </div>

                        <div
                            className={`rounded-xl p-4 ${dashboardPanelClasses}`}
                        >
                            <p className="text-sm font-medium text-muted-foreground">
                                Total Period Expenses
                            </p>
                            <p className="mt-1 text-4xl font-semibold tracking-tight text-[#ff6f61] dark:text-[#ff8b80]">
                                {formatCurrency(summary.period_expenses, 'EUR')}
                            </p>
                        </div>

                        <div
                            className={`rounded-xl p-4 ${dashboardPanelClasses}`}
                        >
                            <p className="text-sm font-medium text-muted-foreground">
                                Total Period Income
                            </p>
                            <p className="mt-1 text-4xl font-semibold tracking-tight text-emerald-600 dark:text-emerald-400">
                                {formatCurrency(summary.period_income, 'EUR')}
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
