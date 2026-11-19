import { Head, Link } from '@inertiajs/react';
import Loader2 from 'lucide-react/dist/esm/icons/loader-2.js';
import Plus from 'lucide-react/dist/esm/icons/plus.js';
import RefreshCw from 'lucide-react/dist/esm/icons/refresh-cw.js';
import Search from 'lucide-react/dist/esm/icons/search.js';
import SlidersHorizontal from 'lucide-react/dist/esm/icons/sliders-horizontal.js';
import TriangleAlert from 'lucide-react/dist/esm/icons/triangle-alert.js';
import Upload from 'lucide-react/dist/esm/icons/upload.js';
import { useMemo, useState } from 'react';
import { AddTransactionSheet } from '@/components/add-transaction-sheet';
import type {
    AddTransactionOptions,
    CategoryOptionLite,
} from '@/components/add-transaction-sheet';
import BankLogo from '@/components/bank-logo';
import {
    MonthPicker,
    Panel,
    PhoneAddButton,
    useLocale,
} from '@/components/kit';
import type { TagColorOption, TagOption } from '@/components/tag-chip';
import { TransactionEditor } from '@/components/transaction-editor';
import { TransactionLedger } from '@/components/transaction-ledger';
import { Button } from '@/components/ui/button';
import { useSyncAllConnections } from '@/hooks/use-sync-all-connections';
import AppLayout from '@/layouts/app-layout';
import { amountTone, formatDay, formatMonth, formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { BankConnectionStatus, BreadcrumbItem } from '@/types';

type Account = {
    id: number;
    display_name: string | null;
    name: string | null;
    official_name: string | null;
    institution_name: string | null;
    institution_logo: string | null;
    provider: string | null;
    wallet_id?: number | null;
    wallet_name?: string | null;
    /** Every account in this wallet (e.g. EUR and GBP statements), for switching. */
    siblings?: Array<{ id: number; name: string; currency: string | null }>;
    subtype?: string | null;
    is_manual: boolean;
    connection: BankConnectionStatus | null;
    mask: string | null;
    currency: string | null;
    balances: {
        current?: number | null;
        available?: number | null;
        credit_limit?: number | null;
    } | null;
    last_synced_at: string | null;
    last_import?: {
        created_at: string | null;
        date_to: string | null;
        rows_added: number;
        is_stale: boolean;
    } | null;
};

type Transaction = {
    id: number;
    account_id: number;
    name: string | null;
    merchant_name: string | null;
    counterparty: string | null;
    category: string;
    category_id: number | null;
    category_icon: string;
    category_type: 'expense' | 'income' | 'transfer';
    category_source: 'manual' | 'automatic' | 'fallback';
    amount: number;
    currency: string;
    date: string | null;
    pending: boolean;
    tag_ids: number[];
};

type Props = {
    account: Account;
    month: { start: string; end: string };
    categoryOptions: {
        expense: CategoryOptionLite[];
        income: CategoryOptionLite[];
        transfer?: CategoryOptionLite[];
    };
    categoryColors: TagColorOption[];
    tags: TagOption[];
    transactions: Transaction[];
    addTransaction: AddTransactionOptions;
};

const RELOAD = ['transactions', 'tags', 'account'];

function when(iso: string | null, locale: string): string | null {
    if (!iso) {
        return null;
    }

    return new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

/**
 * What a transaction is for the totals and the type filter: a transfer (money moved
 * between your own accounts), spending (including money back, which lowers it) or income.
 */
function kindOf(transaction: Transaction): 'expense' | 'income' | 'transfer' {
    if (transaction.category_type === 'transfer') {
        return 'transfer';
    }

    return transaction.amount < 0 || transaction.category_type === 'expense'
        ? 'expense'
        : 'income';
}

export default function AccountShow({
    account,
    month,
    categoryOptions,
    categoryColors,
    tags,
    transactions,
    addTransaction,
}: Props) {
    const locale = useLocale();
    const [openId, setOpenId] = useState<number | null>(null);
    const [adding, setAdding] = useState(false);
    const [search, setSearch] = useState('');
    const [type, setType] = useState<'all' | 'expense' | 'income' | 'transfer'>(
        'all',
    );
    const { isSyncing, errorMessage, startSyncConnection } =
        useSyncAllConnections({ only: RELOAD });
    const currency = account.currency ?? 'EUR';
    const title =
        account.display_name ??
        account.name ??
        account.official_name ??
        'Account';
    const monthName = formatMonth(month.start, locale, 'long').split(' ')[0];
    const isImport = account.provider === 'import';
    const isAsset = account.provider === 'asset';
    const canSync =
        account.connection !== null &&
        ['enable_banking', 'plaid', 'ibkr'].includes(account.provider ?? '') &&
        account.connection.status !== 'needs_reauth';

    const visible = useMemo(() => {
        const needle = search.trim().toLowerCase();

        return transactions.filter((transaction) => {
            if (type !== 'all' && kindOf(transaction) !== type) {
                return false;
            }

            if (needle === '') {
                return true;
            }

            return [
                transaction.merchant_name,
                transaction.counterparty,
                transaction.name,
                transaction.category,
            ].some((value) => value?.toLowerCase().includes(needle));
        });
    }, [transactions, search, type]);

    const totals = useMemo(
        () =>
            visible.reduce(
                // Transfers are neither; money back counts against spending.
                (sum, transaction) => {
                    const kind = kindOf(transaction);

                    return {
                        in:
                            sum.in +
                            (kind === 'income' ? transaction.amount : 0),
                        out:
                            sum.out +
                            (kind === 'expense' ? transaction.amount : 0),
                    };
                },
                { in: 0, out: 0 },
            ),
        [visible],
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Wallets', href: '/wallets' },
        { title, href: `/accounts/${account.id}` },
    ];
    const siblings = account.siblings ?? [];
    const sameNames =
        new Set(siblings.map((sibling) => sibling.name)).size < siblings.length;

    const status = account.connection?.status;
    const freshness = isImport
        ? account.last_import?.date_to
            ? `Transactions up to ${formatDay(account.last_import.date_to, locale)}`
            : 'No statement imported yet'
        : account.is_manual
          ? 'Cash wallet, kept by hand'
          : when(account.last_synced_at, locale)
            ? `Updated ${when(account.last_synced_at, locale)}`
            : null;

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            phoneAction={
                account.is_manual ? (
                    <PhoneAddButton
                        label="Add transaction"
                        onClick={() => setAdding(true)}
                    />
                ) : undefined
            }
        >
            <Head title={title} />

            <div className="mb-6 flex flex-wrap items-start justify-between gap-x-6 gap-y-4 desk:mb-8">
                <div className="flex min-w-0 items-center gap-4">
                    <BankLogo
                        src={account.institution_logo}
                        name={account.institution_name ?? title}
                        provider={account.provider}
                        className="size-12 rounded-xl"
                    />
                    <div className="min-w-0">
                        <h1 className="truncate text-screen-title font-normal text-foreground tablet:text-page-title">
                            {title}
                        </h1>
                        <p className="mt-1 text-[15px] text-muted-foreground">
                            {[
                                account.institution_name,
                                account.mask
                                    ? `··${account.mask.slice(-4)}`
                                    : null,
                                freshness,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    {canSync && account.connection ? (
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11 rounded-lg px-4 text-[15px]"
                            disabled={isSyncing || status === 'syncing'}
                            onClick={() =>
                                void startSyncConnection(account.connection!.id)
                            }
                        >
                            {isSyncing || status === 'syncing' ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <RefreshCw
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                            )}
                            {isSyncing || status === 'syncing'
                                ? 'Syncing…'
                                : 'Sync now'}
                        </Button>
                    ) : null}
                    {isImport ? (
                        <Button
                            asChild
                            className="h-11 rounded-lg px-5 text-[15px]"
                        >
                            <Link href={`/imports/new?account=${account.id}`}>
                                <Upload className="size-5" strokeWidth={1.75} />
                                Import statement
                            </Link>
                        </Button>
                    ) : null}
                    {account.is_manual ? (
                        <Button
                            type="button"
                            className="hidden h-11 rounded-lg px-5 text-[15px] tablet:inline-flex"
                            onClick={() => setAdding(true)}
                        >
                            <Plus className="size-5" strokeWidth={1.75} />
                            Add transaction
                        </Button>
                    ) : null}
                    {!isAsset && account.wallet_id ? (
                        <Button
                            asChild
                            variant="outline"
                            className="h-11 rounded-lg px-4 text-[15px]"
                        >
                            <Link href={`/wallets/${account.wallet_id}`}>
                                <SlidersHorizontal
                                    className="size-4"
                                    strokeWidth={1.75}
                                />
                                {account.provider === 'ibkr'
                                    ? 'Holdings & settings'
                                    : 'Settings & history'}
                            </Link>
                        </Button>
                    ) : null}
                    {isAsset && account.wallet_id ? (
                        <Button
                            asChild
                            className="h-11 rounded-lg px-5 text-[15px]"
                        >
                            <Link href={`/wallets/${account.wallet_id}`}>
                                {account.subtype === 'pension'
                                    ? 'Update value'
                                    : 'ISA details'}
                            </Link>
                        </Button>
                    ) : null}
                </div>
            </div>

            {siblings.length > 1 ? (
                <nav
                    aria-label={`Accounts in ${account.wallet_name ?? 'this wallet'}`}
                    className="-mt-2 mb-6 flex flex-wrap gap-2"
                >
                    {siblings.map((sibling) => {
                        const current = sibling.id === account.id;

                        return (
                            <Link
                                key={sibling.id}
                                href={`/accounts/${sibling.id}?start_date=${month.start}&end_date=${month.end}`}
                                aria-current={current ? 'page' : undefined}
                                className={cn(
                                    'inline-flex h-10 items-center rounded-full border px-4 text-[15px] transition-colors',
                                    current
                                        ? 'border-transparent bg-accent font-medium text-accent-foreground'
                                        : 'border-border bg-card text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {sameNames || !sibling.currency
                                    ? (sibling.currency ?? sibling.name)
                                    : `${sibling.name} · ${sibling.currency}`}
                            </Link>
                        );
                    })}
                </nav>
            ) : null}

            <div className="space-y-5 desk:space-y-6">
                {status === 'needs_reauth' ||
                status === 'sync_failed' ||
                (isImport && account.last_import?.is_stale) ||
                errorMessage ? (
                    <div
                        role="status"
                        className="flex items-start gap-2 rounded-xl bg-warning-surface px-4 py-3 text-[15px] text-warning"
                    >
                        <TriangleAlert
                            className="mt-0.5 size-4 shrink-0"
                            strokeWidth={1.75}
                        />
                        <span>
                            {errorMessage ??
                                (isImport
                                    ? 'This statement is more than a few weeks old. Import a newer one to keep it current.'
                                    : (account.connection?.error_message ??
                                      'This bank needs you to reconnect it before it can sync again.'))}
                        </span>
                    </div>
                ) : null}

                <Panel>
                    <div className="flex flex-wrap items-end justify-between gap-6">
                        <div>
                            <p className="text-[15px] text-muted-foreground">
                                {account.balances?.credit_limit
                                    ? 'Balance owed'
                                    : 'Current balance'}
                            </p>
                            <p
                                className={cn(
                                    'mt-1 text-[2.75rem] leading-none font-light tracking-[-0.02em] amount',
                                    amountTone(
                                        Number(account.balances?.current ?? 0),
                                        false,
                                    ),
                                )}
                            >
                                {formatMoney(
                                    Number(account.balances?.current ?? 0),
                                    currency,
                                    locale,
                                )}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-x-8 gap-y-2 text-[15px]">
                            {typeof account.balances?.available === 'number' &&
                            !account.is_manual ? (
                                <p className="text-muted-foreground">
                                    Available{' '}
                                    <span className="text-foreground amount">
                                        {formatMoney(
                                            account.balances.available,
                                            currency,
                                            locale,
                                        )}
                                    </span>
                                </p>
                            ) : null}
                            <p className="text-muted-foreground">
                                In {monthName}{' '}
                                <span className="text-positive amount">
                                    {formatMoney(
                                        totals.in,
                                        currency,
                                        locale,
                                        'always',
                                    )}
                                </span>
                            </p>
                            <p className="text-muted-foreground">
                                Out{' '}
                                <span
                                    className={cn(
                                        'amount',
                                        amountTone(totals.out),
                                    )}
                                >
                                    {formatMoney(
                                        totals.out,
                                        currency,
                                        locale,
                                        'always',
                                    )}
                                </span>
                            </p>
                        </div>
                    </div>
                </Panel>

                <div className="flex flex-wrap items-center gap-3">
                    <MonthPicker month={month} />
                    <label className="sr-only" htmlFor="account-type">
                        Type
                    </label>
                    <select
                        id="account-type"
                        value={type}
                        onChange={(event) =>
                            setType(event.target.value as typeof type)
                        }
                        className="h-11 rounded-lg border border-control/70 bg-card px-3 text-[15px] text-foreground"
                    >
                        <option value="all">All types</option>
                        <option value="expense">Expenses</option>
                        <option value="income">Income</option>
                        <option value="transfer">Transfers</option>
                    </select>
                    <div className="flex min-w-[12rem] flex-1 items-center gap-2 rounded-lg border border-control/70 bg-card px-3">
                        <Search
                            className="size-4 shrink-0 text-muted-foreground"
                            strokeWidth={1.75}
                        />
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search payee, description or category"
                            aria-label="Search payee, description or category"
                            className="h-11 w-full bg-transparent text-[15px] outline-none placeholder:text-muted-foreground"
                        />
                    </div>
                </div>

                {visible.length === 0 ? (
                    <div className="rounded-card border border-border bg-card px-6 py-10">
                        <p className="text-lg text-foreground">
                            {transactions.length === 0
                                ? `No transactions in ${monthName}.`
                                : 'Nothing matches this search.'}
                        </p>
                        {transactions.length === 0 && isImport ? (
                            <Link
                                href={`/imports/new?account=${account.id}`}
                                className="mt-1 inline-block text-[15px] font-medium text-positive hover:underline"
                            >
                                Import a statement ›
                            </Link>
                        ) : null}
                    </div>
                ) : (
                    <TransactionLedger
                        transactions={visible}
                        openId={openId}
                        onToggle={setOpenId}
                        tags={tags}
                        categoryColors={categoryColors}
                        renderEditor={(transaction) => (
                            <TransactionEditor
                                transaction={transaction}
                                categories={categoryOptions}
                                tags={tags}
                                colors={categoryColors}
                                reload={RELOAD}
                                from="account"
                                onClose={() => setOpenId(null)}
                            />
                        )}
                    />
                )}
            </div>

            {account.is_manual ? (
                <AddTransactionSheet
                    open={adding}
                    onOpenChange={setAdding}
                    options={addTransaction}
                    from="account"
                />
            ) : null}
        </AppLayout>
    );
}
