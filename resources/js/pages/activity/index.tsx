import { Head, router } from '@inertiajs/react';
import ListFilter from 'lucide-react/dist/esm/icons/list-filter.js';
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
import {
    MonthPicker,
    PageHeader,
    PhoneAddButton,
    useLocale,
} from '@/components/kit';
import type { TagColorOption, TagOption } from '@/components/tag-chip';
import { TransactionEditor } from '@/components/transaction-editor';
import { TransactionLedger } from '@/components/transaction-ledger';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { amountTone, formatMonth, formatMoney } from '@/lib/money';
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

const NO_FILTERS: Filters = {
    type: 'all',
    account: null,
    category: null,
    tag: null,
    q: '',
};

const selectClasses =
    'h-11 rounded-lg border border-control/70 bg-card px-3 text-[15px] text-foreground';

const TYPES: Array<{ value: Filters['type']; label: string }> = [
    { value: 'all', label: 'All types' },
    { value: 'expense', label: 'Expenses' },
    { value: 'income', label: 'Income' },
];

function focusFromUrl(): number | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const value = Number(
        new URLSearchParams(window.location.search).get('focus'),
    );

    return Number.isFinite(value) && value > 0 ? value : null;
}

function activeCount(filters: Filters): number {
    return [
        filters.type !== 'all',
        filters.account !== null,
        filters.category !== null,
        filters.tag !== null,
        filters.q !== '',
    ].filter(Boolean).length;
}

function toId(value: string): number | null {
    return value === '' ? null : Number(value);
}

