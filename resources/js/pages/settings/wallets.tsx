import { Head, router } from '@inertiajs/react';
import { Landmark, RefreshCw, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import AddNewWalletMenu from '@/components/add-new-wallet-menu';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePlaidAccountLinkedRefresh } from '@/hooks/use-plaid-account-linked-refresh';
import { useSyncAllConnections } from '@/hooks/use-sync-all-connections';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { openCenteredPopup } from '@/lib/popup';
import type { BreadcrumbItem } from '@/types';

type Account = {
    id: number;
    display_name: string | null;
    name: string | null;
    official_name: string | null;
    institution_name: string | null;
    wallet_name: string | null;
    mask: string | null;
    currency: string | null;
};

type Props = {
    accounts: Account[];
    base_currency: {
        selected: string;
        options: Array<{
            code: string;
            label: string;
        }>;
    };
    number_locale: {
        selected: string;
        options: Array<{
            code: string;
            label: string;
            example: string;
        }>;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Wallet settings',
        href: '/settings/wallets',
    },
];

function accountTitle(account: Account): string {
    return (
        account.display_name ??
        account.name ??
        account.official_name ??
        'Linked Account'
    );
}

export default function WalletSettings({
    accounts,
    base_currency,
    number_locale,
}: Props) {
    const [values, setValues] = useState<Record<number, string>>(() =>
        accounts.reduce<Record<number, string>>((carry, account) => {
            carry[account.id] = account.display_name ?? '';

            return carry;
        }, {}),
    );
    const [savingId, setSavingId] = useState<number | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [baseCurrency, setBaseCurrency] = useState<string>(
        base_currency.selected,
    );
    const [isSavingBaseCurrency, setIsSavingBaseCurrency] = useState(false);
    const [showBaseCurrencySaved, setShowBaseCurrencySaved] = useState(false);
    const [numberLocale, setNumberLocale] = useState<string>(
        number_locale.selected,
    );
    const [isSavingNumberLocale, setIsSavingNumberLocale] = useState(false);
    const [showNumberLocaleSaved, setShowNumberLocaleSaved] = useState(false);

    usePlaidAccountLinkedRefresh({ only: ['accounts'] });

    const {
        isSyncing,
        successMessage: syncSuccessMessage,
        errorMessage: syncErrorMessage,
        startSyncAll,
        clearSuccessMessage,
        clearErrorMessage,
    } = useSyncAllConnections({ only: ['accounts'] });

    const hasAccounts = useMemo(() => accounts.length > 0, [accounts.length]);

    const handleSave = (accountId: number): void => {
        if (savingId !== null) {
            return;
        }

        setSavingId(accountId);

        router.patch(
            `/settings/wallets/accounts/${accountId}`,
            {
                display_name: values[accountId] ?? '',
            },
            {
                preserveScroll: true,
                onFinish: () => setSavingId(null),
            },
        );
    };

    const handleDelete = (accountId: number, label: string): void => {
        if (deletingId !== null) {
            return;
        }

        const confirmed = window.confirm(
            `Delete ${label} and all of its transactions permanently?`,
        );

        if (!confirmed) {
            return;
        }

        setDeletingId(accountId);

        router.delete(`/settings/wallets/accounts/${accountId}`, {
            preserveScroll: true,
            onFinish: () => setDeletingId(null),
        });
    };

    const handleAddNewWallet = (): void => {
        const popup = openCenteredPopup(
            '/wallets/add-account',
            'plaid-add-account',
            {
                width: 520,
                height: 760,
            },
        );

        if (!popup) {
            window.alert('Popup was blocked. Please allow popups and try again.');

            return;
        }

        popup.focus();
    };

    const handleBaseCurrencyChange = (nextBaseCurrency: string): void => {
        if (isSavingBaseCurrency) {
            return;
        }

        setBaseCurrency(nextBaseCurrency);
        setIsSavingBaseCurrency(true);

        router.patch(
            '/settings/wallets/base-currency',
            { base_currency: nextBaseCurrency },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShowBaseCurrencySaved(true);
                    window.setTimeout(() => {
                        setShowBaseCurrencySaved(false);
                    }, 2500);
                },
                onError: () => {
                    setBaseCurrency(base_currency.selected);
                    setShowBaseCurrencySaved(false);
                },
                onFinish: () => {
                    setIsSavingBaseCurrency(false);
                },
            },
        );
    };

    const handleNumberLocaleChange = (nextNumberLocale: string): void => {
        if (isSavingNumberLocale) {
            return;
        }

        setNumberLocale(nextNumberLocale);
        setIsSavingNumberLocale(true);

        router.patch(
            '/settings/wallets/number-locale',
            { number_locale: nextNumberLocale },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShowNumberLocaleSaved(true);
                    window.setTimeout(() => {
                        setShowNumberLocaleSaved(false);
                    }, 2500);
                },
                onError: () => {
                    setNumberLocale(number_locale.selected);
                    setShowNumberLocaleSaved(false);
                },
                onFinish: () => {
                    setIsSavingNumberLocale(false);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Wallet settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <Heading
                            variant="small"
                            title="Bank accounts"
                            description="Set custom names for dashboard cards or delete linked accounts completely."
                        />

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
                                onConnectBank={handleAddNewWallet}
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div className="rounded-lg border border-border bg-card/70 p-4">
                            <p className="text-sm font-semibold text-foreground">
                                Dashboard base currency
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Dashboard totals and account amounts will use
                                this currency as the converted display value.
                            </p>

                            <div className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                                <select
                                    value={baseCurrency}
                                    onChange={(event) =>
                                        handleBaseCurrencyChange(
                                            event.target.value,
                                        )
                                    }
                                    disabled={isSavingBaseCurrency}
                                    className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    {base_currency.options.map((currencyOption) => (
                                        <option
                                            key={currencyOption.code}
                                            value={currencyOption.code}
                                        >
                                            {currencyOption.label}
                                        </option>
                                    ))}
                                </select>

                                <span className="text-xs text-muted-foreground">
                                    {isSavingBaseCurrency
                                        ? 'Saving base currency...'
                                        : showBaseCurrencySaved
                                          ? 'Base currency saved'
                                          : 'Saved automatically'}
                                </span>
                            </div>
                        </div>

                        <div className="rounded-lg border border-border bg-card/70 p-4">
                            <p className="text-sm font-semibold text-foreground">
                                Number localization
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Choose your preferred number format style for
                                dashboard amounts.
                            </p>

                            <div className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                                <select
                                    value={numberLocale}
                                    onChange={(event) =>
                                        handleNumberLocaleChange(
                                            event.target.value,
                                        )
                                    }
                                    disabled={isSavingNumberLocale}
                                    className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    {number_locale.options.map((localeOption) => (
                                        <option
                                            key={localeOption.code}
                                            value={localeOption.code}
                                        >
                                            {localeOption.label} ({localeOption.example})
                                        </option>
                                    ))}
                                </select>

                                <span className="text-xs text-muted-foreground">
                                    {isSavingNumberLocale
                                        ? 'Saving number localization...'
                                        : showNumberLocaleSaved
                                          ? 'Number localization saved'
                                          : 'Saved automatically'}
                                </span>
                            </div>
                        </div>
                    </div>

                    {!hasAccounts ? (
                        <div className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                            No linked bank accounts yet.
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {syncErrorMessage ? (
                                <div className="flex items-center justify-between gap-2 rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
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
                                <div className="flex items-center justify-between gap-2 rounded-lg border border-emerald-300/60 bg-emerald-100/60 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-950/40 dark:text-emerald-300">
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

                            {accounts.map((account) => {
                                const title = accountTitle(account);
                                const isSaving = savingId === account.id;
                                const isDeleting = deletingId === account.id;

                                return (
                                    <div
                                        key={account.id}
                                        className="space-y-3 rounded-lg border border-border bg-card/70 p-4"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-semibold text-foreground">
                                                    {title}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    <Landmark className="mr-1 inline size-3.5" />
                                                    {account.institution_name ??
                                                        'Institution'}
                                                    {account.mask
                                                        ? ` • **** ${account.mask}`
                                                        : ''}
                                                    {account.wallet_name
                                                        ? ` • Wallet: ${account.wallet_name}`
                                                        : ''}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="grid gap-2 sm:grid-cols-[1fr_auto_auto] sm:items-center">
                                            <Input
                                                value={values[account.id] ?? ''}
                                                onChange={(event) =>
                                                    setValues((current) => ({
                                                        ...current,
                                                        [account.id]:
                                                            event.target.value,
                                                    }))
                                                }
                                                placeholder={
                                                    account.name ??
                                                    account.official_name ??
                                                    'Enter custom name'
                                                }
                                                maxLength={120}
                                            />

                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={isSaving || isDeleting}
                                                onClick={() =>
                                                    handleSave(account.id)
                                                }
                                            >
                                                {isSaving
                                                    ? 'Saving...'
                                                    : 'Save name'}
                                            </Button>

                                            <Button
                                                type="button"
                                                variant="destructive"
                                                disabled={isSaving || isDeleting}
                                                onClick={() =>
                                                    handleDelete(
                                                        account.id,
                                                        title,
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                                {isDeleting
                                                    ? 'Deleting...'
                                                    : 'Delete'}
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
