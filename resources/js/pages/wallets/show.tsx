import { Head, router, usePage } from '@inertiajs/react';
import Link2 from 'lucide-react/dist/esm/icons/link-2.js';
import RefreshCw from 'lucide-react/dist/esm/icons/refresh-cw.js';
import WalletIcon from 'lucide-react/dist/esm/icons/wallet.js';
import Heading from '@/components/heading';
import PlaidLinkButton from '@/components/plaid-link-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDate, formatLocalizedDateTime } from '@/lib/date-filters';
import type { BreadcrumbItem } from '@/types';

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
    balances: {
        available?: number | null;
        current?: number | null;
    } | null;
    last_synced_at: string | null;
};

type Connection = {
    id: number;
    status: string;
    institution_name: string | null;
    last_synced_at: string | null;
    last_webhook_at: string | null;
    error_message: string | null;
    accounts: Account[];
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

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'connected') {
        return 'default';
    }

    if (status === 'syncing') {
        return 'secondary';
    }

    if (
        status === 'needs_reauth' ||
        status === 'error' ||
        status === 'sync_failed'
    ) {
        return 'destructive';
    }

    return 'outline';
}

export default function WalletShow({ wallet }: { wallet: Wallet }) {
    const page = usePage();
    const numberLocale =
        typeof page.props.number_locale === 'string'
            ? page.props.number_locale
            : 'en-GB';
    const query = page.url.includes('?') ? page.url.split('?')[1] : '';
    const params = new URLSearchParams(query);
    const autoConnect = params.get('auto_connect') === '1';

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
                                description="Manage linked banks, accounts, and transactions for this wallet."
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

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        '/wallets/refresh-all',
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <RefreshCw className="size-4" />
                                Refresh All Data
                            </Button>

                            <PlaidLinkButton
                                walletId={wallet.id}
                                autoStart={autoConnect}
                            />
                        </div>
                    </div>
                </div>

                <div className="space-y-4">
                    {wallet.connections.map((connection) => (
                        <section
                            key={connection.id}
                            className="space-y-4 rounded-xl border border-brand/20 bg-linear-to-br from-brand/6 via-card to-card p-4 dark:from-brand/10"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="space-y-1">
                                    <p className="text-base font-semibold">
                                        <Link2 className="mr-1 inline size-4 text-brand" />
                                        {connection.institution_name ??
                                            'Linked Institution'}
                                    </p>
                                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                        <Badge
                                            variant={statusVariant(
                                                connection.status,
                                            )}
                                        >
                                            {connection.status}
                                        </Badge>
                                        {wallet.type !== 'cash' ? (
                                            <>
                                                <span>
                                                    Last synced:{' '}
                                                    {formatDate(
                                                        connection.last_synced_at,
                                                        numberLocale,
                                                    )}
                                                </span>
                                                <span>
                                                    Webhook:{' '}
                                                    {formatDate(
                                                        connection.last_webhook_at,
                                                        numberLocale,
                                                    )}
                                                </span>
                                            </>
                                        ) : null}
                                    </div>
                                    {connection.error_message ? (
                                        <p className="text-sm text-destructive">
                                            {connection.error_message}
                                        </p>
                                    ) : null}
                                </div>

                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            `/bank-connections/${connection.id}/refresh`,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <RefreshCw className="size-4" />
                                    Refresh Data
                                </Button>
                            </div>

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
                                                                    {account
                                                                        .balances
                                                                        ?.current ??
                                                                        0}{' '}
                                                                    {account.currency ??
                                                                        wallet.currency}
                                                                </p>
                                                                {wallet.type !== 'cash' ? (
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
                                                            variant={statusVariant(
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
                                                    +{run.added_count} / ~
                                                    {run.modified_count} / -
                                                    {run.removed_count}
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
                        </section>
                    ))}

                    {wallet.connections.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border bg-card p-10 text-center text-muted-foreground">
                            This wallet has no bank connections yet. Click Bank
                            Connection to start Plaid Link.
                        </div>
                    ) : null}
                </div>
            </div>
        </AppLayout>
    );
}
