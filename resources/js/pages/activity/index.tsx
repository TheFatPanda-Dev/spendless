import { Head, router } from '@inertiajs/react';
import Plus from 'lucide-react/dist/esm/icons/plus.js';
import Search from 'lucide-react/dist/esm/icons/search.js';
import X from 'lucide-react/dist/esm/icons/x.js';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { AddTransactionSheet } from '@/components/add-transaction-sheet';
import type {
    AddTransactionOptions,
    CategoryOptionLite,
} from '@/components/add-transaction-sheet';
import { IconTile, MonthPicker, PageHeader, useLocale } from '@/components/kit';
import { TagChip } from '@/components/tag-chip';
import type { TagColorOption, TagOption } from '@/components/tag-chip';
import { TransactionEditor } from '@/components/transaction-editor';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatMonth, formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

type Month = { start: string; end: string };

type Transaction = {
    id: number;
    account_id: number;
    account: string | null;
    name: string | null;
    merchant_name: string | null;
    counterparty: string | null;
    category: string;
    category_id: number | null;
    category_icon: string;
    category_type: 'expense' | 'income';
    category_source: 'manual' | 'automatic' | 'fallback';
    amount: number;
    currency: string;
    date: string | null;
    pending: boolean;
    tag_ids: number[];
};

type Filters = {
    type: 'all' | 'expense' | 'income';
    account: number | null;
    category: number | null;
    tag: number | null;
    q: string;
};

type Props = {
    month: Month;
    filters: Filters;
    transactions: Transaction[];
    truncated: boolean;
    totals: Array<{ currency: string; out: number; in: number; count: number }>;
    accounts: Array<{ id: number; name: string; currency: string }>;
    categoryOptions: {
        expense: CategoryOptionLite[];
        income: CategoryOptionLite[];
    };
    categoryColors: TagColorOption[];
    tags: TagOption[];
    addTransaction: AddTransactionOptions;
};

const RELOAD = ['transactions', 'totals', 'tags', 'truncated'];

const selectClasses =
    'h-11 rounded-lg border border-control/70 bg-card px-3 text-[15px] text-foreground';

function leaf(path: string): string {
    const parts = path.split(' / ');

    return parts[parts.length - 1] ?? path;
}

function payee(transaction: Transaction): string {
    return (
        transaction.merchant_name ??
        transaction.counterparty ??
        transaction.name ??
        'Transaction'
    );
}

function dayLabel(date: string, locale: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Intl.DateTimeFormat(locale, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, month - 1, day)));
}

function focusFromUrl(): number | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const value = Number(
        new URLSearchParams(window.location.search).get('focus'),
    );

    return Number.isFinite(value) && value > 0 ? value : null;
}