/** Phone: every filter in one sheet, opened from the "Filters" control. */
function FilterSheet({
    open,
    onOpenChange,
    filters,
    accounts,
    categories,
    tags,
    onApply,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    filters: Filters;
    accounts: Props['accounts'];
    categories: CategoryOptionLite[];
    tags: TagOption[];
    onApply: (filters: Filters) => void;
}) {
    const [draft, setDraft] = useState<Filters>(filters);

    const change = (next: boolean): void => {
        if (next) {
            setDraft(filters);
        }

        onOpenChange(next);
    };

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        onApply({ ...draft, q: draft.q.trim() });
        onOpenChange(false);
    };

    return (
        <Sheet open={open} onOpenChange={change}>
            <SheetContent
                side="bottom"
                className="max-h-[90dvh] gap-0 overflow-y-auto rounded-t-card bg-background p-0"
            >
                <SheetHeader className="border-b border-border px-5 py-4">
                    <SheetTitle className="text-section font-medium">
                        Filters
                    </SheetTitle>
                    <SheetDescription className="sr-only">
                        Narrow the list by type, wallet, category, tag or text.
                    </SheetDescription>
                </SheetHeader>
                <form
                    onSubmit={submit}
                    className="flex flex-col gap-5 px-5 pt-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
                >
                    <div
                        className="grid grid-cols-3 gap-1 rounded-xl border border-border bg-card p-1"
                        role="radiogroup"
                        aria-label="Type"
                    >
                        {TYPES.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                role="radio"
                                aria-checked={draft.type === option.value}
                                onClick={() =>
                                    setDraft({ ...draft, type: option.value })
                                }
                                className={cn(
                                    'h-11 rounded-lg text-[15px] transition-colors',
                                    draft.type === option.value
                                        ? 'bg-accent font-medium text-accent-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {option.value === 'all' ? 'All' : option.label}
                            </button>
                        ))}
                    </div>

                    <label className="grid gap-1.5">
                        <span className="text-[15px] text-muted-foreground">
                            Search
                        </span>
                        <input
                            value={draft.q}
                            onChange={(event) =>
                                setDraft({ ...draft, q: event.target.value })
                            }
                            placeholder="Payee or description"
                            className="h-11 w-full rounded-lg border border-control/70 bg-card px-3.5 text-base text-foreground placeholder:text-muted-foreground"
                        />
                    </label>

                    <label className="grid gap-1.5">
                        <span className="text-[15px] text-muted-foreground">
                            Wallet
                        </span>
                        <select
                            value={draft.account ?? ''}
                            onChange={(event) =>
                                setDraft({
                                    ...draft,
                                    account: toId(event.target.value),
                                })
                            }
                            className={cn(selectClasses, 'w-full text-base')}
                        >
                            <option value="">All wallets</option>
                            {accounts.map((account) => (
                                <option key={account.id} value={account.id}>
                                    {account.name}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="grid gap-1.5">
                        <span className="text-[15px] text-muted-foreground">
                            Category
                        </span>
                        <select
                            value={draft.category ?? ''}
                            onChange={(event) =>
                                setDraft({
                                    ...draft,
                                    category: toId(event.target.value),
                                })
                            }
                            className={cn(selectClasses, 'w-full text-base')}
                        >
                            <option value="">All categories</option>
                            {categories.map((option) => (
                                <option
                                    key={option.id}
                                    value={option.id}
                                >{`${' '.repeat(option.depth ?? 0)}${option.name}`}</option>
                            ))}
                        </select>
                    </label>

                    {tags.length > 0 ? (
                        <label className="grid gap-1.5">
                            <span className="text-[15px] text-muted-foreground">
                                Tag
                            </span>
                            <select
                                value={draft.tag ?? ''}
                                onChange={(event) =>
                                    setDraft({
                                        ...draft,
                                        tag: toId(event.target.value),
                                    })
                                }
                                className={cn(
                                    selectClasses,
                                    'w-full text-base',
                                )}
                            >
                                <option value="">All tags</option>
                                {tags.map((tag) => (
                                    <option key={tag.id} value={tag.id}>
                                        {tag.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    ) : null}

                    <div className="mt-1 grid grid-cols-2 gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-12 rounded-lg text-base"
                            disabled={activeCount(draft) === 0}
                            onClick={() => setDraft(NO_FILTERS)}
                        >
                            Clear all
                        </Button>
                        <Button
                            type="submit"
                            className="h-12 rounded-lg text-base font-medium"
                        >
                            Show results
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
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
    const [filtering, setFiltering] = useState(false);
    const [search, setSearch] = useState(filters.q);
    const openRowRef = useRef<HTMLLIElement | null>(null);
    const allCategories = useMemo(
        () => [...categoryOptions.expense, ...categoryOptions.income],
        [categoryOptions],
    );

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

        if (overrides.q !== undefined) {
            setSearch(overrides.q);
        }

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

    const active = activeCount(filters);
    const filtered = active > 0;
    const monthName = formatMonth(month.start, locale, 'long').split(' ')[0];
    const openAdd = (): void => setAdding(true);

    return (
        <AppLayout
            phoneAction={
                <PhoneAddButton label="Add transaction" onClick={openAdd} />
            }
        >
            <Head title="Activity" />

            <PageHeader
                title="Activity"
                actions={
                    <Button
                        type="button"
                        onClick={openAdd}
                        className="hidden h-11 rounded-lg px-5 text-[15px] font-medium tablet:inline-flex"
                    >
                        <Plus className="size-5" strokeWidth={1.75} />
                        Add transaction
                    </Button>
                }
            />

            <div className="mb-5 flex items-center gap-3 tablet:hidden">
                <MonthPicker month={month} query={query()} />
                <button
                    type="button"
                    onClick={() => setFiltering(true)}
                    className={cn(
                        'inline-flex h-11 items-center gap-2 rounded-lg border px-3.5 text-[15px] transition-colors',
                        filtered
                            ? 'border-primary/60 bg-accent text-accent-foreground'
                            : 'border-control/70 bg-card text-foreground hover:bg-muted',
                    )}
                >
                    <ListFilter className="size-5" strokeWidth={1.75} />
                    {filtered ? `Filters · ${active}` : 'Filters'}
                </button>
            </div>

            <div className="mb-6 hidden flex-wrap items-center gap-3 tablet:flex">
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
                    {TYPES.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <label className="sr-only" htmlFor="filter-wallet">
                    Wallet
                </label>
                <select
                    id="filter-wallet"
                    value={filters.account ?? ''}
                    onChange={(event) =>
                        apply({ account: toId(event.target.value) })
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
                        apply({ category: toId(event.target.value) })
                    }
                    className={cn(selectClasses, 'max-w-[14rem]')}
                >
                    <option value="">All categories</option>
                    {allCategories.map((option) => (
                        <option
                            key={option.id}
                            value={option.id}
                        >{`${' '.repeat(option.depth ?? 0)}${option.name}`}</option>
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
                                apply({ tag: toId(event.target.value) })
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
                        onClick={() => apply(NO_FILTERS)}
                        className="inline-flex h-11 items-center gap-1.5 rounded-lg px-3 text-[15px] font-medium text-positive hover:bg-muted"
                    >
                        <X className="size-4" strokeWidth={1.75} />
                        Clear filters
                    </button>
                ) : null}
            </div>

            {totals.length > 0 ? (
                <div className="mb-4 flex flex-wrap gap-x-8 gap-y-1 text-[15px] text-muted-foreground tablet:mb-6">
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
                            <span className={cn('amount', amountTone(row.out))}>
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
                <>
                    <TransactionLedger
                        transactions={transactions}
                        variant="activity"
                        openId={openId}
                        onToggle={setOpenId}
                        openRowRef={openRowRef}
                        tags={tags}
                        categoryColors={categoryColors}
                        renderEditor={(transaction) => (
                            <TransactionEditor
                                transaction={transaction}
                                categories={categoryOptions}
                                tags={tags}
                                colors={categoryColors}
                                reload={RELOAD}
                                onClose={() => setOpenId(null)}
                            />
                        )}
                    />
                    {props.truncated ? (
                        <p className="mt-6 text-[15px] text-muted-foreground">
                            Showing the latest 500. Narrow the filters to see
                            older ones.
                        </p>
                    ) : null}
                </>
            )}

            <FilterSheet
                open={filtering}
                onOpenChange={setFiltering}
                filters={filters}
                accounts={accounts}
                categories={allCategories}
                tags={tags}
                onApply={apply}
            />

            <AddTransactionSheet
                open={adding}
                onOpenChange={setAdding}
                options={props.addTransaction}
                from="activity"
            />
        </AppLayout>
    );
}
