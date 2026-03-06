import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    CalendarDays,
    Landmark,
    Search,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Account = {
    id: number;
    display_name: string | null;
    name: string | null;
    official_name: string | null;
    institution_name: string | null;
    mask: string | null;
    currency: string | null;
    balances: {
        current?: number | null;
        available?: number | null;
    } | null;
    last_synced_at: string | null;
};

type Transaction = {
    id: number;
    name: string | null;
    merchant_name: string | null;
    counterparty: string | null;
    category: string;
    amount: number;
    currency: string | null;
    date: string | null;
    pending: boolean;
};

type Filters = {
    start_date: string;
    end_date: string;
};

type Props = {
    account: Account;
    filters: Filters;
    transactions: Transaction[];
};

function formatCurrency(value: number, currency: string): string {
    const sign = value > 0 ? '+' : '';

    return `${sign}${value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} ${currency}`;
}

function formatDisplayDate(dateValue: string): string {
    const parsed = new Date(`${dateValue}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return dateValue;
    }

    return parsed.toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    });
}

function shiftMonth(dateValue: string, direction: -1 | 1): string {
    const parsed = new Date(`${dateValue}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return dateValue;
    }

    parsed.setMonth(parsed.getMonth() + direction);

    return parsed.toISOString().slice(0, 10);
}

