import { Head, router, useForm } from '@inertiajs/react';
import Pencil from 'lucide-react/dist/esm/icons/pencil.js';
import Trash2 from 'lucide-react/dist/esm/icons/trash-2.js';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import {
    BudgetRing,
    HeroFigure,
    IconTile,
    MonthPicker,
    PageHeader,
    Panel,
    useLocale,
} from '@/components/kit';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatMonth, formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

type Month = { start: string; end: string };

type Line = {
    id: number;
    amount: number;
    spent: number;
    left: number;
    percent: number;
};

type CategoryLine = Line & { category_id: number; name: string; icon: string };

type Props = {
    month: Month;
    baseCurrency: string;
    spent: number;
    total: Line | null;
    categories: CategoryLine[];
    unbudgeted: Array<{
        id: number;
        name: string;
        icon: string;
        amount: number;
    }>;
    options: Array<{ id: number; name: string; icon: string }>;
};

const inputClasses =
    'amount h-12 w-full rounded-lg border border-control/70 bg-card px-4 text-base text-foreground placeholder:text-muted-foreground';

function AmountForm({
    categoryId,
    initial,
    currency,
    submitLabel,
    onDone,
    className,
}: {
    categoryId: number | null;
    initial?: number;
    currency: string;
    submitLabel: string;
    onDone?: () => void;
    className?: string;
}) {
    const form = useForm({
        amount: initial !== undefined ? initial.toFixed(2) : '',
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.transform((data) => ({
            category_id: categoryId,
            amount: data.amount.replace(',', '.').replace(/[^\d.]/g, ''),
        }));
        form.post('/budgets', {
            preserveScroll: true,
            onSuccess: () => onDone?.(),
        });
    };

    return (
        <form
            onSubmit={submit}
            className={cn('flex flex-wrap items-start gap-3', className)}
        >
            <div className="min-w-0 flex-1 basis-40">
                <label
                    className="sr-only"
                    htmlFor={`budget-${categoryId ?? 'total'}`}
                >
                    Amount in {currency}
                </label>
                <input
                    id={`budget-${categoryId ?? 'total'}`}
                    inputMode="decimal"
                    autoComplete="off"
                    placeholder={`Amount in ${currency}`}
                    value={form.data.amount}
                    onChange={(event) =>
                        form.setData('amount', event.target.value)
                    }
                    className={inputClasses}
                />
                <InputError
                    message={
                        form.errors.amount ??
                        (form.errors as Record<string, string | undefined>)
                            .category_id
                    }
                    className="mt-2"
                />
            </div>
            <Button
                type="submit"
                disabled={form.processing || form.data.amount.trim() === ''}
                className="h-12 rounded-lg px-5 text-[15px]"
            >
                {form.processing ? 'Saving…' : submitLabel}
            </Button>
            {onDone ? (
                <Button
                    type="button"
                    variant="ghost"
                    className="h-12 rounded-lg px-4 text-[15px]"
                    onClick={onDone}
                >
                    Cancel
                </Button>
            ) : null}
        </form>
    );
}

function TotalHero({
    month,
    total,
    spent,
    currency,
}: {
    month: Month;
    total: Line | null;
    spent: number;
    currency: string;
}) {
    const locale = useLocale();
    const [editing, setEditing] = useState(false);
    const monthName = formatMonth(month.start, locale, 'long').split(' ')[0];

    if (total === null || editing) {
        return (
            <section className="rounded-card bg-hero p-5 text-hero-foreground tablet:p-8">
                <h2 className="text-section font-medium">Monthly budget</h2>
                <p className="mt-2 max-w-[60ch] text-[15px] text-hero-muted">
                    {total === null
                        ? `How much do you want to spend in a month, across everything? ${formatMoney(spent, currency, locale)} has gone out in ${monthName} so far.`
                        : 'The same amount applies to every month.'}
                </p>
                <AmountForm
                    categoryId={null}
                    initial={total?.amount}
                    currency={currency}
                    submitLabel={total === null ? 'Set budget' : 'Save'}
                    onDone={
                        total === null ? undefined : () => setEditing(false)
                    }
                    className="mt-6 max-w-lg"
                />
            </section>
        );
    }

    const over = total.left < 0;

    return (
        <section className="rounded-card bg-hero p-5 text-hero-foreground tablet:p-8">
            <div className="flex items-center justify-between gap-4">
                <h2 className="text-section font-medium max-tablet:sr-only">
                    Monthly budget
                </h2>
                <p className="text-lg text-hero-muted tablet:hidden">
                    {over ? 'Over budget by' : 'Left to spend'}
                </p>
                <button
                    type="button"
                    onClick={() => setEditing(true)}
                    className="inline-flex h-11 items-center gap-2 rounded-lg px-3 text-[15px] font-medium text-positive hover:bg-hero-foreground/5"
                >
                    <Pencil className="size-4" strokeWidth={1.75} />
                    Change
                </button>
            </div>
            <div className="flex items-center justify-between gap-4 tablet:mt-4">
                <div className="@container min-w-0 flex-1">
                    <p className="hidden text-lg text-hero-muted tablet:block">
                        {over ? 'Over budget by' : 'Left to spend'}
                    </p>
                    <HeroFigure
                        value={formatMoney(
                            Math.abs(total.left),
                            currency,
                            locale,
                        )}
                        className={over ? 'text-destructive' : undefined}
                    />
                </div>
                <BudgetRing
                    percent={total.percent}
                    size={132}
                    className="size-20 tablet:size-[132px]"
                />
            </div>
            <div className="mt-5 grid grid-cols-2 gap-6 border-t border-hero-foreground/15 pt-4 tablet:mt-7 tablet:pt-5">
                <div>
                    <p className="text-[15px] text-hero-muted">
                        Spent in {monthName}
                    </p>
                    <p className="mt-1 text-[1.375rem] leading-tight font-light amount tablet:text-[1.75rem]">
                        {formatMoney(total.spent, currency, locale)}
                    </p>
                </div>
                <div>
                    <p className="text-[15px] text-hero-muted">Budget</p>
                    <p className="mt-1 text-[1.375rem] leading-tight font-light amount tablet:text-[1.75rem]">
                        {formatMoney(total.amount, currency, locale)}
                    </p>
                </div>
            </div>
        </section>
    );
}

/**
 * One category against its own budget: the track is the whole budget and the fill is
 * what has gone out. Over budget the bar is all red, with a tick where the budget ran
 * out. The row's text says the same in words.
 */
function BudgetBar({ spent, amount }: { spent: number; amount: number }) {
    const over = amount > 0 && spent > amount;
    const used = amount > 0 ? Math.min(spent / amount, 1) : spent > 0 ? 1 : 0;

    return (
        <div className="relative mt-2.5 h-2.5" aria-hidden>
            <div className="absolute inset-0 overflow-hidden rounded-full bg-muted">
                {used > 0 ? (
                    <span
                        className={cn(
                            'absolute inset-y-0 left-0 rounded-full',
                            over ? 'bg-destructive' : 'bg-plot',
                        )}
                        style={{ width: `max(${used * 100}%, 0.625rem)` }}
                    />
                ) : null}
            </div>
            {over ? (
                <span
                    className="absolute -inset-y-1 w-0.5 -translate-x-1/2 rounded-full bg-foreground"
                    style={{ left: `${(amount / spent) * 100}%` }}
                />
            ) : null}
        </div>
    );
}

function CategoryRow({
    line,
    currency,
}: {
    line: CategoryLine;
    currency: string;
}) {
    const locale = useLocale();
    const [editing, setEditing] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const over = line.left < 0;

    return (
        <li className="py-4">
            <div className="flex items-start gap-4">
                <IconTile icon={line.icon} />
                <div className="min-w-0 flex-1">
                    <div className="flex items-center justify-between gap-3">
                        <div className="min-w-0 flex-1 tablet:flex tablet:items-baseline tablet:justify-between tablet:gap-x-4">
                            <p className="truncate text-base font-medium text-foreground">
                                {line.name}
                            </p>
                            <p className="text-[15px] whitespace-nowrap text-muted-foreground amount">
                                <span className="text-foreground">
                                    {formatMoney(line.spent, currency, locale)}
                                </span>{' '}
                                of {formatMoney(line.amount, currency, locale)}
                            </p>
                        </div>
                        <div className="-my-1.5 -mr-2 flex shrink-0 gap-0.5">
                            <button
                                type="button"
                                onClick={() => setEditing((value) => !value)}
                                className="flex size-11 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                                aria-label={`Change the ${line.name} budget`}
                            >
                                <Pencil className="size-4" strokeWidth={1.75} />
                            </button>
                            <button
                                type="button"
                                onClick={() => setConfirming(true)}
                                className="flex size-11 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-destructive"
                                aria-label={`Remove the ${line.name} budget`}
                            >
                                <Trash2 className="size-4" strokeWidth={1.75} />
                            </button>
                        </div>
                    </div>
                    <BudgetBar spent={line.spent} amount={line.amount} />
                    <p
                        className={cn(
                            'mt-2 text-sm',
                            over ? 'text-destructive' : 'text-muted-foreground',
                        )}
                    >
                        {over
                            ? `${formatMoney(-line.left, currency, locale)} over budget · ${line.percent}% used`
                            : `${formatMoney(line.left, currency, locale)} left · ${line.percent}% used`}
                    </p>
                </div>
            </div>
            {editing ? (
                <AmountForm
                    categoryId={line.category_id}
                    initial={line.amount}
                    currency={currency}
                    submitLabel="Save"
                    onDone={() => setEditing(false)}
                    className="mt-4 tablet:pl-14"
                />
            ) : null}
            {confirming ? (
                <div className="mt-4 flex flex-wrap items-center gap-3 rounded-xl bg-error-surface px-4 py-3 text-[15px] text-destructive tablet:ml-14">
                    Remove the {line.name} budget? Its spending stays.
                    <span className="ml-auto flex gap-2">
                        <Button
                            type="button"
                            variant="destructive"
                            className="h-10 rounded-lg"
                            onClick={() =>
                                router.delete(`/budgets/${line.id}`, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Remove
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            className="h-10 rounded-lg"
                            onClick={() => setConfirming(false)}
                        >
                            Keep
                        </Button>
                    </span>
                </div>
            ) : null}
        </li>
    );
}

function AddCategoryBudget({
    options,
    currency,
    preset,
}: {
    options: Props['options'];
    currency: string;
    preset: number | null;
}) {
    const form = useForm({
        category_id: preset !== null ? String(preset) : '',
        amount: '',
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.transform((data) => ({
            category_id: Number(data.category_id),
            amount: data.amount.replace(',', '.').replace(/[^\d.]/g, ''),
        }));
        form.post('/budgets', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    if (options.length === 0) {
        return null;
    }

    return (
        <form
            onSubmit={submit}
            className="mt-6 grid gap-3 border-t border-border pt-6 tablet:grid-cols-[minmax(0,1fr)_minmax(0,12rem)_auto]"
            id="add-category-budget"
        >
            <div>
                <label htmlFor="budget-category" className="sr-only">
                    Category
                </label>
                <select
                    id="budget-category"
                    value={form.data.category_id}
                    onChange={(event) =>
                        form.setData('category_id', event.target.value)
                    }
                    className="h-12 w-full rounded-lg border border-control/70 bg-card px-4 text-base text-foreground"
                >
                    <option value="">Choose a category</option>
                    {options.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.name}
                        </option>
                    ))}
                </select>
                <InputError
                    message={form.errors.category_id}
                    className="mt-2"
                />
            </div>
            <div>
                <label htmlFor="budget-category-amount" className="sr-only">
                    Amount in {currency}
                </label>
                <input
                    id="budget-category-amount"
                    inputMode="decimal"
                    autoComplete="off"
                    placeholder={`Amount in ${currency}`}
                    value={form.data.amount}
                    onChange={(event) =>
                        form.setData('amount', event.target.value)
                    }
                    className={inputClasses}
                />
                <InputError message={form.errors.amount} className="mt-2" />
            </div>
            <Button
                type="submit"
                disabled={
                    form.processing ||
                    form.data.category_id === '' ||
                    form.data.amount.trim() === ''
                }
                className="h-12 rounded-lg px-5 text-[15px]"
            >
                Add budget
            </Button>
        </form>
    );
}

export default function BudgetsIndex({
    month,
    baseCurrency,
    spent,
    total,
    categories,
    unbudgeted,
    options,
}: Props) {
    const locale = useLocale();
    const [preset, setPreset] = useState<number | null>(null);
    const monthName = formatMonth(month.start, locale, 'long').split(' ')[0];

    return (
        <AppLayout>
            <Head title="Budgets" />

            <PageHeader
                title="Budgets"
                description={`Amounts in ${baseCurrency}. Spending in other currencies is converted at the day's rate.`}
                actions={<MonthPicker month={month} quietOnPhone />}
            />

            <div className="space-y-5 desk:space-y-6">
                <TotalHero
                    month={month}
                    total={total}
                    spent={spent}
                    currency={baseCurrency}
                />

                <Panel title="Category budgets">
                    {categories.length === 0 ? (
                        <p className="text-base text-muted-foreground">
                            No category budgets yet. Add one for the categories
                            you want to keep an eye on.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {categories.map((line) => (
                                <CategoryRow
                                    key={line.id}
                                    line={line}
                                    currency={baseCurrency}
                                />
                            ))}
                        </ul>
                    )}
                    <AddCategoryBudget
                        key={preset ?? 'none'}
                        options={options}
                        currency={baseCurrency}
                        preset={preset}
                    />
                </Panel>

                {unbudgeted.length > 0 ? (
                    <Panel title={`Spent without a budget in ${monthName}`}>
                        <ul className="divide-y divide-border">
                            {unbudgeted.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex min-h-16 items-center gap-4 py-3"
                                >
                                    <IconTile icon={row.icon} />
                                    <span className="min-w-0 flex-1 text-base text-foreground">
                                        {row.name}
                                    </span>
                                    <span className="text-base font-medium text-foreground amount">
                                        {formatMoney(
                                            row.amount,
                                            baseCurrency,
                                            locale,
                                        )}
                                    </span>
                                    {options.some(
                                        (option) => option.id === row.id,
                                    ) ? (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setPreset(row.id);
                                                window.requestAnimationFrame(
                                                    () =>
                                                        document
                                                            .getElementById(
                                                                'add-category-budget',
                                                            )
                                                            ?.scrollIntoView({
                                                                behavior:
                                                                    'smooth',
                                                                block: 'center',
                                                            }),
                                                );
                                            }}
                                            className="h-11 shrink-0 rounded-lg px-3 text-[15px] font-medium text-positive hover:bg-muted"
                                        >
                                            Set budget
                                        </button>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    </Panel>
                ) : null}
            </div>
        </AppLayout>
    );
}
