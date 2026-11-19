import { Head, Link, usePage } from '@inertiajs/react';
import RefreshCw from 'lucide-react/dist/esm/icons/refresh-cw.js';
import WalletIcon from 'lucide-react/dist/esm/icons/wallet.js';
import { useState } from 'react';
import AddNewWalletMenu from '@/components/add-new-wallet-menu';
import BankAlertsBanner from '@/components/bank-alerts-banner';
import BankLogo from '@/components/bank-logo';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useBankAccountLinkedRefresh } from '@/hooks/use-bank-account-linked-refresh';
import { useSyncAllConnections } from '@/hooks/use-sync-all-connections';
import AppLayout from '@/layouts/app-layout';
import { openBankPopup } from '@/lib/bank-popup';
import { bankStatusLabel, bankStatusVariant } from '@/lib/bank-status';
import { formatLocalizedDateTime } from '@/lib/date-filters';
import type { BankConnectionStatus, BreadcrumbItem } from '@/types';

type WalletSummary = {
    id: number;
    name: string;
    type: string;
    currency: string;
    last_synced_at: string | null;
    connections_count: number;
    accounts_count: number;
    connections: BankConnectionStatus[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Wallets',
        href: '/wallets',
    },
];

function formatDate(value: string | null, locale: string): string {
    return formatLocalizedDateTime(value, locale);
}

export default function WalletsIndex({
    wallets,
}: {
    wallets: WalletSummary[];
}) {
    const page = usePage();
    const numberLocale =
        typeof page.props.number_locale === 'string'
            ? page.props.number_locale
            : 'en-GB';
    const [connectError, setConnectError] = useState<string | null>(null);

    useBankAccountLinkedRefresh({ only: ['wallets', 'bankAlerts'] });

    const {
        isSyncing,
        successMessage,
        errorMessage,
        startSyncAll,
        clearSuccessMessage,
        clearErrorMessage,
    } = useSyncAllConnections({ only: ['wallets', 'bankAlerts'] });

    const hasBankWallets = wallets.some((wallet) => wallet.type !== 'cash');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Wallets" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        title="Wallets"
                        description="Your connected banks and cash wallets. Banks sync automatically four times a day."
                    />

                    <div className="flex items-center gap-2">
                        {hasBankWallets ? (
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

                        <AddNewWalletMenu
                            onConnectBank={() =>
                                setConnectError(
                                    openBankPopup()
                                        ? null
                                        : 'Popup was blocked. Please allow popups for this site and try again.',
                                )
                            }
                        />
                    </div>
                </div>

                <BankAlertsBanner />

                {(connectError ?? errorMessage) ? (
                    <div className="flex items-center justify-between gap-2 rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        <span>{connectError ?? errorMessage}</span>
                        <button
                            type="button"
                            className="text-xs font-semibold"
                            onClick={() => {
                                setConnectError(null);
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

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {wallets.map((wallet) => (
                        <Link
                            key={wallet.id}
                            href={`/wallets/${wallet.id}`}
                            className="rounded-xl border border-brand/20 bg-linear-to-br from-brand/6 via-card to-card p-4 transition hover:border-brand/35 dark:from-brand/10"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <p className="text-lg font-semibold">
                                        {wallet.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {wallet.type} · {wallet.currency}
                                    </p>
                                </div>
                                {wallet.connections[0]?.institution_logo ? (
                                    <BankLogo
                                        src={
                                            wallet.connections[0]
                                                .institution_logo
                                        }
                                        name={wallet.name}
                                        className="size-8"
                                    />
                                ) : (
                                    <WalletIcon className="size-5 text-brand" />
                                )}
                            </div>

                            <div className="mt-4 grid grid-cols-2 gap-2 text-sm">
                                <div className="rounded-lg border border-border bg-background/70 px-3 py-2">
                                    <p className="text-muted-foreground">
                                        Connections
                                    </p>
                                    <p className="font-medium">
                                        {wallet.connections_count}
                                    </p>
                                </div>
                                <div className="rounded-lg border border-border bg-background/70 px-3 py-2">
                                    <p className="text-muted-foreground">
                                        Accounts
                                    </p>
                                    <p className="font-medium">
                                        {wallet.accounts_count}
                                    </p>
                                </div>
                            </div>

                            {wallet.type !== 'cash' ? (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Last synced:{' '}
                                    {formatDate(
                                        wallet.last_synced_at,
                                        numberLocale,
                                    )}
                                </p>
                            ) : null}

                            <div className="mt-3 flex flex-wrap gap-1.5">
                                {wallet.type === 'cash' ? (
                                    <Badge variant="outline">Cash wallet</Badge>
                                ) : wallet.connections.length === 0 ? (
                                    <Badge variant="outline">
                                        No bank connections
                                    </Badge>
                                ) : (
                                    wallet.connections
                                        .slice(0, 3)
                                        .map((connection) => (
                                            <Badge
                                                key={connection.id}
                                                variant={
                                                    connection.provider ===
                                                        'import' ||
                                                    connection.provider ===
                                                        'asset'
                                                        ? 'outline'
                                                        : bankStatusVariant(
                                                              connection.status,
                                                          )
                                                }
                                                className="max-w-full truncate"
                                            >
                                                {connection.provider ===
                                                'import'
                                                    ? 'Statement import'
                                                    : connection.provider ===
                                                        'asset'
                                                      ? wallet.type ===
                                                        'pension'
                                                          ? 'Pension'
                                                          : 'Cash ISA'
                                                      : bankStatusLabel(
                                                            connection.status,
                                                        )}
                                            </Badge>
                                        ))
                                )}
                            </div>
                        </Link>
                    ))}

                    {wallets.length === 0 ? (
                        <div className="col-span-full rounded-xl border border-dashed border-border bg-card p-8 text-center text-muted-foreground">
                            No wallets yet. Use Add wallet to connect your
                            bank or add a cash wallet.
                        </div>
                    ) : null}
                </div>
            </div>
        </AppLayout>
    );
}
