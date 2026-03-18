import { Head, Link, router } from '@inertiajs/react';
import ArrowLeft from 'lucide-react/dist/esm/icons/arrow-left.js';
import ArrowRight from 'lucide-react/dist/esm/icons/arrow-right.js';
import Building2 from 'lucide-react/dist/esm/icons/building-2.js';
import CalendarDays from 'lucide-react/dist/esm/icons/calendar-days.js';
import ChevronDown from 'lucide-react/dist/esm/icons/chevron-down.js';
import Loader2 from 'lucide-react/dist/esm/icons/loader-2.js';
import RefreshCw from 'lucide-react/dist/esm/icons/refresh-cw.js';
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

type DashboardFilters = {
    start_date: string;
    end_date: string;
};

type DashboardExchangeRates = {
    eur_per_unit: Record<string, number>;
};

type DashboardPeriodBreakdown = {
    change_by_currency: Record<string, number>;
    expenses_by_currency: Record<string, number>;
    income_by_currency: Record<string, number>;
};

type Props = {
    accounts: DashboardAccount[];
    filters: DashboardFilters;
    base_currency: string;
    number_locale: string;
    exchange_rates: DashboardExchangeRates;
    period_breakdown: DashboardPeriodBreakdown;
};

const dashboardShellClasses =
    'relative flex h-full flex-1 flex-col gap-6 overflow-x-hidden rounded-[28px] border border-brand/25 bg-linear-to-br from-brand/12 via-card to-card p-3 shadow-[0_32px_90px_-54px_rgba(16,185,129,0.42)] sm:p-5 lg:p-7 dark:border-brand/20 dark:from-brand/12 dark:via-card dark:to-card dark:shadow-none';

const dashboardPanelClasses =
    'rounded-2xl border border-brand/15 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(244,250,247,0.98))] shadow-[0_14px_36px_-24px_rgba(15,23,42,0.18)] dark:border-brand/15 dark:bg-linear-to-br dark:from-brand/6 dark:via-card dark:to-card dark:shadow-[0_16px_36px_-24px_rgba(2,6,23,0.85)]';

const dashboardControlClasses =
    'border-brand/20 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(247,251,249,0.98))] text-foreground shadow-sm hover:bg-brand/6 dark:border-brand/15 dark:bg-linear-to-br dark:from-brand/6 dark:via-card dark:to-card dark:text-foreground dark:hover:bg-card';

const DEFAULT_EUR_PER_UNIT: Record<string, number> = {
    EUR: 1,
    GBP: 1.17,
    USD: 0.92,
};

const DEFAULT_BASE_CURRENCY = 'EUR';

function normalizeCurrencyCode(currency: string | null | undefined): string {
    return (currency ?? DEFAULT_BASE_CURRENCY).toUpperCase();
}

function convertAmount(
    amount: number,
    fromCurrency: string,
    toCurrency: string,
    eurPerUnit: Record<string, number>,
): number {
    const normalizedFrom = normalizeCurrencyCode(fromCurrency);
    const normalizedTo = normalizeCurrencyCode(toCurrency);
    const fromRate = eurPerUnit[normalizedFrom];
    const toRate = eurPerUnit[normalizedTo];

    if (!fromRate || !toRate) {
        return amount;
    }

    const amountInEur = amount * fromRate;

    return amountInEur / toRate;
}

function convertTotalsByCurrency(
    totalsByCurrency: Record<string, number>,
    toCurrency: string,
    eurPerUnit: Record<string, number>,
): number {
    return Object.entries(totalsByCurrency).reduce(
        (sum, [currency, value]) =>
            sum + convertAmount(value, currency, toCurrency, eurPerUnit),
        0,
    );
}

function formatCurrency(
    value: number,
    currency: string,
    numberLocale: string,
): string {
    const sign = value > 0 ? '+' : '';

    return `${sign}${value.toLocaleString(numberLocale, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} ${currency}`;
}

function formatNumber(value: number, numberLocale: string): string {
    return value.toLocaleString(numberLocale);
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
            preserveUrl: true,
            replace: true,
            showProgress: false,
            only: ['filters', 'accounts', 'exchange_rates', 'period_breakdown'],
        },
    );
}

