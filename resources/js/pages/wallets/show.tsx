import { Head, Link, router, usePage } from '@inertiajs/react';
import RefreshCw from 'lucide-react/dist/esm/icons/refresh-cw.js';
import Unplug from 'lucide-react/dist/esm/icons/unplug.js';
import Upload from 'lucide-react/dist/esm/icons/upload.js';
import WalletIcon from 'lucide-react/dist/esm/icons/wallet.js';
import { useEffect, useRef, useState } from 'react';
import BankAlertsBanner from '@/components/bank-alerts-banner';
import BankLogo from '@/components/bank-logo';
import Heading from '@/components/heading';
import IbkrConnectDialog from '@/components/ibkr-connect-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useBankAccountLinkedRefresh } from '@/hooks/use-bank-account-linked-refresh';
import { useSyncAllConnections } from '@/hooks/use-sync-all-connections';
import AppLayout from '@/layouts/app-layout';
import { openBankPopup } from '@/lib/bank-popup';
import { bankStatusLabel, bankStatusVariant } from '@/lib/bank-status';
import { formatDisplayDate, formatLocalizedDateTime } from '@/lib/date-filters';
import type { BankConnectionStatus, BreadcrumbItem } from '@/types';

type SyncRun = {
    id: number;
    sync_type: string;
    status: string;
    added_count: number;
    modified_count: number;
    removed_count: number;
    started_at: string | null;
    finished_at: string | null;
    error_message: string | null;
};

type Transaction = {
    id: number;
    name: string | null;
    merchant_name: string | null;
    amount: number;
    currency: string | null;
    date: string | null;
    pending: boolean;
};

type Account = {
    id: number;
    name: string | null;
    official_name: string | null;
    mask: string | null;
    type: string | null;
    subtype: string | null;
    currency: string | null;
    is_active: boolean;
    balances: {
        available?: number | null;
        current?: number | null;
    } | null;
    last_synced_at: string | null;
};

type StatementImport = {
    id: number;
    filename: string;
    rows_added: number;
    rows_updated: number;
    date_from: string | null;
    date_to: string | null;
    created_at: string | null;
};

type Holding = {
    id: number;
    symbol: string;
    description: string | null;
    asset_category: string | null;
    quantity: number;
    mark_price: number | null;
    currency: string;
    position_value: number;
    value_in_base: number;
    unrealized_pnl: number | null;
    unrealized_pnl_in_base: number | null;
};

type InvestmentActivity = {
    id: number;
    kind: 'buy' | 'sell' | 'deposit' | 'withdrawal' | string;
    date: string | null;
    symbol: string | null;
    description: string | null;
    quantity: number | null;
    price: number | null;
    amount: number;
    commission: number | null;
    currency: string;
};

type Investments = {
    base_currency: string | null;
    total_value: number;
    cash: number;
    as_of: string | null;
    unrealized_pnl: number;
    holdings: Holding[];
    activities: InvestmentActivity[];
    query_id: string | null;
};

const ACTIVITY_LABELS: Record<string, string> = {
    buy: 'Buy',
    sell: 'Sell',
    deposit: 'Deposit',
    withdrawal: 'Withdrawal',
};

type Connection = BankConnectionStatus & {
    accounts: Account[];
    investments: Investments | null;
    imports: StatementImport[];
    transactions: Transaction[];
    sync_runs: SyncRun[];
};

type Wallet = {
    id: number;
    name: string;
    type: string;
    currency: string;
    last_synced_at: string | null;
    connections: Connection[];
};

function formatDate(value: string | null, locale: string): string {
    return formatLocalizedDateTime(value, locale);
}

function runStatusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'succeeded') {
        return 'default';
    }

    if (status === 'failed') {
        return 'destructive';
    }

    return 'secondary';
}