export default function ActivityIndex(props: Props) {
    const {
        month,
        filters,
        transactions,
        totals,
        accounts,
        categoryOptions,
        categoryColors,
        tags,
    } = props;
    const locale = useLocale();
    const [openId, setOpenId] = useState<number | null>(focusFromUrl);
    const [adding, setAdding] = useState(false);
    const [search, setSearch] = useState(filters.q);
    const openRowRef = useRef<HTMLLIElement | null>(null);
    const tagsById = useMemo(
        () => new Map(tags.map((tag) => [tag.id, tag])),
        [tags],
    );
    const colorMap = useMemo(
        () => new Map(categoryColors.map((color) => [color.value, color])),
        [categoryColors],
    );
    const allCategories = useMemo(
        () => [...categoryOptions.expense, ...categoryOptions.income],
        [categoryOptions],
    );

    const groups = useMemo(() => {
        const byDay = new Map<string, Transaction[]>();

        for (const transaction of transactions) {
            const key = transaction.date ?? 'Undated';
            byDay.set(key, [...(byDay.get(key) ?? []), transaction]);
        }

        return [...byDay.entries()];
    }, [transactions]);

    useEffect(() => {
        openRowRef.current?.scrollIntoView({ block: 'center' });
        // Only when arriving from a link that names a transaction.
    }, []);

    const query = (
        overrides: Partial<Filters> = {},
    ): Record<string, string | number> => {
        const merged = { ...filters, ...overrides };

        return Object.fromEntries(
            Object.entries({
                type: merged.type === 'all' ? '' : merged.type,
                account: merged.account ?? '',
                category: merged.category ?? '',
                tag: merged.tag ?? '',
                q: merged.q,
            }).filter(([, value]) => value !== '' && value !== null),
        ) as Record<string, string | number>;
    };

    const apply = (overrides: Partial<Filters>): void => {
        setOpenId(null);
        router.get(
            '/activity',
            {
                ...query(overrides),
                start_date: month.start,
                end_date: month.end,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent): void => {
        event.preventDefault();
        apply({ q: search.trim() });
    };

    const filtered =
        filters.type !== 'all' ||
        filters.account !== null ||
        filters.category !== null ||
        filters.tag !== null ||
        filters.q !== '';
    const monthName = formatMonth(month.start, locale, 'long').split(' ')[0];

    return (
        <AppLayout>
            <Head title="Activity" />

            <PageHeader
                title="Activity"
                actions={
                    <Button
                        type="button"
                        onClick={() => setAdding(true)}
                        className="h-11 rounded-lg px-5 text-[15px] font-medium"
                    >
                        <Plus className="size-5" strokeWidth={1.75} />
                        Add transaction
                    </Button>
                }
            />

            <div className="mb-6 flex flex-wrap items-center gap-3">
                <MonthPicker month={month} query={query()} />
                <label className="sr-only" htmlFor="filter-type">
                    Type
                </label>
                <select
                    id="filter-type"
                    value={filters.type}
                    onChange={(event) =>
                        apply({ type: event.target.value as Filters['type'] })
                    }
                    className={selectClasses}
                >
                    <option value="all">All types</option>
                    <option value="expense">Expenses</option>
                    <option value="income">Income</option>
                </select>
                <label className="sr-only" htmlFor="filter-wallet">
                    Wallet
                </label>
                <select
                    id="filter-wallet"
                    value={filters.account ?? ''}
                    onChange={(event) =>
                        apply({
                            account:
                                event.target.value === ''
                                    ? null
                                    : Number(event.target.value),
                        })
                    }
                    className={selectClasses}
                >
                    <option value="">All wallets</option>
                    {accounts.map((account) => (
                        <option key={account.id} value={account.id}>
                            {account.name}
                        </option>
                    ))}
                </select>
                <label className="sr-only" htmlFor="filter-category">
                    Category
                </label>
                <select
                    id="filter-category"
                    value={filters.category ?? ''}
                    onChange={(event) =>
                        apply({
                            category:
                                event.target.value === ''
                                    ? null
                                    : Number(event.target.value),
                        })
                    }
                    className={cn(selectClasses, 'max-w-[14rem]')}
                >
                    <option value="">All categories</option>
                    {allCategories.map((option) => (
                        <option
                            key={option.id}
                            value={option.id}
                        >{`${' '.repeat(option.depth ?? 0)}${option.name}`}</option>
                    ))}
                </select>
                {tags.length > 0 ? (
                    <>
                        <label className="sr-only" htmlFor="filter-tag">
                            Tag
                        </label>
                        <select
                            id="filter-tag"
                            value={filters.tag ?? ''}
                            onChange={(event) =>
                                apply({
                                    tag:
                                        event.target.value === ''
                                            ? null
                                            : Number(event.target.value),
                                })
                            }
                            className={selectClasses}
                        >
                            <option value="">All tags</option>
                            {tags.map((tag) => (
                                <option key={tag.id} value={tag.id}>
                                    {tag.name}
                                </option>
                            ))}
                        </select>
                    </>
                ) : null}
                <form
                    onSubmit={submitSearch}
                    className="flex min-w-[12rem] flex-1 items-center gap-2 rounded-lg border border-control/70 bg-card px-3"
                >
                    <Search
                        className="size-4 shrink-0 text-muted-foreground"
                        strokeWidth={1.75}
                    />
                    <input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search payee or description"
                        aria-label="Search payee or description"
                        className="h-11 w-full bg-transparent text-[15px] outline-none placeholder:text-muted-foreground"
                    />
                </form>
                {filtered ? (
                    <button
                        type="button"
                        onClick={() => {
                            setSearch('');
                            apply({
                                type: 'all',
                                account: null,
                                category: null,
                                tag: null,
                                q: '',
                            });
                        }}
                        className="inline-flex h-11 items-center gap-1.5 rounded-lg px-3 text-[15px] font-medium text-positive hover:bg-muted"
                    >
                        <X className="size-4" strokeWidth={1.75} />
                        Clear filters
                    </button>
                ) : null}
            </div>

            {totals.length > 0 ? (
                <div className="mb-6 flex flex-wrap gap-x-8 gap-y-2 text-[15px] text-muted-foreground">
                    {totals.map((row) => (
                        <p key={row.currency}>
                            <span className="text-positive amount">
                                {formatMoney(
                                    row.in,
                                    row.currency,
                                    locale,
                                    'always',
                                )}
                            </span>
                            {' in · '}
                            <span className="text-foreground amount">
                                {formatMoney(
                                    row.out,
                                    row.currency,
                                    locale,
                                    'always',
                                )}
                            </span>
                            {' out · '}
                            {row.count}{' '}
                            {row.count === 1 ? 'transaction' : 'transactions'}
                        </p>
                    ))}
                </div>
            ) : null}

            {transactions.length === 0 ? (
                <div className="rounded-card border border-border bg-card px-6 py-10">
                    <p className="text-lg text-foreground">
                        {filtered
                            ? 'No transactions match these filters.'
                            : `No transactions in ${monthName} yet.`}
                    </p>
                    <p className="mt-1 text-[15px] text-muted-foreground">
                        {filtered
                            ? 'Try another month or clear the filters.'
                            : 'Synced banks and imports add them automatically; cash spending can be added by hand.'}
                    </p>
                </div>
            ) : (
                <div className="space-y-8">
                    {groups.map(([day, items]) => (
                        <section key={day}>
                            <h2 className="mb-2 text-sm font-medium tracking-[0.1em] text-muted-foreground uppercase">
                                {day === 'Undated'
                                    ? day
                                    : dayLabel(day, locale)}
                            </h2>
                            <ul className="divide-y divide-border rounded-card border border-border bg-card">
                                {items.map((transaction) => {
                                    const open = openId === transaction.id;
                                    const income = transaction.amount > 0;

                                    return (
                                        <li
                                            key={transaction.id}
                                            ref={open ? openRowRef : undefined}
                                            className="first:rounded-t-card last:rounded-b-card"
                                        >
                                            <button
                                                type="button"
                                                aria-expanded={open}
                                                onClick={() =>
                                                    setOpenId(
                                                        open
                                                            ? null
                                                            : transaction.id,
                                                    )
                                                }
                                                className={cn(
                                                    'grid w-full grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-4 px-4 py-3.5 text-left transition-colors tablet:grid-cols-[auto_minmax(0,2fr)_minmax(0,1.3fr)_minmax(0,1fr)_auto] tablet:px-5',
                                                    open
                                                        ? 'bg-accent/40'
                                                        : 'hover:bg-muted/60',
                                                )}
                                            >
                                                <IconTile
                                                    icon={
                                                        transaction.category_icon
                                                    }
                                                    income={income}
                                                />
                                                <span className="min-w-0">
                                                    <span className="block truncate text-base font-medium text-foreground">
                                                        {payee(transaction)}
                                                    </span>
                                                    <span className="block truncate text-sm text-muted-foreground tablet:hidden">
                                                        {leaf(
                                                            transaction.category,
                                                        )}{' '}
                                                        · {transaction.account}
                                                        {transaction.pending
                                                            ? ' · pending'
                                                            : ''}
                                                    </span>
                                                    {transaction.tag_ids
                                                        .length > 0 ? (
                                                        <span className="mt-1 flex flex-wrap gap-1">
                                                            {transaction.tag_ids.map(
                                                                (id) => {
                                                                    const tag =
                                                                        tagsById.get(
                                                                            id,
                                                                        );

                                                                    return tag ? (
                                                                        <TagChip
                                                                            key={
                                                                                id
                                                                            }
                                                                            tag={
                                                                                tag
                                                                            }
                                                                            colorMap={
                                                                                colorMap
                                                                            }
                                                                        />
                                                                    ) : null;
                                                                },
                                                            )}
                                                        </span>
                                                    ) : null}
                                                </span>
                                                <span className="hidden truncate text-base text-muted-foreground tablet:block">
                                                    {leaf(transaction.category)}
                                                    {transaction.pending
                                                        ? ' · pending'
                                                        : ''}
                                                </span>
                                                <span className="hidden truncate text-base text-muted-foreground tablet:block">
                                                    {transaction.account}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'text-right text-base font-medium amount',
                                                        income
                                                            ? 'text-positive'
                                                            : 'text-foreground',
                                                    )}
                                                >
                                                    {formatMoney(
                                                        transaction.amount,
                                                        transaction.currency,
                                                        locale,
                                                        'always',
                                                    )}
                                                </span>
                                            </button>
                                            {open ? (
                                                <div className="px-3 pb-4 tablet:px-5">
                                                    <TransactionEditor
                                                        transaction={
                                                            transaction
                                                        }
                                                        categories={
                                                            categoryOptions
                                                        }
                                                        tags={tags}
                                                        colors={categoryColors}
                                                        reload={RELOAD}
                                                        onClose={() =>
                                                            setOpenId(null)
                                                        }
                                                    />
                                                </div>
                                            ) : null}
                                        </li>
                                    );
                                })}
                            </ul>
                        </section>
                    ))}
                    {props.truncated ? (
                        <p className="text-[15px] text-muted-foreground">
                            Showing the latest 500. Narrow the filters to see
                            older ones.
                        </p>
                    ) : null}
                </div>
            )}

            <AddTransactionSheet
                open={adding}
                onOpenChange={setAdding}
                options={props.addTransaction}
                from="activity"
            />
        </AppLayout>
    );
}