export default function Dashboard({
    accounts,
    filters,
    base_currency,
    number_locale,
    exchange_rates,
    period_breakdown,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: buildDashboardHref(dashboard()),
        },
    ];

    const [connectError, setConnectError] = useState<string | null>(null);
    const [showPendingNewAccount, setShowPendingNewAccount] = useState(false);
    const [mobileAccountsOpen, setMobileAccountsOpen] = useState(false);
    const [selectedMobileAccountId, setSelectedMobileAccountId] = useState<
        number | null
    >(accounts[0]?.id ?? null);
    const previousMonthRange = shiftMonthRange(filters.start_date, -1);
    const nextMonthRange = shiftMonthRange(filters.start_date, 1);

    const selectedMobileAccount =
        accounts.find((account) => account.id === selectedMobileAccountId) ??
        accounts[0] ??
        null;

    const eurPerUnit = {
        ...DEFAULT_EUR_PER_UNIT,
        ...Object.fromEntries(
            Object.entries(exchange_rates.eur_per_unit).map(
                ([currency, value]) => [normalizeCurrencyCode(currency), value],
            ),
        ),
    };

    const baseCurrency = normalizeCurrencyCode(base_currency);
    const numberLocale = number_locale;

    const totalConvertedBalance = accounts.reduce((sum, account) => {
        return (
            sum +
            convertAmount(
                account.current_balance,
                account.currency,
                baseCurrency,
                eurPerUnit,
            )
        );
    }, 0);

    const convertedPeriodChange = convertTotalsByCurrency(
        period_breakdown.change_by_currency,
        baseCurrency,
        eurPerUnit,
    );
    const convertedPeriodExpenses = convertTotalsByCurrency(
        period_breakdown.expenses_by_currency,
        baseCurrency,
        eurPerUnit,
    );
    const convertedPeriodIncome = convertTotalsByCurrency(
        period_breakdown.income_by_currency,
        baseCurrency,
        eurPerUnit,
    );

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
                    <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                            Accounts
                        </h2>

                        <div className="grid w-full grid-cols-2 gap-2 sm:w-auto sm:grid-cols-1 sm:auto-cols-max sm:grid-flow-col sm:items-center">
                            <Button
                                type="button"
                                variant="outline"
                                className={`w-full sm:w-auto ${dashboardControlClasses}`}
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

                            <AddNewWalletMenu onConnectBank={handleConnectBank} />
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

                    <div className="space-y-3 sm:hidden">
                        {accounts.length > 0 ? (
                            <>
                                {selectedMobileAccount ? (
                                    <>
                                        <button
                                            type="button"
                                            className={`w-full rounded-2xl border border-brand/30 bg-[linear-gradient(140deg,rgba(3,10,9,0.98),rgba(4,15,13,0.95)_38%,rgba(2,7,8,0.98))] p-4 text-left shadow-[0_18px_48px_-30px_rgba(16,185,129,0.62)] transition hover:border-brand/45 ${dashboardPanelClasses}`}
                                            onClick={() => {
                                                setMobileAccountsOpen(
                                                    (currentOpen) =>
                                                        !currentOpen,
                                                );
                                            }}
                                            aria-expanded={mobileAccountsOpen}
                                            aria-controls="mobile-accounts-list"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <div className="inline-flex items-center rounded-full border border-brand/35 bg-brand/12 px-2 py-0.5 text-[11px] font-semibold tracking-wide text-brand/90">
                                                        Portfolio Snapshot
                                                    </div>
                                                    <p
                                                        className={`mt-3 text-[2.45rem] leading-none font-semibold tracking-[-0.02em] sm:text-5xl ${
                                                            totalConvertedBalance >
                                                            0
                                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                                : totalConvertedBalance <
                                                                    0
                                                                  ? 'text-[#ff6f61] dark:text-[#ff8b80]'
                                                                  : 'text-muted-foreground dark:text-slate-400'
                                                        }`}
                                                    >
                                                        {formatCurrency(
                                                            totalConvertedBalance,
                                                            baseCurrency,
                                                            numberLocale,
                                                        )}
                                                    </p>
                                                    <p className="mt-2 text-xs text-slate-400">
                                                        Open to see all wallets ({formatNumber(accounts.length, numberLocale)})
                                                    </p>
                                                </div>

                                                <div className="mt-1 flex size-8 shrink-0 items-center justify-center rounded-full border border-brand/35 bg-brand/10">
                                                    <ChevronDown
                                                        className={`size-5 text-slate-300 transition-transform duration-300 ${
                                                            mobileAccountsOpen
                                                                ? 'rotate-180'
                                                                : 'rotate-0'
                                                        }`}
                                                    />
                                                </div>
                                            </div>
                                        </button>

                                        <div
                                            id="mobile-accounts-list"
                                            className={`overflow-hidden transition-all duration-300 ease-out ${
                                                mobileAccountsOpen
                                                    ? 'max-h-[30rem] opacity-100'
                                                    : 'max-h-0 opacity-0'
                                            }`}
                                        >
                                            <div className="space-y-2 pt-1">
                                                {accounts.map((account) => {
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
                                                    const isSelected =
                                                        selectedMobileAccount.id ===
                                                        account.id;

                                                    return (
                                                        <Link
                                                            key={account.id}
                                                            href={`/accounts/${account.id}?start_date=${filters.start_date}&end_date=${filters.end_date}`}
                                                            className={`block w-full rounded-xl border px-3 py-2 text-left transition ${
                                                                isSelected
                                                                    ? 'border-brand/40 bg-brand/8'
                                                                    : 'border-brand/15 bg-card'
                                                            }`}
                                                            onClick={() => {
                                                                setSelectedMobileAccountId(
                                                                    account.id,
                                                                );
                                                                setMobileAccountsOpen(
                                                                    false,
                                                                );
                                                            }}
                                                        >
                                                            <div className="flex items-start justify-between gap-3">
                                                                <p className="truncate text-sm font-medium text-foreground">
                                                                    {title}
                                                                </p>
                                                                <p
                                                                    className={`shrink-0 text-sm font-semibold ${
                                                                        convertAmount(
                                                                            account.current_balance,
                                                                            account.currency,
                                                                            baseCurrency,
                                                                            eurPerUnit,
                                                                        ) >
                                                                        0
                                                                            ? 'text-emerald-600 dark:text-emerald-400'
                                                                            : convertAmount(
                                                                                    account.current_balance,
                                                                                    account.currency,
                                                                                    baseCurrency,
                                                                                    eurPerUnit,
                                                                                ) <
                                                                                0
                                                                              ? 'text-[#ff6f61] dark:text-[#ff8b80]'
                                                                              : 'text-muted-foreground dark:text-slate-400'
                                                                    }`}
                                                                >
                                                                    {formatCurrency(
                                                                        convertAmount(
                                                                            account.current_balance,
                                                                            account.currency,
                                                                            baseCurrency,
                                                                            eurPerUnit,
                                                                        ),
                                                                        baseCurrency,
                                                                        numberLocale,
                                                                    )}
                                                                </p>
                                                            </div>
                                                            <div className="mt-0.5 flex items-center justify-between gap-3">
                                                                <p className="truncate text-xs text-muted-foreground">
                                                                    {subtitle}
                                                                    {account.mask
                                                                        ? ` • **** ${account.mask}`
                                                                        : ''}
                                                                </p>
                                                                {normalizeCurrencyCode(account.currency) !== baseCurrency ? (
                                                                    <p className="shrink-0 text-[11px] text-muted-foreground">
                                                                        {formatCurrency(
                                                                            account.current_balance,
                                                                            account.currency,
                                                                            numberLocale,
                                                                        )}
                                                                    </p>
                                                                ) : null}
                                                            </div>
                                                        </Link>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    </>
                                ) : null}
                            </>
                        ) : null}
                    </div>

                    <div className="hidden grid-cols-1 gap-3 sm:grid sm:grid-cols-2 xl:grid-cols-4">
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

                                            <div className="min-w-0 flex-1">
                                                <p className="text-[15px] leading-tight font-medium text-foreground break-words">
                                                    {title}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground break-words">
                                                    {subtitle}
                                                    {account.mask
                                                        ? ` • **** ${account.mask}`
                                                        : ''}
                                                </p>
                                                <p
                                                    className={`mt-2 text-xl leading-none font-semibold tracking-tight ${
                                                        convertAmount(
                                                            account.current_balance,
                                                            account.currency,
                                                            baseCurrency,
                                                            eurPerUnit,
                                                        ) > 0
                                                            ? 'text-emerald-600 dark:text-emerald-400'
                                                            : convertAmount(
                                                                    account.current_balance,
                                                                    account.currency,
                                                                    baseCurrency,
                                                                    eurPerUnit,
                                                                ) < 0
                                                              ? 'text-[#ff6f61] dark:text-[#ff8b80]'
                                                              : 'text-muted-foreground dark:text-slate-400'
                                                    }`}
                                                >
                                                    {formatCurrency(
                                                        convertAmount(
                                                            account.current_balance,
                                                            account.currency,
                                                            baseCurrency,
                                                            eurPerUnit,
                                                        ),
                                                        baseCurrency,
                                                        numberLocale,
                                                    )}
                                                </p>
                                                {normalizeCurrencyCode(account.currency) !== baseCurrency ? (
                                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                                        {formatCurrency(
                                                            account.current_balance,
                                                            account.currency,
                                                            numberLocale,
                                                        )}
                                                    </p>
                                                ) : null}
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

                    {accounts.length === 0 && (
                        <div className="sm:hidden">
                            <Card className="col-span-full gap-0 rounded-2xl border border-dashed border-brand/20 bg-[linear-gradient(180deg,rgba(255,255,255,0.94),rgba(244,250,247,0.9))] py-6 dark:border-brand/15 dark:bg-linear-to-br dark:from-brand/6 dark:via-card dark:to-card">
                                <CardContent className="px-4 text-center text-sm text-muted-foreground">
                                    No connected bank accounts yet. Use Add New
                                    Wallet to connect one with Plaid.
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </section>

                <section className="relative animate-in duration-500 fade-in-0 [animation-delay:120ms] slide-in-from-bottom-2">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                            Current Period
                        </h2>

                        <div className="flex w-full flex-wrap items-center justify-center gap-2 sm:w-auto sm:justify-start">
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
                                    {formatDisplayDate(filters.start_date, numberLocale)} -{' '}
                                    {formatDisplayDate(filters.end_date, numberLocale)}
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
                            className={`hidden rounded-xl p-4 sm:block ${dashboardPanelClasses}`}
                        >
                            <p className="text-sm font-medium text-muted-foreground">
                                Current Wallet Balance
                            </p>
                            <p className="mt-1 text-4xl font-semibold tracking-tight text-emerald-600 dark:text-emerald-400">
                                {formatCurrency(
                                    totalConvertedBalance,
                                    baseCurrency,
                                    numberLocale,
                                )}
                            </p>
                        </div>

                        <div
                            className={`rounded-xl p-4 ${dashboardPanelClasses}`}
                        >
                            <p className="text-sm font-medium text-muted-foreground">
                                Total Period Income
                            </p>
                            <p className="mt-1 text-4xl font-semibold tracking-tight text-emerald-600 dark:text-emerald-400">
                                {formatCurrency(
                                    convertedPeriodIncome,
                                    baseCurrency,
                                    numberLocale,
                                )}
                            </p>
                        </div>

                        <div
                            className={`rounded-xl p-4 ${dashboardPanelClasses}`}
                        >
                            <p className="text-sm font-medium text-muted-foreground">
                                Total Period Expenses
                            </p>
                            <p className="mt-1 text-4xl font-semibold tracking-tight text-[#ff6f61] dark:text-[#ff8b80]">
                                {formatCurrency(
                                    convertedPeriodExpenses,
                                    baseCurrency,
                                    numberLocale,
                                )}
                            </p>
                        </div>

                        <div
                            className={`rounded-xl p-4 ${dashboardPanelClasses}`}
                        >
                            <p className="text-sm font-medium text-muted-foreground">
                                Total Period Change
                            </p>
                            <p className="mt-1 text-4xl font-semibold tracking-tight text-amber-500 dark:text-amber-400">
                                {formatCurrency(
                                    convertedPeriodChange,
                                    baseCurrency,
                                    numberLocale,
                                )}
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
