import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import Pencil from 'lucide-react/dist/esm/icons/pencil.js';
import Plus from 'lucide-react/dist/esm/icons/plus.js';
import Trash2 from 'lucide-react/dist/esm/icons/trash-2.js';
import Wand2 from 'lucide-react/dist/esm/icons/wand-2.js';
import X from 'lucide-react/dist/esm/icons/x.js';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { CategoryIcon } from '@/components/category-icon';
import { resolveCategoryColorPresentation } from '@/components/category-icon-utils';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { TagChip } from '@/components/tag-chip';
import type { TagColorOption } from '@/components/tag-chip';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { buildDashboardHref, formatDisplayDate } from '@/lib/date-filters';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Period = 'month' | 'year' | '12m' | 'all';

type Total = {
    currency: string;
    spent: number;
    received: number;
    count: number;
};

type CategoryTotal = {
    category: string;
    icon: string;
    color: string;
    currency: string;
    spent: number;
};

type TaggedTransaction = {
    id: number;
    date: string | null;
    label: string;
    account_id: number;
    account: string | null;
    amount: number;
    currency: string;
    category: string | null;
    category_icon: string;
    category_color: string;
    pending: boolean;
};

type Rule = {
    id: number;
    account_id: number | null;
    account: string | null;
    contains: string | null;
};

type TagSummary = {
    id: number;
    name: string;
    color: string;
    count: number;
    totals: Total[];
    by_category: CategoryTotal[];
    transactions: TaggedTransaction[];
    rules: Rule[];
};

type AccountOption = {
    id: number;
    name: string;
};

type Props = {
    period: Period;
    range: { from: string | null; to: string };
    tags: TagSummary[];
    accounts: AccountOption[];
    colors: TagColorOption[];
};

const PERIODS: Array<{ value: Period; label: string }> = [
    { value: 'month', label: 'This month' },
    { value: 'year', label: 'This year' },
    { value: '12m', label: '12 months' },
    { value: 'all', label: 'All time' },
];

/** Read as "4 transactions this year", "Nothing spent in the last 12 months". */
const PERIOD_PHRASES: Record<Period, string> = {
    month: 'this month',
    year: 'this year',
    '12m': 'in the last 12 months',
    all: 'in total',
};

const panelClasses = 'rounded-card border border-border bg-card p-5 tablet:p-6';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm';

const VISIBLE_TRANSACTIONS = 8;

function formatMoney(value: number, currency: string, locale: string): string {
    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency,
        }).format(value);
    } catch {
        return `${value.toFixed(2)} ${currency}`;
    }
}

/** The account page for the month the transaction is in. */
function accountMonthHref(accountId: number, date: string | null): string {
    if (date === null || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
        return `/accounts/${accountId}`;
    }

    const [year, month] = date.split('-').map(Number);
    const lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate();
    const prefix = date.slice(0, 8);

    return `/accounts/${accountId}?start_date=${prefix}01&end_date=${prefix}${String(lastDay).padStart(2, '0')}`;
}

function leafName(path: string): string {
    const parts = path.split(' / ');

    return parts[parts.length - 1] ?? path;
}

function ColorSwatches({
    colors,
    value,
    onChange,
}: {
    colors: TagColorOption[];
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div
            className="flex flex-wrap gap-1.5"
            role="radiogroup"
            aria-label="Color"
        >
            {colors.map((color) => (
                <button
                    key={color.value}
                    type="button"
                    role="radio"
                    aria-checked={value === color.value}
                    aria-label={color.label}
                    title={color.label}
                    onClick={() => onChange(color.value)}
                    className={`size-6 rounded-full border-2 transition outline-none focus-visible:ring-2 focus-visible:ring-brand/40 ${
                        value === color.value
                            ? 'border-foreground'
                            : 'border-transparent hover:border-foreground/30'
                    }`}
                    style={{ backgroundColor: color.background }}
                />
            ))}
        </div>
    );
}

function NewTagForm({
    colors,
    usedColors,
}: {
    colors: TagColorOption[];
    usedColors: Set<string>;
}) {
    const firstFree =
        colors.find((color) => !usedColors.has(color.value))?.value ??
        colors[0]?.value ??
        'slate';
    const form = useForm({ name: '', color: firstFree });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post('/tags', {
            preserveScroll: true,
            onSuccess: () => form.reset('name'),
        });
    };

    return (
        <form onSubmit={submit} className={`space-y-3 ${panelClasses}`}>
            <h3 className="font-medium">New tag</h3>
            <div className="grid gap-3 sm:grid-cols-[minmax(0,18rem)_1fr] sm:items-start">
                <div className="grid gap-1.5">
                    <Label htmlFor="new-tag-name">Name</Label>
                    <Input
                        id="new-tag-name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        placeholder="e.g. Golf, Motorbike, Flat Ljubljana"
                        maxLength={50}
                    />
                    <InputError message={form.errors.name} />
                </div>
                <div className="grid gap-1.5">
                    <Label>Color</Label>
                    <ColorSwatches
                        colors={colors}
                        value={form.data.color}
                        onChange={(value) => form.setData('color', value)}
                    />
                </div>
            </div>
            <div className="flex justify-end">
                <Button
                    type="submit"
                    disabled={form.processing || form.data.name.trim() === ''}
                >
                    <Plus className="size-4" />
                    Create tag
                </Button>
            </div>
        </form>
    );
}

