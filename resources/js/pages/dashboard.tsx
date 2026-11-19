import { Head, Link } from '@inertiajs/react';
import ChevronRight from 'lucide-react/dist/esm/icons/chevron-right.js';
import Loader2 from 'lucide-react/dist/esm/icons/loader-2.js';
import Plus from 'lucide-react/dist/esm/icons/plus.js';
import RefreshCw from 'lucide-react/dist/esm/icons/refresh-cw.js';
import TriangleAlert from 'lucide-react/dist/esm/icons/triangle-alert.js';
import { useCallback, useState } from 'react';
import AddNewWalletMenu from '@/components/add-new-wallet-menu';
import { AddTransactionSheet } from '@/components/add-transaction-sheet';
import type { AddTransactionOptions } from '@/components/add-transaction-sheet';
import BankAlertsBanner from '@/components/bank-alerts-banner';
import BankLogo from '@/components/bank-logo';
import {
    BudgetRing,
    IconTile,
    HeroFigure,
    categoryColor,
    MonthPicker,
    PageHeader,
    PhoneAddButton,
    Panel,
    StackedBar,
    useLocale,
} from '@/components/kit';
import { Button } from '@/components/ui/button';
import { useBankAccountLinkedRefresh } from '@/hooks/use-bank-account-linked-refresh';
import { useSyncAllConnections } from '@/hooks/use-sync-all-connections';
import AppLayout from '@/layouts/app-layout';
import { openBankPopup } from '@/lib/bank-popup';
import { amountTone, formatDay, formatMonth, formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

type Month = { start: string; end: string };

type BudgetLine = {
    id: number;
    amount: number;
    spent: number;
    left: number;
    percent: number;
};

type CategorySpend = {
    id: number | null;
    name: string;
    icon: string;
    color?: string | null;
    amount: number;
    other?: boolean;
};

type RecentTransaction = {
    id: number;
    account_id: number;
    account: string | null;
    name: string | null;
    merchant_name: string | null;
    counterparty: string | null;
    category: string;
    category_icon: string;
    category_color?: string | null;
    category_type: 'expense' | 'income' | 'transfer';
    amount: number;
    currency: string;
    date: string | null;
    pending: boolean;
};

type WalletRow = {
    id: number;
    wallet_id: number | null;
    name: string;
    institution: string | null;
    logo: string | null;
    provider: string | null;
    status: string | null;
    mask: string | null;
    currency: string;
    balance: number;
    last_synced_at: string | null;
};

type Props = {
    month: Month;
    baseCurrency: string;
    budget: BudgetLine | null;
    spent: number;
    income: number;
    categories: CategorySpend[];
    overBudget: Array<{ name: string; category_id: number; over: number }>;
    recent: RecentTransaction[];
    wallets: WalletRow[];
    netWorth: {
        by_currency: Array<{ currency: string; amount: number }>;
        total: number;
    };
    addTransaction: AddTransactionOptions;
};

const REFRESH_PROPS = [
    'budget',
    'spent',
    'income',
    'categories',
    'overBudget',
    'recent',
    'wallets',
    'netWorth',
    'bankAlerts',
];

function leaf(path: string): string {
    const parts = path.split(' / ');

    return parts[parts.length - 1] ?? path;
}

function activityHref(
    month: Month,
    extra: Record<string, string | number> = {},
): string {
    const query = new URLSearchParams({
        start_date: month.start,
        end_date: month.end,
        ...Object.fromEntries(
            Object.entries(extra).map(([key, value]) => [key, String(value)]),
        ),
    });

    return `/activity?${query.toString()}`;
}

function payee(transaction: RecentTransaction): string {
    return (
        transaction.merchant_name ??
        transaction.counterparty ??
        transaction.name ??
        'Transaction'
    );
}

/** Where a wallet's numbers come from and when: the source may truncate, the date never. */
function freshness(
    wallet: WalletRow,
    locale: string,
): { source: string | null; verb: string | null; when: string | null } {
    const status = {
        needs_reauth: 'Reconnect needed',
        syncing: 'Syncing…',
        sync_failed: 'Last sync failed',
    }[wallet.status ?? ''];

    if (status) {
        return { source: status, verb: null, when: null };
    }

    if (!wallet.last_synced_at) {
        return { source: wallet.institution ?? null, verb: null, when: null };
    }

    const date = new Date(wallet.last_synced_at);
    const sameDay = date.toDateString() === new Date().toDateString();
    const when = sameDay
        ? new Intl.DateTimeFormat(locale, {
              hour: '2-digit',
              minute: '2-digit',
          }).format(date)
        : new Intl.DateTimeFormat(locale, {
              day: 'numeric',
              month: 'short',
          }).format(date);

    return {
        source: wallet.institution ?? null,
        verb: wallet.provider === 'import' ? 'imported' : 'updated',
        when,
    };
}

function BudgetHero({
    month,
    budget,
    spent,
    currency,
}: {
    month: Month;
    budget: BudgetLine | null;
    spent: number;
    currency: string;
}) {
    const locale = useLocale();
    const monthName = formatMonth(month.start, locale, 'long').split(' ')[0];

    if (budget === null) {
        return (
            <section className="flex flex-col rounded-card bg-hero p-5 text-hero-foreground tablet:p-8">
                <h2 className="hidden text-section font-medium tablet:block">
                    Monthly budget
                </h2>
                <div className="@container tablet:mt-6">
                    <p className="text-lg text-hero-muted">Spent so far</p>
                    <HeroFigure value={formatMoney(spent, currency, locale)} />
                </div>
                <div className="mt-auto border-t border-hero-foreground/15 pt-5">
                    <p className="text-[15px] text-hero-muted">
                        No budget for {monthName} yet.
                    </p>
                    <Link
                        href={`/budgets?start_date=${month.start}&end_date=${month.end}`}
                        className="mt-1 inline-flex items-center gap-0.5 text-[15px] font-medium text-positive hover:underline"
                    >
                        Set a monthly budget
                        <ChevronRight className="size-4" strokeWidth={1.75} />
                    </Link>
                </div>
            </section>
        );
    }

    const over = budget.left < 0;

    return (
        <section className="flex flex-col rounded-card bg-hero p-5 text-hero-foreground tablet:p-8">
            <h2 className="hidden text-section font-medium tablet:block">
                Monthly budget
            </h2>
            <div className="flex items-center justify-between gap-4 tablet:mt-6">
                <div className="@container min-w-0 flex-1">
                    <p className="text-lg text-hero-muted">
                        {over ? 'Over budget by' : 'Left to spend'}
                    </p>
                    <HeroFigure
                        value={formatMoney(
                            Math.abs(budget.left),
                            currency,
                            locale,
                        )}
                        className={over ? 'text-destructive' : undefined}
                    />
                </div>
                <BudgetRing
                    percent={budget.percent}
                    size={132}
                    className="size-20 tablet:size-[132px]"
                />
            </div>
            <div className="mt-5 grid grid-cols-2 gap-6 border-t border-hero-foreground/15 pt-4 tablet:mt-7 tablet:pt-5">
                <div>
                    <p className="text-[15px] text-hero-muted">Spent so far</p>
                    <p className="mt-1 text-[1.375rem] leading-tight font-light amount tablet:text-[1.75rem]">
                        {formatMoney(budget.spent, currency, locale)}
                    </p>
                </div>
                <div>
                    <p className="text-[15px] text-hero-muted">Budget</p>
                    <p className="mt-1 text-[1.375rem] leading-tight font-light amount tablet:text-[1.75rem]">
                        {formatMoney(budget.amount, currency, locale)}
                    </p>
                </div>
            </div>
        </section>
    );
}

function CategorySpending({
    month,
    categories,
    currency,
}: {
    month: Month;
    categories: CategorySpend[];
    currency: string;
}) {
    const locale = useLocale();
    const monthName = formatMonth(month.start, locale, 'long').split(' ')[0];

    return (
        <Panel
            title="Spending by category"
            action={
                categories.length > 0 ? (
                    <Link
                        href={activityHref(month, { type: 'expense' })}
                        className="inline-flex items-center gap-0.5 text-[15px] font-medium text-positive hover:underline"
                    >
                        Details
                        <ChevronRight className="size-4" strokeWidth={1.75} />
                    </Link>
                ) : null
            }
            className="flex flex-col"
        >
            {categories.length === 0 ? (
                <p className="text-base text-muted-foreground">
                    No expenses in {monthName} yet.
                </p>
            ) : (
                <>
                    <StackedBar
                        parts={categories.map((category, index) => ({
                            key: `${category.id ?? 'x'}-${category.name}`,
                            amount: category.amount,
                            color: categoryColor(
                                category.color,
                                index,
                                category.other,
                            ),
                            label: `${category.name} ${formatMoney(category.amount, currency, locale)}`,
                        }))}
                    />
                    <ul className="mt-5 divide-y divide-border">
                        {categories.map((category, index) => {
                            const row = (
                                <>
                                    <span className="flex min-w-0 items-center gap-3">
                                        <IconTile
                                            icon={category.icon}
                                            tint={categoryColor(
                                                category.color,
                                                index,
                                                category.other,
                                            )}
                                            size="sm"
                                        />
                                        <span className="truncate">
                                            {category.name}
                                        </span>
                                    </span>
                                    <span className="amount">
                                        {formatMoney(
                                            category.amount,
                                            currency,
                                            locale,
                                        )}
                                    </span>
                                </>
                            );

                            return (
                                <li
                                    key={`${category.id ?? 'x'}-${category.name}`}
                                >
                                    {category.id !== null ? (
                                        <Link
                                            href={activityHref(month, {
                                                type: 'expense',
                                                category: category.id,
                                            })}
                                            className="-mx-2 flex min-h-11 items-center justify-between gap-4 rounded-lg px-2 text-base text-foreground hover:bg-muted"
                                        >
                                            {row}
                                        </Link>
                                    ) : (
                                        <div className="flex min-h-11 items-center justify-between gap-4 text-base text-foreground">
                                            {row}
                                        </div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </>
            )}
        </Panel>
    );
}

function RecentActivity({
    month,
    transactions,
}: {
    month: Month;
    transactions: RecentTransaction[];
}) {
    const locale = useLocale();
    const monthName = formatMonth(month.start, locale, 'long').split(' ')[0];

    return (
        <Panel
            title="Recent activity"
            action={
                <Link
                    href={activityHref(month)}
                    className="inline-flex items-center gap-0.5 text-[15px] font-medium text-positive hover:underline"
                >
                    View all activity
                    <ChevronRight className="size-4" strokeWidth={1.75} />
                </Link>
            }
        >
            {transactions.length === 0 ? (
                <p className="text-base text-muted-foreground">
                    Nothing in {monthName} yet.
                </p>
            ) : (
                <div className="-mx-1">
                    <div className="hidden grid-cols-[minmax(0,2.2fr)_minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)] gap-4 border-b border-border px-1 pb-3 text-xs font-medium tracking-[0.12em] text-muted-foreground uppercase tablet:grid">
                        <span>Transaction</span>
                        <span>Category</span>
                        <span>Date</span>
                        <span className="text-right">Amount</span>
                    </div>
                    <ul className="divide-y divide-border">
                        {transactions.map((transaction) => {
                            const income = transaction.amount > 0;
                            const paidBack =
                                income &&
                                transaction.category_type === 'expense';

                            return (
                                <li key={transaction.id}>
                                    <Link
                                        href={activityHref(month, {
                                            focus: transaction.id,
                                        })}
                                        className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 rounded-lg px-1 py-3 hover:bg-muted/60 tablet:grid-cols-[minmax(0,2.2fr)_minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)]"
                                    >
                                        <span className="flex min-w-0 items-center gap-4 tablet:col-auto">
                                            <IconTile
                                                icon={transaction.category_icon}
                                                tint={
                                                    transaction.category_color
                                                }
                                                income={income}
                                            />
                                            <span className="min-w-0">
                                                <span className="block truncate text-base font-medium text-foreground">
                                                    {payee(transaction)}
                                                </span>
                                                <span className="block truncate text-sm text-muted-foreground tablet:hidden">
                                                    {leaf(transaction.category)}
                                                    {paidBack
                                                        ? ' · paid back'
                                                        : ''}
                                                    {transaction.pending
                                                        ? ' · pending'
                                                        : ''}
                                                </span>
                                            </span>
                                        </span>
                                        <span className="hidden truncate text-base text-muted-foreground tablet:block">
                                            {leaf(transaction.category)}
                                            {paidBack ? ' · paid back' : ''}
                                            {transaction.pending
                                                ? ' · pending'
                                                : ''}
                                        </span>
                                        <span className="hidden text-base text-muted-foreground tablet:block">
                                            {transaction.date
                                                ? formatDay(
                                                      transaction.date,
                                                      locale,
                                                  )
                                                : ''}
                                        </span>
                                        <span
                                            className={cn(
                                                'text-right text-base font-medium amount',
                                                transaction.category_type ===
                                                    'transfer'
                                                    ? 'text-foreground'
                                                    : amountTone(
                                                          transaction.amount,
                                                      ),
                                            )}
                                        >
                                            {formatMoney(
                                                transaction.amount,
                                                transaction.currency,
                                                locale,
                                                'always',
                                            )}
                                        </span>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}
        </Panel>
    );
}

function Wallets({
    wallets,
    netWorth,
    baseCurrency,
}: {
    wallets: WalletRow[];
    netWorth: Props['netWorth'];
    baseCurrency: string;
}) {
    const locale = useLocale();
    const [popupBlocked, setPopupBlocked] = useState(false);
    const { isSyncing, successMessage, errorMessage, startSyncAll } =
        useSyncAllConnections({ only: REFRESH_PROPS });
    const { isRefreshing } = useBankAccountLinkedRefresh({
        only: REFRESH_PROPS,
    });
    const connectBank = useCallback((): void => {
        setPopupBlocked(!openBankPopup());
    }, []);
    const multiCurrency = netWorth.by_currency.length > 1;

    return (
        <Panel
            title="Wallets"
            action={
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        className="h-11 rounded-lg border-control/70 px-3.5 text-[15px]"
                        onClick={() => void startSyncAll()}
                        disabled={isSyncing}
                    >
                        {isSyncing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <RefreshCw className="size-4" strokeWidth={1.75} />
                        )}
                        <span className="hidden sm:inline">
                            {isSyncing ? 'Syncing…' : 'Sync all'}
                        </span>
                    </Button>
                    <AddNewWalletMenu onConnectBank={connectBank} />
                </div>
            }
        >
            <div className="mb-5 flex flex-wrap items-baseline gap-x-6 gap-y-1">
                {netWorth.by_currency.map((row) => (
                    <p
                        key={row.currency}
                        className={cn(
                            'text-[1.75rem] leading-tight font-light amount',
                            amountTone(row.amount, false),
                        )}
                    >
                        {formatMoney(row.amount, row.currency, locale)}
                    </p>
                ))}
                {multiCurrency ? (
                    <p className="text-[15px] text-muted-foreground">
                        about{' '}
                        {formatMoney(netWorth.total, baseCurrency, locale)} in
                        total
                    </p>
                ) : null}
            </div>

            {successMessage || errorMessage || popupBlocked || isRefreshing ? (
                <p
                    role="status"
                    className={cn(
                        'mb-4 rounded-lg px-3 py-2 text-[15px]',
                        errorMessage || popupBlocked
                            ? 'bg-error-surface text-destructive'
                            : 'bg-hero text-hero-foreground',
                    )}
                >
                    {popupBlocked
                        ? 'Your browser blocked the bank window. Allow pop-ups for this site and try again.'
                        : (errorMessage ??
                          successMessage ??
                          'Loading your new account…')}
                </p>
            ) : null}

            {wallets.length === 0 ? (
                <p className="text-base text-muted-foreground">
                    No wallets yet. Connect a bank, import a statement or add a
                    cash wallet.
                </p>
            ) : (
                <ul className="grid grid-cols-[minmax(0,1fr)] gap-x-8 desk:grid-cols-[repeat(2,minmax(0,1fr))]">
                    {wallets.map((wallet) => {
                        const meta = freshness(wallet, locale);

                        return (
                            <li
                                key={wallet.id}
                                className="border-b border-border last:border-b-0 desk:[&:nth-last-child(2):nth-child(odd)]:border-b-0"
                            >
                                <Link
                                    href={`/accounts/${wallet.id}`}
                                    className="-mx-2 flex min-h-16 items-center gap-4 rounded-lg px-2 py-3 hover:bg-muted/60"
                                >
                                    <BankLogo
                                        src={wallet.logo}
                                        name={wallet.institution ?? wallet.name}
                                        provider={wallet.provider}
                                        className="size-10 rounded-xl"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-base font-medium text-foreground">
                                            {wallet.name}
                                            {wallet.mask ? (
                                                <span className="font-normal text-muted-foreground">
                                                    {' '}
                                                    ··{wallet.mask.slice(-4)}
                                                </span>
                                            ) : null}
                                        </span>
                                        <span
                                            className={cn(
                                                'flex min-w-0 items-center gap-1 text-sm',
                                                wallet.status ===
                                                    'needs_reauth' ||
                                                    wallet.status ===
                                                        'sync_failed'
                                                    ? 'text-warning'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {wallet.status === 'needs_reauth' ||
                                            wallet.status === 'sync_failed' ? (
                                                <TriangleAlert
                                                    className="size-3.5 shrink-0"
                                                    strokeWidth={1.75}
                                                />
                                            ) : null}
                                            {meta.source ? (
                                                <span className="min-w-0 truncate">
                                                    {meta.source}
                                                </span>
                                            ) : null}
                                            {meta.when ? (
                                                <span className="shrink-0 whitespace-nowrap">
                                                    {meta.source ? '· ' : ''}
                                                    <span className="hidden tablet:inline">
                                                        {meta.verb}{' '}
                                                    </span>
                                                    {meta.when}
                                                </span>
                                            ) : null}
                                        </span>
                                    </span>
                                    <span
                                        className={cn(
                                            'text-right text-base font-medium amount',
                                            amountTone(wallet.balance, false),
                                        )}
                                    >
                                        {formatMoney(
                                            wallet.balance,
                                            wallet.currency,
                                            locale,
                                        )}
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Panel>
    );
}

export default function Dashboard(props: Props) {
    const locale = useLocale();
    const [adding, setAdding] = useState(false);
    const { month, baseCurrency } = props;
    const monthName = formatMonth(month.start, locale, 'long').split(' ')[0];

    return (
        <AppLayout
            phoneAction={
                <PhoneAddButton
                    label="Add transaction"
                    onClick={() => setAdding(true)}
                />
            }
        >
            <Head title="Overview" />

            <PageHeader
                title="Overview"
                actions={
                    <>
                        <MonthPicker month={month} quietOnPhone />
                        <Button
                            type="button"
                            onClick={() => setAdding(true)}
                            className="hidden h-11 rounded-lg px-5 text-[15px] font-medium tablet:inline-flex"
                        >
                            <Plus className="size-5" strokeWidth={1.75} />
                            Add transaction
                        </Button>
                    </>
                }
            />

            <div className="space-y-5 desk:space-y-6">
                <BankAlertsBanner />

                {props.overBudget.map((line) => (
                    <div
                        key={line.category_id}
                        role="status"
                        className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-xl bg-warning-surface px-4 py-3 text-[15px] text-warning"
                    >
                        <span className="flex items-center gap-2">
                            <TriangleAlert
                                className="size-4 shrink-0"
                                strokeWidth={1.75}
                            />
                            {line.name} is{' '}
                            {formatMoney(line.over, baseCurrency, locale)} over
                            its {monthName} budget.
                        </span>
                        <Link
                            href={activityHref(month, {
                                type: 'expense',
                                category: line.category_id,
                            })}
                            className="inline-flex items-center gap-0.5 font-medium hover:underline"
                        >
                            Review {line.name.toLowerCase()} expenses
                            <ChevronRight
                                className="size-4"
                                strokeWidth={1.75}
                            />
                        </Link>
                    </div>
                ))}

                <div className="grid grid-cols-[minmax(0,1fr)] gap-5 desk:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] desk:gap-6">
                    <BudgetHero
                        month={month}
                        budget={props.budget}
                        spent={props.spent}
                        currency={baseCurrency}
                    />
                    <CategorySpending
                        month={month}
                        categories={props.categories}
                        currency={baseCurrency}
                    />
                </div>

                <RecentActivity month={month} transactions={props.recent} />

                <Wallets
                    wallets={props.wallets}
                    netWorth={props.netWorth}
                    baseCurrency={baseCurrency}
                />
            </div>

            <AddTransactionSheet
                open={adding}
                onOpenChange={setAdding}
                options={props.addTransaction}
                from="overview"
            />
        </AppLayout>
    );
}
