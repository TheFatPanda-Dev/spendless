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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

function formatCurrency(value: number, currency: string): string {
    const sign = value > 0 ? '+' : '';

    return `${sign}${value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} ${currency}`;
}

function formatDisplayDate(dateValue: string): string {
    const parsed = new Date(`${dateValue}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return dateValue;
    }

    return parsed.toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    });
}

function shiftMonth(dateValue: string, direction: -1 | 1): string {
    const parsed = new Date(`${dateValue}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return dateValue;
    }

    parsed.setMonth(parsed.getMonth() + direction);

    return parsed.toISOString().slice(0, 10);
}

function applyDateFilter(startDate: string, endDate: string): void {
    router.get(
        dashboard(),
        {
            start_date: startDate,
            end_date: endDate,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

export default function Dashboard({ accounts, summary, filters }: Props) {
    const [connectError, setConnectError] = useState<string | null>(null);
    const [showPendingNewAccount, setShowPendingNewAccount] = useState(false);

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

            <div className="relative flex h-full flex-1 flex-col gap-6 overflow-x-hidden rounded-2xl bg-linear-to-b from-slate-100 via-slate-100 to-slate-50 p-3 sm:p-5 lg:p-7 dark:from-background dark:via-background dark:to-background">
                <div className="pointer-events-none absolute inset-x-0 top-0 h-44 bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,0.10),transparent_70%)] dark:bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,0.09),transparent_74%)]" />

                <section className="relative animate-in duration-500 fade-in-0 slide-in-from-top-2">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <h2 className="text-2xl font-bold tracking-tight text-slate-800 sm:text-3xl dark:text-foreground">
                            Accounts
                        </h2>

                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-200 dark:bg-white dark:text-slate-700"
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
                            <Card className="gap-0 rounded-2xl border border-dashed border-emerald-300/70 bg-white py-4 dark:border-emerald-700/60 dark:bg-card">
                                <CardContent className="flex items-center gap-3 px-4">
                                    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                        <Loader2 className="size-5 animate-spin" />
                                    </div>

                                    <div className="min-w-0">
                                        <p className="truncate text-[15px] font-medium text-slate-700 dark:text-foreground">
                                            Syncing new account...
                                        </p>
                                        <p className="truncate text-xs text-slate-500 dark:text-muted-foreground">
                                            Importing balances and latest transactions
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
                                    prefetch
                                    className="block"
                                >
                                    <Card
                                        className="gap-0 rounded-2xl border border-slate-200/90 bg-white py-4 shadow-[0_10px_24px_-14px_rgba(15,23,42,0.35)] transition hover:-translate-y-0.5 hover:border-brand/35 dark:border-border/80 dark:bg-card dark:shadow-[0_10px_20px_-14px_rgba(2,6,23,0.65)]"
                                        style={{
                                            animationDelay: `${index * 40}ms`,
                                        }}
                                    >
                                        <CardContent className="flex items-center gap-3 px-4">
                                            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                                <Building2 className="size-5" />
                                            </div>

                                            <div className="min-w-0">
                                                <p className="truncate text-[15px] font-medium text-slate-700 dark:text-foreground">
                                                    {title}
                                                </p>
                                                <p className="truncate text-xs text-slate-500 dark:text-muted-foreground">
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
                                                              : 'text-slate-500 dark:text-slate-400'
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
                            <Card className="col-span-full gap-0 rounded-2xl border border-dashed border-slate-300 bg-white/80 py-6 dark:border-border dark:bg-card/85">
                                <CardContent className="px-4 text-center text-sm text-slate-500 dark:text-muted-foreground">
                                    No connected bank accounts yet. Use Add New
                                    Wallet to connect one with Plaid.
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </section>

                <section className="relative animate-in duration-500 fade-in-0 [animation-delay:120ms] slide-in-from-bottom-2">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-2xl font-bold tracking-tight text-slate-800 sm:text-3xl dark:text-foreground">
                            Current Period
                        </h2>

                        <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-9 rounded-xl border-slate-200 bg-white text-slate-600 dark:border-border dark:bg-card dark:text-foreground"
                                onClick={() => {
                                    applyDateFilter(
                                        shiftMonth(filters.start_date, -1),
                                        shiftMonth(filters.end_date, -1),
                                    );
                                }}
                            >
                                <ArrowLeft className="size-4" />
                            </Button>

                            <div className="flex min-w-57.5 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 shadow-sm dark:border-border dark:bg-card dark:text-foreground">
                                <CalendarDays className="size-4 text-slate-500 dark:text-muted-foreground" />
                                <span>
                                    {formatDisplayDate(filters.start_date)} -{' '}
                                    {formatDisplayDate(filters.end_date)}
                                </span>
                            </div>

                            <Button
                                variant="outline"
                                size="icon"
                                className="size-9 rounded-xl border-slate-200 bg-white text-slate-600 dark:border-border dark:bg-card dark:text-foreground"
                                onClick={() => {
                                    applyDateFilter(
                                        shiftMonth(filters.start_date, 1),
                                        shiftMonth(filters.end_date, 1),
                                    );
                                }}
                            >
                                <ArrowRight className="size-4" />
                            </Button>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div className="rounded-xl border border-slate-200 bg-white p-4 dark:border-border/80 dark:bg-card/90">
                            <p className="text-sm font-medium text-slate-600 dark:text-muted-foreground">
                                Current Wallet Balance
                            </p>
                            <p className="mt-1 text-4xl font-semibold tracking-tight text-emerald-600 dark:text-emerald-400">
                                {formatCurrency(summary.total_balance, 'EUR')}
                            </p>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white p-4 dark:border-border/80 dark:bg-card/90">
                            <p className="text-sm font-medium text-slate-600 dark:text-muted-foreground">
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

                        <div className="rounded-xl border border-slate-200 bg-white p-4 dark:border-border/80 dark:bg-card/90">
                            <p className="text-sm font-medium text-slate-600 dark:text-muted-foreground">
                                Total Period Expenses
                            </p>
                            <p className="mt-1 text-4xl font-semibold tracking-tight text-[#ff6f61] dark:text-[#ff8b80]">
                                {formatCurrency(summary.period_expenses, 'EUR')}
                            </p>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white p-4 dark:border-border/80 dark:bg-card/90">
                            <p className="text-sm font-medium text-slate-600 dark:text-muted-foreground">
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