function RuleForm({
    tag,
    accounts,
}: {
    tag: TagSummary;
    accounts: AccountOption[];
}) {
    const form = useForm<{
        bank_account_id: string;
        contains: string;
        apply_existing: boolean;
    }>({ bank_account_id: '', contains: '', apply_existing: true });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            bank_account_id:
                data.bank_account_id === ''
                    ? null
                    : Number(data.bank_account_id),
        }));
        form.post(`/tags/${tag.id}/rules`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-2">
            <div className="grid gap-2 sm:grid-cols-2">
                <div className="grid gap-1">
                    <Label
                        htmlFor={`rule-account-${tag.id}`}
                        className="text-xs"
                    >
                        From account
                    </Label>
                    <select
                        id={`rule-account-${tag.id}`}
                        className={selectClasses}
                        value={form.data.bank_account_id}
                        onChange={(event) =>
                            form.setData('bank_account_id', event.target.value)
                        }
                    >
                        <option value="">Any account</option>
                        {accounts.map((account) => (
                            <option key={account.id} value={account.id}>
                                {account.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1">
                    <Label htmlFor={`rule-text-${tag.id}`} className="text-xs">
                        Payee or description contains
                    </Label>
                    <Input
                        id={`rule-text-${tag.id}`}
                        value={form.data.contains}
                        onChange={(event) =>
                            form.setData('contains', event.target.value)
                        }
                        placeholder="e.g. J. Novak, OMV, Yamaha"
                        maxLength={120}
                        className="h-9"
                    />
                </div>
            </div>
            <InputError
                message={form.errors.contains ?? form.errors.bank_account_id}
            />
            <div className="flex flex-wrap items-center justify-between gap-2">
                <label className="flex items-center gap-2 text-xs text-muted-foreground">
                    <input
                        type="checkbox"
                        checked={form.data.apply_existing}
                        onChange={(event) =>
                            form.setData('apply_existing', event.target.checked)
                        }
                        className="size-4 rounded border-input accent-brand"
                    />
                    Also tag matching past transactions
                </label>
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    disabled={
                        form.processing ||
                        (form.data.bank_account_id === '' &&
                            form.data.contains.trim() === '')
                    }
                >
                    <Wand2 className="size-4" />
                    Add rule
                </Button>
            </div>
        </form>
    );
}

function TagCard({
    tag,
    accounts,
    colors,
    colorMap,
    locale,
    period,
}: {
    tag: TagSummary;
    accounts: AccountOption[];
    colors: TagColorOption[];
    colorMap: Map<string, TagColorOption>;
    locale: string;
    period: Period;
}) {
    const [isEditing, setIsEditing] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [showAll, setShowAll] = useState(false);
    const [showRuleForm, setShowRuleForm] = useState(false);
    const editForm = useForm({ name: tag.name, color: tag.color });
    const maxSpent = Math.max(0, ...tag.by_category.map((item) => item.spent));
    const visibleTransactions = showAll
        ? tag.transactions
        : tag.transactions.slice(0, VISIBLE_TRANSACTIONS);
    const periodLabel = PERIOD_PHRASES[period];

    const saveEdit = (event: FormEvent): void => {
        event.preventDefault();
        editForm.patch(`/tags/${tag.id}`, {
            preserveScroll: true,
            onSuccess: () => setIsEditing(false),
        });
    };

    const deleteTag = (): void => {
        router.delete(`/tags/${tag.id}`, { preserveScroll: true });
    };

    const removeRule = (rule: Rule): void => {
        router.delete(`/tags/${tag.id}/rules/${rule.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <section className={`space-y-4 ${panelClasses}`}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                {isEditing ? (
                    <form onSubmit={saveEdit} className="w-full space-y-3">
                        <div className="grid gap-1.5 sm:max-w-72">
                            <Label htmlFor={`tag-name-${tag.id}`}>Name</Label>
                            <Input
                                id={`tag-name-${tag.id}`}
                                value={editForm.data.name}
                                onChange={(event) =>
                                    editForm.setData('name', event.target.value)
                                }
                                maxLength={50}
                            />
                            <InputError message={editForm.errors.name} />
                        </div>
                        <ColorSwatches
                            colors={colors}
                            value={editForm.data.color}
                            onChange={(value) =>
                                editForm.setData('color', value)
                            }
                        />
                        <div className="flex gap-2">
                            <Button
                                type="submit"
                                size="sm"
                                disabled={editForm.processing}
                            >
                                Save
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    editForm.reset();
                                    setIsEditing(false);
                                }}
                            >
                                Cancel
                            </Button>
                        </div>
                    </form>
                ) : (
                    <>
                        <div className="min-w-0 space-y-1">
                            <TagChip
                                tag={tag}
                                colorMap={colorMap}
                                className="px-3 py-1 text-sm"
                            />
                            <p className="text-xs text-muted-foreground">
                                {tag.count === 0
                                    ? `No transactions ${periodLabel}`
                                    : `${tag.count} transaction${tag.count === 1 ? '' : 's'} ${periodLabel}`}
                            </p>
                        </div>
                        <div className="flex gap-1.5">
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => setIsEditing(true)}
                                aria-label={`Edit ${tag.name}`}
                            >
                                <Pencil className="size-4" />
                            </Button>
                            {confirmDelete ? (
                                <>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        onClick={deleteTag}
                                    >
                                        Delete tag
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setConfirmDelete(false)}
                                    >
                                        Keep
                                    </Button>
                                </>
                            ) : (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => setConfirmDelete(true)}
                                    aria-label={`Delete ${tag.name}`}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </div>
            {confirmDelete ? (
                <p className="text-xs text-muted-foreground">
                    Deleting removes the tag and its rules from every
                    transaction. The transactions themselves stay.
                </p>
            ) : null}

            {tag.totals.length > 0 ? (
                <div className="flex flex-wrap gap-x-6 gap-y-2">
                    {tag.totals.map((total) => (
                        <div key={total.currency} className="space-y-0.5">
                            <p className="text-xs text-muted-foreground">
                                Spent
                                {tag.totals.length > 1
                                    ? ` (${total.currency})`
                                    : ''}
                            </p>
                            <p className="text-xl font-semibold text-destructive tabular-nums">
                                {formatMoney(
                                    total.spent,
                                    total.currency,
                                    locale,
                                )}
                            </p>
                            {total.received > 0 ? (
                                <p className="text-xs text-positive tabular-nums">
                                    Received{' '}
                                    {formatMoney(
                                        total.received,
                                        total.currency,
                                        locale,
                                    )}
                                </p>
                            ) : null}
                        </div>
                    ))}
                </div>
            ) : null}

            {tag.count > 0 ? (
                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="space-y-2">
                        <h4 className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                            Spent by category
                        </h4>
                        {tag.by_category.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing spent {periodLabel}.
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {tag.by_category.slice(0, 6).map((item) => (
                                    <li
                                        key={`${item.category}-${item.currency}`}
                                        className="space-y-1"
                                    >
                                        <div className="flex items-center justify-between gap-3 text-sm">
                                            <span className="flex min-w-0 items-center gap-2">
                                                <CategoryIcon
                                                    name={item.icon}
                                                    variant="badge"
                                                    presentation={resolveCategoryColorPresentation(
                                                        item.color,
                                                        colorMap,
                                                    )}
                                                    className="size-6 rounded-lg"
                                                    iconSize={12}
                                                />
                                                <span className="truncate">
                                                    {leafName(item.category)}
                                                </span>
                                            </span>
                                            <span className="shrink-0 tabular-nums">
                                                {formatMoney(
                                                    item.spent,
                                                    item.currency,
                                                    locale,
                                                )}
                                            </span>
                                        </div>
                                        <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-brand/70"
                                                style={{
                                                    width: `${maxSpent > 0 ? Math.max(4, (item.spent / maxSpent) * 100) : 0}%`,
                                                }}
                                            />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="space-y-2">
                        <h4 className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                            Transactions
                        </h4>
                        <ul className="divide-y divide-border rounded-lg border border-border bg-card/70">
                            {visibleTransactions.map((transaction) => (
                                <li key={transaction.id}>
                                    <Link
                                        href={accountMonthHref(
                                            transaction.account_id,
                                            transaction.date,
                                        )}
                                        className="flex items-center justify-between gap-3 px-3 py-2 text-sm hover:bg-brand/5"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate font-medium">
                                                {transaction.label}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {transaction.date
                                                    ? formatDisplayDate(
                                                          transaction.date,
                                                          locale,
                                                      )
                                                    : ''}
                                                {transaction.category
                                                    ? ` · ${leafName(transaction.category)}`
                                                    : ''}
                                                {transaction.account
                                                    ? ` · ${transaction.account}`
                                                    : ''}
                                            </span>
                                        </span>
                                        <span
                                            className={`shrink-0 font-semibold tabular-nums ${
                                                transaction.amount < 0
                                                    ? 'text-destructive'
                                                    : 'text-positive'
                                            }`}
                                        >
                                            {formatMoney(
                                                transaction.amount,
                                                transaction.currency,
                                                locale,
                                            )}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                        {tag.transactions.length > VISIBLE_TRANSACTIONS ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => setShowAll((value) => !value)}
                            >
                                {showAll
                                    ? 'Show fewer'
                                    : `Show ${tag.transactions.length - VISIBLE_TRANSACTIONS} more`}
                            </Button>
                        ) : null}
                        {tag.count > tag.transactions.length ? (
                            <p className="text-xs text-muted-foreground">
                                Showing the latest {tag.transactions.length} of{' '}
                                {tag.count}.
                            </p>
                        ) : null}
                    </div>
                </div>
            ) : null}

            <div className="space-y-2 border-t border-border pt-3">
                <div className="flex items-center justify-between gap-2">
                    <h4 className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                        Tag automatically
                    </h4>
                    {!showRuleForm ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => setShowRuleForm(true)}
                        >
                            <Plus className="size-4" />
                            Add rule
                        </Button>
                    ) : null}
                </div>
                {tag.rules.length > 0 ? (
                    <ul className="space-y-1.5">
                        {tag.rules.map((rule) => (
                            <li
                                key={rule.id}
                                className="flex items-center justify-between gap-2 rounded-lg border border-border bg-card/70 px-3 py-1.5 text-sm"
                            >
                                <span className="min-w-0 truncate">
                                    {rule.account_id !== null
                                        ? `From ${rule.account ?? 'a removed account'}`
                                        : 'Any account'}
                                    {rule.contains
                                        ? `${rule.account_id !== null ? ' and' : ','} contains “${rule.contains}”`
                                        : ''}
                                </span>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => removeRule(rule)}
                                    aria-label="Remove rule"
                                >
                                    <X className="size-4" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        No rules yet. For example: payments from your
                        motorbike's card account, or payee “J. Novak” for a
                        flat's rent.
                    </p>
                )}
                {showRuleForm ? (
                    <RuleForm tag={tag} accounts={accounts} />
                ) : null}
            </div>
        </section>
    );
}

export default function TagsIndex({ period, tags, accounts, colors }: Props) {
    const page = usePage<{ number_locale?: string }>();
    const locale = page.props.number_locale ?? 'en-GB';
    const colorMap = useMemo(
        () => new Map(colors.map((color) => [color.value, color])),
        [colors],
    );
    const usedColors = useMemo(
        () => new Set(tags.map((tag) => tag.color)),
        [tags],
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: buildDashboardHref(dashboard()) },
        { title: 'Tags', href: '/tags' },
    ];

    const changePeriod = (value: Period): void => {
        router.get(
            '/tags',
            { period: value },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tags" />

            <div className="mx-auto w-full max-w-5xl space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading
                        variant="page"
                        title="Tags"
                        description="Tags say which car, flat or project a payment was for; categories still say what it was. Tag a transaction from its account page, or let rules do it."
                    />
                    <div
                        className="grid w-full grid-cols-4 rounded-lg border border-border bg-card p-0.5 sm:inline-flex sm:w-auto"
                        role="group"
                        aria-label="Period"
                    >
                        {PERIODS.map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                aria-pressed={period === item.value}
                                onClick={() => changePeriod(item.value)}
                                className={`rounded-md px-2 py-1.5 text-xs whitespace-nowrap transition sm:px-3 sm:text-sm ${
                                    period === item.value
                                        ? 'bg-brand text-white shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                </div>

                {tags.map((tag) => (
                    <TagCard
                        key={tag.id}
                        tag={tag}
                        accounts={accounts}
                        colors={colors}
                        colorMap={colorMap}
                        locale={locale}
                        period={period}
                    />
                ))}

                {tags.length === 0 ? (
                    <div className={`space-y-2 ${panelClasses}`}>
                        <h3 className="font-medium">No tags yet</h3>
                        <p className="text-sm text-muted-foreground">
                            Make one tag per car, motorbike or flat, e.g.
                            "Golf", "Motorbike", "Flat Ljubljana". You'll then
                            see everything each one cost, split by category
                            (fuel, insurance, repairs…).
                        </p>
                    </div>
                ) : null}

                <NewTagForm
                    key={tags.length}
                    colors={colors}
                    usedColors={usedColors}
                />
            </div>
        </AppLayout>
    );
}