function formatDayLabel(dateValue: string): string {
    const parsed = new Date(`${dateValue}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return dateValue;
    }

    return parsed.toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    });
}

export default function AccountShow({ account, filters, transactions }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: account.display_name ?? account.name ?? 'Account',
            href: `/accounts/${account.id}`,
        },
    ];

    const currency = account.currency ?? 'EUR';
    const currentBalance = Number(account.balances?.current ?? 0);
    const availableBalance = account.balances?.available;

    const categories = useMemo(
        () => Array.from(new Set(transactions.map((transaction) => transaction.category))).sort(),
        [transactions],
    );

    const counterparties = useMemo(
        () =>
            Array.from(
                new Set(
                    transactions
                        .map((transaction) => transaction.counterparty ?? transaction.merchant_name)
                        .filter((value): value is string => Boolean(value)),
                ),
            ).sort(),
        [transactions],
    );

    const [categoryFilter, setCategoryFilter] = useState('all');
    const [counterpartyFilter, setCounterpartyFilter] = useState('all');
    const [keywordFilter, setKeywordFilter] = useState('');

    const maxAbsAmount = useMemo(
        () => Math.max(...transactions.map((transaction) => Math.abs(transaction.amount)), 100),
        [transactions],
    );
    const [amountCap, setAmountCap] = useState(maxAbsAmount);

    const filteredTransactions = useMemo(() => {
        const keyword = keywordFilter.trim().toLowerCase();

        return transactions.filter((transaction) => {
            const categoryMatch =
                categoryFilter === 'all' || transaction.category === categoryFilter;
            const personValue =
                transaction.counterparty ?? transaction.merchant_name ?? 'Unknown';
            const personMatch =
                counterpartyFilter === 'all' || personValue === counterpartyFilter;
            const keywordSource = [
                transaction.name,
                transaction.merchant_name,
                transaction.counterparty,
                transaction.category,
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();
            const keywordMatch = keyword.length === 0 || keywordSource.includes(keyword);
            const amountMatch = Math.abs(transaction.amount) <= amountCap;

            return categoryMatch && personMatch && keywordMatch && amountMatch;
        });
    }, [amountCap, categoryFilter, counterpartyFilter, keywordFilter, transactions]);

    const groupedTransactions = useMemo(() => {
        const grouped = new Map<string, Transaction[]>();

        filteredTransactions.forEach((transaction) => {
            const dateKey = transaction.date ?? 'Unknown date';
            const dayTransactions = grouped.get(dateKey) ?? [];

            dayTransactions.push(transaction);
            grouped.set(dateKey, dayTransactions);
        });

        return Array.from(grouped.entries());
    }, [filteredTransactions]);

    const resetFilters = (): void => {
        setCategoryFilter('all');
        setCounterpartyFilter('all');
        setKeywordFilter('');
        setAmountCap(maxAbsAmount);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={account.display_name ?? account.name ?? 'Account'} />

            <div className="space-y-6">
                <Card className="border-brand/20 bg-linear-to-br from-brand/6 via-card to-card py-4 dark:from-brand/10">
                    <CardContent className="flex flex-wrap items-start justify-between gap-4 px-4">
                        <div>
                            <p className="text-xl font-semibold text-foreground">
                                {account.display_name ??
                                    account.name ??
                                    account.official_name ??
                                    'Bank account'}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                <Landmark className="mr-1 inline size-4 text-brand" />
                                {account.institution_name ?? 'Institution'}
                                {account.mask ? ` • **** ${account.mask}` : ''}
                            </p>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Last synced: {account.last_synced_at ? new Date(account.last_synced_at).toLocaleString() : 'Never'}
                            </p>
                        </div>

                        <div className="text-right">
                            <p className="text-sm text-muted-foreground">Current balance</p>
                            <p className="text-3xl font-semibold text-emerald-600 dark:text-emerald-400">
                                {formatCurrency(currentBalance, currency)}
                            </p>
                            {typeof availableBalance === 'number' ? (
                                <p className="text-xs text-muted-foreground">
                                    Available: {formatCurrency(availableBalance, currency)}
                                </p>
                            ) : null}
                        </div>
                    </CardContent>
                </Card>

                <div className="rounded-xl border border-border bg-card p-4">
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <h2 className="text-xl font-semibold">Filters</h2>
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Reset filters
                        </button>
                    </div>

                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <p className="mb-1 text-xs text-muted-foreground">By category</p>
                            <select
                                value={categoryFilter}
                                onChange={(event) => setCategoryFilter(event.target.value)}
                                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="all">All categories</option>
                                {categories.map((category) => (
                                    <option key={category} value={category}>
                                        {category}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <p className="mb-1 text-xs text-muted-foreground">By people</p>
                            <select
                                value={counterpartyFilter}
                                onChange={(event) => setCounterpartyFilter(event.target.value)}
                                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="all">All people</option>
                                {counterparties.map((name) => (
                                    <option key={name} value={name}>
                                        {name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <p className="mb-1 text-xs text-muted-foreground">By note</p>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground" />
                                <Input
                                    value={keywordFilter}
                                    onChange={(event) => setKeywordFilter(event.target.value)}
                                    placeholder="Filter by specific keyword"
                                    className="pl-9"
                                />
                            </div>
                        </div>

                        <div>
                            <p className="mb-1 text-xs text-muted-foreground">By amount</p>
                            <div className="rounded-md border border-input bg-background px-3 py-2">
                                <input
                                    type="range"
                                    min={0}
                                    max={Math.ceil(maxAbsAmount)}
                                    step={1}
                                    value={amountCap}
                                    onChange={(event) => setAmountCap(Number(event.target.value))}
                                    className="w-full"
                                />
                                <div className="mt-1 flex items-center justify-between text-xs text-muted-foreground">
                                    <span>0</span>
                                    <span>{Math.round(amountCap)}</span>
                                    <span>{Math.ceil(maxAbsAmount)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex w-full flex-wrap items-center justify-between gap-3">
                    <h2 className="text-xl font-semibold">Transactions</h2>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => {
                                router.get(
                                    `/accounts/${account.id}`,
                                    {
                                        start_date: shiftMonth(filters.start_date, -1),
                                        end_date: shiftMonth(filters.end_date, -1),
                                    },
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                    },
                                );
                            }}
                        >
                            <ArrowLeft className="size-4" />
                        </Button>

                        <div className="flex items-center gap-2 rounded-md border border-border bg-card px-3 py-2 text-sm">
                            <CalendarDays className="size-4 text-muted-foreground" />
                            <span>
                                {formatDisplayDate(filters.start_date)} - {formatDisplayDate(filters.end_date)}
                            </span>
                        </div>

                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => {
                                router.get(
                                    `/accounts/${account.id}`,
                                    {
                                        start_date: shiftMonth(filters.start_date, 1),
                                        end_date: shiftMonth(filters.end_date, 1),
                                    },
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                    },
                                );
                            }}
                        >
                            <ArrowRight className="size-4" />
                        </Button>
                    </div>
                </div>

                <Card className="overflow-hidden py-0">
                    <div className="divide-y divide-border">
                        {groupedTransactions.map(([day, dayTransactions]) => {
                            const dayTotal = dayTransactions.reduce(
                                (carry, transaction) => carry + transaction.amount,
                                0,
                            );

                            return (
                                <section key={day} className="px-4 py-3">
                                    <div className="mb-3 flex items-center justify-between">
                                        <h3 className="text-base font-semibold text-foreground">
                                            {day === 'Unknown date' ? day : formatDayLabel(day)}
                                        </h3>
                                        <p
                                            className={`text-sm font-semibold ${
                                                dayTotal > 0
                                                    ? 'text-[#ff6f61] dark:text-[#ff8b80]'
                                                    : dayTotal < 0
                                                      ? 'text-emerald-600 dark:text-emerald-400'
                                                      : 'text-muted-foreground'
                                            }`}
                                        >
                                            {formatCurrency(dayTotal, currency)}
                                        </p>
                                    </div>

                                    <div className="space-y-2">
                                        {dayTransactions.map((transaction) => {
                                            const amountClass =
                                                transaction.amount > 0
                                                    ? 'text-[#ff6f61] dark:text-[#ff8b80]'
                                                    : 'text-emerald-600 dark:text-emerald-400';

                                            return (
                                                <div
                                                    key={transaction.id}
                                                    className="grid grid-cols-[1fr_auto] items-center gap-3 rounded-md border border-border/70 bg-background/50 px-3 py-2"
                                                >
                                                    <div>
                                                        <p className="text-sm font-medium text-foreground">
                                                            {transaction.category}
                                                            {transaction.pending ? (
                                                                <span className="ml-2 rounded bg-slate-200 px-1.5 py-0.5 text-[10px] text-slate-700 dark:bg-slate-700 dark:text-slate-100">
                                                                    Pending
                                                                </span>
                                                            ) : null}
                                                        </p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {transaction.merchant_name ?? transaction.name ?? 'Transaction'}
                                                        </p>
                                                        {transaction.counterparty ? (
                                                            <p className="text-xs text-muted-foreground/80">
                                                                {transaction.counterparty}
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                    <p className={`text-sm font-semibold ${amountClass}`}>
                                                        {formatCurrency(
                                                            transaction.amount,
                                                            transaction.currency ?? currency,
                                                        )}
                                                    </p>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </section>
                            );
                        })}

                        {groupedTransactions.length === 0 ? (
                            <div className="px-4 py-8 text-center text-sm text-muted-foreground">
                                No transactions match the selected filters.
                            </div>
                        ) : null}
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
