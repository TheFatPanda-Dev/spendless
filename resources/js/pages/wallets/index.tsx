import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Plus, RefreshCw, Wallet as WalletIcon } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type WalletSummary = {
    id: number;
    name: string;
    type: string;
    currency: string;
    last_synced_at: string | null;
    connections_count: number;
    accounts_count: number;
    connections: Array<{
        id: number;
        status: string;
        institution_name: string | null;
        last_synced_at: string | null;
        error_message: string | null;
    }>;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Wallets',
        href: '/wallets',
    },
];

function formatDate(value: string | null): string {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleString();
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

export default function WalletsIndex({
    wallets,
}: {
    wallets: WalletSummary[];
}) {
    const page = usePage();
    const currentUrl = page.url;
    const query = currentUrl.includes('?') ? currentUrl.split('?')[1] : '';
    const params = new URLSearchParams(query);
    const requestedType = params.get('type');
    const initialType =
        requestedType === 'cash' ||
        requestedType === 'stock' ||
        requestedType === 'bank'
            ? requestedType
            : 'general';
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        name: '',
        type: initialType,
        currency: 'EUR',
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Wallets" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        title="Wallets"
                        description="Create multiple wallets and attach one or more Plaid bank connections to each wallet."
                    />

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

                        <Dialog
                            open={isCreateOpen}
                            onOpenChange={setIsCreateOpen}
                        >
                            <DialogTrigger asChild>
                                <Button>
                                    <Plus className="size-4" />
                                    Create New Wallet
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Create wallet</DialogTitle>
                                    <DialogDescription>
                                        Wallets group your connected
                                        institutions and transactions.
                                    </DialogDescription>
                                </DialogHeader>

                                <div className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            Wallet name
                                        </Label>
                                        <Input
                                            id="name"
                                            value={data.name}
                                            onChange={(event) =>
                                                setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Main Wallet"
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="type">Type</Label>
                                        <select
                                            id="type"
                                            value={data.type}
                                            onChange={(event) =>
                                                setData(
                                                    'type',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                        >
                                            <option value="general">
                                                General
                                            </option>
                                            <option value="cash">Cash</option>
                                            <option value="stock">Stock</option>
                                            <option value="bank">Bank</option>
                                        </select>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="currency">
                                            Currency
                                        </Label>
                                        <select
                                            id="currency"
                                            value={data.currency}
                                            onChange={(event) =>
                                                setData(
                                                    'currency',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                        >
                                            <option value="EUR">EUR</option>
                                            <option value="USD">USD</option>
                                            <option value="GBP">GBP</option>
                                        </select>
                                    </div>
                                </div>

                                <DialogFooter>
                                    <Button
                                        variant="outline"
                                        type="button"
                                        onClick={() => {
                                            setIsCreateOpen(false);
                                            reset();
                                        }}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        disabled={processing}
                                        onClick={() =>
                                            post('/wallets', {
                                                onSuccess: () => {
                                                    setIsCreateOpen(false);
                                                    reset();
                                                },
                                            })
                                        }
                                    >
                                        Create Wallet
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

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
                                <WalletIcon className="size-5 text-brand" />
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

                            <p className="mt-3 text-xs text-muted-foreground">
                                Last synced: {formatDate(wallet.last_synced_at)}
                            </p>

                            <div className="mt-3 flex flex-wrap gap-1.5">
                                {wallet.connections.length === 0 ? (
                                    <Badge variant="outline">
                                        No bank connections
                                    </Badge>
                                ) : (
                                    wallet.connections
                                        .slice(0, 3)
                                        .map((connection) => (
                                            <Badge
                                                key={connection.id}
                                                variant={statusVariant(
                                                    connection.status,
                                                )}
                                                className="max-w-full truncate"
                                            >
                                                {(connection.institution_name ??
                                                    'Institution') +
                                                    ' · ' +
                                                    connection.status}
                                            </Badge>
                                        ))
                                )}
                            </div>
                        </Link>
                    ))}

                    {wallets.length === 0 ? (
                        <div className="col-span-full rounded-xl border border-dashed border-border bg-card p-8 text-center text-muted-foreground">
                            No wallets yet. Create your first wallet to start
                            connecting banks.
                        </div>
                    ) : null}
                </div>
            </div>
        </AppLayout>
    );
}