export default function WalletShow({ wallet }: { wallet: Wallet }) {
    const page = usePage();
    const numberLocale =
        typeof page.props.number_locale === 'string'
            ? page.props.number_locale
            : 'en-GB';
    const isCashWallet = wallet.type === 'cash';
    const [popupBlocked, setPopupBlocked] = useState(false);
    const [ibkrDialogFor, setIbkrDialogFor] = useState<Connection | null>(null);
    const money = (value: number, currency: string | null): string =>
        new Intl.NumberFormat(numberLocale, {
            style: 'currency',
            currency: currency ?? wallet.currency,
        }).format(value);
    const quantity = (value: number): string =>
        new Intl.NumberFormat(numberLocale, {
            maximumFractionDigits: 4,
        }).format(value);

    // While a connection is syncing (e.g. IBKR preparing its report), refresh every few
    // seconds for up to three minutes.
    const anySyncing = wallet.connections.some(
        (connection) => connection.status === 'syncing',
    );
    const pollCountRef = useRef(0);

    useEffect(() => {
        if (!anySyncing) {
            pollCountRef.current = 0;

            return;
        }

        if (pollCountRef.current >= 45) {
            return;
        }

        const timer = window.setTimeout(() => {
            pollCountRef.current += 1;
            router.reload({ only: ['wallet', 'bankAlerts'] });
        }, 4000);

        return () => window.clearTimeout(timer);
    }, [anySyncing, wallet]);

    useBankAccountLinkedRefresh({ only: ['wallet', 'bankAlerts'] });

    const {
        isSyncing,
        successMessage,
        errorMessage,
        startSyncAll,
        startSyncConnection,
        clearSuccessMessage,
        clearErrorMessage,
    } = useSyncAllConnections({ only: ['wallet', 'bankAlerts'] });

    const disconnect = (connection: Connection): void => {
        const name = connection.institution_name ?? 'this bank';
        const isImport = connection.provider === 'import';
        const isIbkr = connection.provider === 'ibkr';

        if (
            !window.confirm(
                isImport
                    ? `Delete ${name}? Its account and all imported transactions will be deleted from SpendLess.`
                    : isIbkr
                      ? `Disconnect ${name}? Its holdings, trades and dividends will be deleted from SpendLess. Your IBKR account is not affected.`
                      : `Disconnect ${name}? Its accounts and all their transactions will be deleted from SpendLess, and access at the bank is ended.`,
            )
        ) {
            return;
        }

        router.delete(`/banking/connections/${connection.id}`);
    };

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Wallets',
            href: '/wallets',
        },
        {
            title: wallet.name,
            href: `/wallets/${wallet.id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${wallet.name} Wallet`} />

            <div className="space-y-6">
                <div className="rounded-xl border border-brand/20 bg-linear-to-br from-brand/6 via-card to-card p-5 dark:from-brand/10">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <Heading
                                title={wallet.name}
                                description={
                                    isCashWallet
                                        ? 'A cash wallet you keep up to date yourself.'
                                        : wallet.type === 'investment'
                                          ? 'Account value, holdings, trades and dividends.'
                                          : 'Linked bank, its accounts and recent transactions.'
                                }
                            />
                            <div className="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
                                <WalletIcon className="size-4 text-brand" />
                                <span>
                                    {wallet.type} · {wallet.currency}
                                </span>
                                {wallet.type !== 'cash' ? (
                                    <>
                                        <span>·</span>
                                        <span>
                                            Last synced:{' '}
                                            {formatDate(
                                                wallet.last_synced_at,
                                                numberLocale,
                                            )}
                                        </span>
                                    </>
                                ) : null}
                            </div>
                        </div>

                        {!isCashWallet && wallet.connections.length > 1 ? (
                            <Button
                                variant="outline"
                                onClick={() => void startSyncAll()}
                                disabled={isSyncing}
                            >
                                <RefreshCw
                                    className={`size-4 ${isSyncing ? 'animate-spin' : ''}`}
                                />
                                Sync All
                            </Button>
                        ) : null}
                    </div>
                </div>

                <BankAlertsBanner />

                {errorMessage || popupBlocked ? (
                    <div className="flex items-center justify-between gap-2 rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        <span>
                            {popupBlocked
                                ? 'Popup was blocked. Please allow popups for this site and try again.'
                                : errorMessage}
                        </span>
                        <button
                            type="button"
                            className="text-xs font-semibold"
                            onClick={() => {
                                setPopupBlocked(false);
                                clearErrorMessage();
                            }}
                        >
                            Dismiss
                        </button>
                    </div>
                ) : null}

                {successMessage ? (
                    <div className="flex items-center justify-between gap-2 rounded-lg border border-emerald-300/60 bg-emerald-100/60 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-950/40 dark:text-emerald-300">
                        <span>{successMessage}</span>
                        <button
                            type="button"
                            className="text-xs font-semibold"
                            onClick={clearSuccessMessage}
                        >
                            Dismiss
                        </button>
                    </div>
                ) : null}

                <div className="space-y-4">
                    {wallet.connections.map((connection) => (
                        <section
                            key={connection.id}
                            className="space-y-4 rounded-xl border border-brand/20 bg-linear-to-br from-brand/6 via-card to-card p-4 dark:from-brand/10"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="space-y-1">
                                    <p className="flex items-center gap-2 text-base font-semibold">
                                        <BankLogo
                                            src={connection.institution_logo}
                                            name={
                                                connection.institution_name ??
                                                'Bank'
                                            }
                                            className="size-7"
                                        />
                                        {connection.institution_name ??
                                            'Linked Institution'}
                                    </p>
                                    {!isCashWallet ? (
                                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                            <Badge
                                                variant={
                                                    connection.provider ===
                                                    'import'
                                                        ? 'outline'
                                                        : bankStatusVariant(
                                                              connection.status,
                                                          )
                                                }
                                            >
                                                {connection.provider ===
                                                'import'
                                                    ? 'Statement import'
                                                    : bankStatusLabel(
                                                          connection.status,
                                                      )}
                                            </Badge>
                                            <span>
                                                {connection.provider ===
                                                'import'
                                                    ? 'Last import: '
                                                    : 'Last synced: '}
                                                {formatDate(
                                                    connection.last_synced_at,
                                                    numberLocale,
                                                )}
                                            </span>
                                            {connection.next_sync_at &&
                                            connection.status !==
                                                'needs_reauth' ? (
                                                <span>
                                                    Next:{' '}
                                                    {formatDate(
                                                        connection.next_sync_at,
                                                        numberLocale,
                                                    )}
                                                </span>
                                            ) : null}
                                            {connection.consent_valid_until ? (
                                                <span
                                                    className={
                                                        connection.consent_expires_soon
                                                            ? 'font-medium text-amber-700 dark:text-amber-300'
                                                            : undefined
                                                    }
                                                >
                                                    Access until{' '}
                                                    {formatDisplayDate(
                                                        connection.consent_valid_until.slice(
                                                            0,
                                                            10,
                                                        ),
                                                        numberLocale,
                                                    )}
                                                </span>
                                            ) : null}
                                        </div>
                                    ) : null}
                                    {connection.error_message ? (
                                        <p className="text-sm text-destructive">
                                            {connection.error_message}
                                        </p>
                                    ) : null}
                                </div>

                                {!isCashWallet ? (
                                    <div className="flex flex-wrap items-center gap-2">
                                        {connection.provider === 'import' &&
                                        connection.accounts[0] ? (
                                            <Button asChild>
                                                <Link
                                                    href={`/imports/new?account=${connection.accounts[0].id}`}
                                                >
                                                    <Upload className="size-4" />
                                                    Import statement
                                                </Link>
                                            </Button>
                                        ) : null}
                                        {connection.provider === 'ibkr' ? (
                                            <Button
                                                variant={
                                                    connection.status ===
                                                    'needs_reauth'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                onClick={() =>
                                                    setIbkrDialogFor(connection)
                                                }
                                            >
                                                Update token
                                            </Button>
                                        ) : null}
                                        {connection.provider ===
                                            'enable_banking' &&
                                        (connection.status === 'needs_reauth' ||
                                            connection.consent_expires_soon) ? (
                                            <Button
                                                onClick={() =>
                                                    setPopupBlocked(
                                                        !openBankPopup(
                                                            connection.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                Reconnect
                                            </Button>
                                        ) : null}
                                        {connection.provider !== 'import' &&
                                        connection.status !== 'needs_reauth' ? (
                                            <Button
                                                variant="outline"
                                                disabled={
                                                    isSyncing ||
                                                    connection.status ===
                                                        'syncing'
                                                }
                                                onClick={() =>
                                                    void startSyncConnection(
                                                        connection.id,
                                                    )
                                                }
                                            >
                                                <RefreshCw
                                                    className={`size-4 ${isSyncing || connection.status === 'syncing' ? 'animate-spin' : ''}`}
                                                />
                                                Sync now
                                            </Button>
                                        ) : null}
                                        <Button
                                            variant="outline"
                                            className="text-destructive hover:text-destructive"
                                            onClick={() =>
                                                disconnect(connection)
                                            }
                                        >
                                            <Unplug className="size-4" />
                                            {connection.provider === 'import'
                                                ? 'Delete'
                                                : 'Disconnect'}
                                        </Button>
                                    </div>
                                ) : null}
                            </div>

                            {connection.investments ? (
                                <div className="space-y-4 rounded-lg border border-border bg-background/75 p-3">
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        {[
                                            {
                                                label: 'Account value',
                                                value: money(
                                                    connection.investments
                                                        .total_value,
                                                    connection.investments
                                                        .base_currency,
                                                ),
                                                tone: '',
                                            },
                                            {
                                                label: 'Cash',
                                                value: money(
                                                    connection.investments.cash,
                                                    connection.investments
                                                        .base_currency,
                                                ),
                                                tone: '',
                                            },
                                            {
                                                label: 'Unrealised gain/loss',
                                                value: money(
                                                    connection.investments
                                                        .unrealized_pnl,
                                                    connection.investments
                                                        .base_currency,
                                                ),
                                                tone:
                                                    connection.investments
                                                        .unrealized_pnl < 0
                                                        ? 'text-rose-600 dark:text-rose-400'
                                                        : 'text-emerald-600 dark:text-emerald-400',
                                            },
                                        ].map((item) => (
                                            <div
                                                key={item.label}
                                                className="rounded-md border border-border bg-card px-3 py-2"
                                            >
                                                <p className="text-xs text-muted-foreground">
                                                    {item.label}
                                                </p>
                                                <p
                                                    className={`text-lg font-semibold ${item.tone}`}
                                                >
                                                    {item.value}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                    {connection.investments.as_of ? (
                                        <p className="text-xs text-muted-foreground">
                                            Values at close of{' '}
                                            {formatDisplayDate(
                                                connection.investments.as_of,
                                                numberLocale,
                                            )}
                                            . IBKR data is end-of-day.
                                        </p>
                                    ) : null}

                                    <div>
                                        <h3 className="mb-2 font-medium">
                                            Holdings
                                        </h3>
                                        {connection.investments.holdings
                                            .length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No open positions.
                                            </p>
                                        ) : (
                                            <div className="overflow-x-auto rounded-md border border-border">
                                                <table className="w-full text-sm">
                                                    <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                                                        <tr>
                                                            <th className="px-3 py-2 font-medium">
                                                                Holding
                                                            </th>
                                                            <th className="px-3 py-2 text-right font-medium">
                                                                Quantity
                                                            </th>
                                                            <th className="px-3 py-2 text-right font-medium">
                                                                Price
                                                            </th>
                                                            <th className="px-3 py-2 text-right font-medium">
                                                                Value
                                                            </th>
                                                            <th className="px-3 py-2 text-right font-medium">
                                                                Gain/loss
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {connection.investments.holdings.map(
                                                            (holding) => (
                                                                <tr
                                                                    key={
                                                                        holding.id
                                                                    }
                                                                    className="border-t border-border"
                                                                >
                                                                    <td className="px-3 py-2">
                                                                        <p className="font-medium">
                                                                            {
                                                                                holding.symbol
                                                                            }
                                                                        </p>
                                                                        <p className="max-w-xs truncate text-xs text-muted-foreground">
                                                                            {holding.description ??
                                                                                holding.asset_category}
                                                                        </p>
                                                                    </td>
                                                                    <td className="px-3 py-2 text-right whitespace-nowrap">
                                                                        {quantity(
                                                                            holding.quantity,
                                                                        )}
                                                                    </td>
                                                                    <td className="px-3 py-2 text-right whitespace-nowrap">
                                                                        {holding.mark_price !==
                                                                        null
                                                                            ? money(
                                                                                  holding.mark_price,
                                                                                  holding.currency,
                                                                              )
                                                                            : '–'}
                                                                    </td>
                                                                    <td className="px-3 py-2 text-right whitespace-nowrap">
                                                                        {money(
                                                                            holding.position_value,
                                                                            holding.currency,
                                                                        )}
                                                                    </td>
                                                                    <td
                                                                        className={`px-3 py-2 text-right whitespace-nowrap ${
                                                                            (holding.unrealized_pnl ??
                                                                                0) <
                                                                            0
                                                                                ? 'text-rose-600 dark:text-rose-400'
                                                                                : 'text-emerald-600 dark:text-emerald-400'
                                                                        }`}
                                                                    >
                                                                        {holding.unrealized_pnl !==
                                                                        null
                                                                            ? money(
                                                                                  holding.unrealized_pnl,
                                                                                  holding.currency,
                                                                              )
                                                                            : '–'}
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </div>

                                    <div>
                                        <h3 className="mb-2 font-medium">
                                            Trades &amp; transfers
                                        </h3>
                                        {connection.investments.activities
                                            .length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No trades or transfers in the
                                                report period.
                                            </p>
                                        ) : (
                                            <div className="space-y-2">
                                                {connection.investments.activities.map(
                                                    (activity) => (
                                                        <div
                                                            key={activity.id}
                                                            className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border bg-card p-2.5 text-sm"
                                                        >
                                                            <div className="flex min-w-0 items-center gap-2">
                                                                <Badge variant="outline">
                                                                    {ACTIVITY_LABELS[
                                                                        activity
                                                                            .kind
                                                                    ] ??
                                                                        activity.kind}
                                                                </Badge>
                                                                <span className="min-w-0 truncate">
                                                                    {activity.symbol
                                                                        ? `${activity.symbol} · `
                                                                        : ''}
                                                                    {activity.quantity !==
                                                                        null &&
                                                                    activity.price !==
                                                                        null
                                                                        ? `${quantity(Math.abs(activity.quantity))} @ ${money(activity.price, activity.currency)}`
                                                                        : activity.description}
                                                                </span>
                                                            </div>
                                                            <span className="text-xs text-muted-foreground">
                                                                {activity.date
                                                                    ? formatDisplayDate(
                                                                          activity.date,
                                                                          numberLocale,
                                                                      )
                                                                    : ''}{' '}
                                                                ·{' '}
                                                                <span
                                                                    className={
                                                                        activity.amount <
                                                                        0
                                                                            ? 'text-rose-600 dark:text-rose-400'
                                                                            : 'text-emerald-600 dark:text-emerald-400'
                                                                    }
                                                                >
                                                                    {money(
                                                                        activity.amount,
                                                                        activity.currency,
                                                                    )}
                                                                </span>
                                                            </span>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ) : null}

                            <div className="grid gap-4 lg:grid-cols-[1.2fr_1fr]">
                                <div className="space-y-2 rounded-lg border border-border bg-background/75 p-3">
                                    <h3 className="font-medium">
                                        Linked Accounts
                                    </h3>
                                    {connection.accounts.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No accounts imported yet.
                                        </p>
                                    ) : (
                                        <div className="space-y-2">
                                            {connection.accounts.map(
                                                (account) => (
                                                    <div
                                                        key={account.id}
                                                        className="rounded-md border border-border bg-card p-3"
                                                    >
                                                        <div className="flex items-start justify-between gap-3">
                                                            <div>
                                                                <p className="font-medium">
                                                                    {account.name ??
                                                                        'Account'}
                                                                </p>
                                                                {!account.is_active ? (
                                                                    <p className="text-xs text-muted-foreground">
                                                                        No
                                                                        longer
                                                                        shared
                                                                        by the
                                                                        bank
                                                                    </p>
                                                                ) : null}
                                                                <p className="text-xs text-muted-foreground">
                                                                    {account.official_name ??
                                                                        account.type ??
                                                                        'Unknown type'}
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {account.mask
                                                                        ? `**** ${account.mask}`
                                                                        : 'No mask available'}
                                                                </p>
                                                            </div>
                                                            <div className="text-right text-sm">
                                                                <p>
                                                                    {money(
                                                                        account
                                                                            .balances
                                                                            ?.current ??
                                                                            0,
                                                                        account.currency ??
                                                                            wallet.currency,
                                                                    )}
                                                                </p>
                                                                {wallet.type !==
                                                                'cash' ? (
                                                                    <p className="text-xs text-muted-foreground">
                                                                        Synced{' '}
                                                                        {formatDate(
                                                                            account.last_synced_at,
                                                                            numberLocale,
                                                                        )}
                                                                    </p>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="space-y-2 rounded-lg border border-border bg-background/75 p-3">
                                    <h3 className="font-medium">
                                        Recent Transactions
                                    </h3>
                                    {connection.transactions.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No transactions synced yet.
                                        </p>
                                    ) : (
                                        <div className="space-y-2">
                                            {connection.transactions
                                                .slice(0, 12)
                                                .map((transaction) => (
                                                    <div
                                                        key={transaction.id}
                                                        className="rounded-md border border-border bg-card p-2.5"
                                                    >
                                                        <div className="flex items-center justify-between gap-2">
                                                            <div>
                                                                <p className="text-sm font-medium">
                                                                    {transaction.merchant_name ??
                                                                        transaction.name ??
                                                                        'Transaction'}
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {transaction.date
                                                                        ? formatDisplayDate(
                                                                              transaction.date,
                                                                              numberLocale,
                                                                          )
                                                                        : 'Unknown date'}
                                                                </p>
                                                            </div>
                                                            <div className="text-right">
                                                                <p className="text-sm font-medium">
                                                                    {transaction.amount.toFixed(
                                                                        2,
                                                                    )}{' '}
                                                                    {transaction.currency ??
                                                                        wallet.currency}
                                                                </p>
                                                                {transaction.pending ? (
                                                                    <Badge
                                                                        variant="secondary"
                                                                        className="mt-1"
                                                                    >
                                                                        Pending
                                                                    </Badge>
                                                                ) : null}
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {connection.provider === 'import' ? (
                                <div className="rounded-lg border border-border bg-background/75 p-3">
                                    <h3 className="mb-2 font-medium">
                                        Recent Imports
                                    </h3>
                                    {connection.imports.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No statements imported yet.
                                        </p>
                                    ) : (
                                        <div className="space-y-2">
                                            {connection.imports.map((item) => (
                                                <div
                                                    key={item.id}
                                                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border bg-card p-2.5 text-sm"
                                                >
                                                    <span className="min-w-0 truncate">
                                                        {item.filename}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {item.rows_added} new ·{' '}
                                                        {item.rows_updated}{' '}
                                                        already there
                                                        {item.date_from &&
                                                        item.date_to
                                                            ? ` · ${formatDisplayDate(item.date_from, numberLocale)} – ${formatDisplayDate(item.date_to, numberLocale)}`
                                                            : ''}
                                                        {' · '}
                                                        {formatDate(
                                                            item.created_at,
                                                            numberLocale,
                                                        )}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ) : isCashWallet ? null : (
                                <div className="rounded-lg border border-border bg-background/75 p-3">
                                    <h3 className="mb-2 font-medium">
                                        Recent Sync Runs
                                    </h3>
                                    {connection.sync_runs.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            No sync runs recorded yet.
                                        </p>
                                    ) : (
                                        <div className="space-y-2">
                                            {connection.sync_runs.map((run) => (
                                                <div
                                                    key={run.id}
                                                    className="rounded-md border border-border bg-card p-2.5 text-sm"
                                                >
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <div className="flex items-center gap-2">
                                                            <Badge
                                                                variant={runStatusVariant(
                                                                    run.status,
                                                                )}
                                                            >
                                                                {run.status}
                                                            </Badge>
                                                            <span className="text-muted-foreground">
                                                                {run.sync_type}
                                                            </span>
                                                        </div>
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatDate(
                                                                run.started_at,
                                                                numberLocale,
                                                            )}
                                                        </span>
                                                    </div>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {run.added_count} new ·{' '}
                                                        {run.modified_count}{' '}
                                                        updated ·{' '}
                                                        {run.removed_count}{' '}
                                                        removed
                                                    </p>
                                                    {run.error_message ? (
                                                        <p className="mt-1 text-xs text-destructive">
                                                            {run.error_message}
                                                        </p>
                                                    ) : null}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </section>
                    ))}

                    {wallet.connections.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border bg-card p-10 text-center text-muted-foreground">
                            This wallet has no bank connections. Connect a bank
                            from the dashboard or the Wallets page.
                        </div>
                    ) : null}
                </div>
            </div>

            <IbkrConnectDialog
                open={ibkrDialogFor !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setIbkrDialogFor(null);
                    }
                }}
                connectionId={ibkrDialogFor?.id}
                initialQueryId={ibkrDialogFor?.investments?.query_id ?? null}
            />
        </AppLayout>
    );
}
