import { Link, useForm } from '@inertiajs/react';
import Loader2 from 'lucide-react/dist/esm/icons/loader-2.js';
import { useMemo } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

export type CategoryOptionLite = {
    id: number;
    name: string;
    path: string;
    type: 'expense' | 'income';
    icon: string;
    depth?: number;
};

export type AddTransactionOptions = {
    accounts: Array<{ id: number; name: string; currency: string }>;
    categories: { expense: CategoryOptionLite[]; income: CategoryOptionLite[] };
};

const fieldClasses =
    'h-12 w-full rounded-lg border border-control/70 bg-card px-4 text-base text-foreground placeholder:text-muted-foreground';

function today(): string {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

/**
 * Add an expense or income to a cash wallet (kit screen "Add transaction").
 * Bank and imported wallets get their transactions from the bank or the statement.
 */
export function AddTransactionSheet({
    open,
    onOpenChange,
    options,
    from,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: AddTransactionOptions;
    from: 'overview' | 'activity' | 'account';
}) {
    const form = useForm({
        type: 'expense' as 'expense' | 'income',
        amount: '',
        merchant_name: '',
        category_id: '',
        date: today(),
        account_id: options.accounts[0] ? String(options.accounts[0].id) : '',
    });
    const categories = options.categories[form.data.type];
    const account = options.accounts.find(
        (candidate) => String(candidate.id) === form.data.account_id,
    );
    const currencySymbol = useMemo(() => {
        try {
            return (
                new Intl.NumberFormat('en-GB', {
                    style: 'currency',
                    currency: account?.currency ?? 'EUR',
                })
                    .formatToParts(0)
                    .find((part) => part.type === 'currency')?.value ?? ''
            );
        } catch {
            return '';
        }
    }, [account?.currency]);

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        if (!account) {
            return;
        }

        form.transform((data) => ({
            category_id:
                data.category_id === '' ? null : Number(data.category_id),
            amount: data.amount.replace(',', '.').replace(/[^\d.]/g, ''),
            date: data.date,
            merchant_name: data.merchant_name.trim() || null,
            transaction_name: data.merchant_name.trim() || null,
        }));
        form.post(`/accounts/${account.id}/manual-transactions?from=${from}`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('amount', 'merchant_name', 'category_id');
                onOpenChange(false);
            },
        });
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="w-full gap-0 overflow-y-auto bg-background p-0 sm:max-w-md"
            >
                <SheetHeader className="border-b border-border px-6 py-5">
                    <SheetTitle className="text-section font-medium">
                        Add transaction
                    </SheetTitle>
                    <SheetDescription>
                        For a cash wallet. Banks and imports add their own.
                    </SheetDescription>
                </SheetHeader>

                {options.accounts.length === 0 ? (
                    <div className="space-y-4 px-6 py-8">
                        <p className="text-base text-foreground">
                            You don't have a cash wallet yet.
                        </p>
                        <p className="text-[15px] text-muted-foreground">
                            Create one for cash or anything you track by hand,
                            then add transactions to it here.
                        </p>
                        <Button
                            asChild
                            className="h-12 w-full rounded-lg text-base"
                        >
                            <Link href="/wallets">Go to wallets</Link>
                        </Button>
                    </div>
                ) : (
                    <form
                        onSubmit={submit}
                        className="flex flex-1 flex-col gap-6 px-6 py-6"
                    >
                        <div
                            className="grid grid-cols-2 gap-1 rounded-xl border border-border bg-card p-1"
                            role="radiogroup"
                            aria-label="Type"
                        >
                            {(['expense', 'income'] as const).map((type) => (
                                <button
                                    key={type}
                                    type="button"
                                    role="radio"
                                    aria-checked={form.data.type === type}
                                    onClick={() => {
                                        form.setData((data) => ({
                                            ...data,
                                            type,
                                            category_id: '',
                                        }));
                                    }}
                                    className={cn(
                                        'h-11 rounded-lg text-base transition-colors',
                                        form.data.type === type
                                            ? 'bg-accent font-medium text-accent-foreground'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {type === 'expense' ? 'Expense' : 'Income'}
                                </button>
                            ))}
                        </div>

                        <div>
                            <label
                                htmlFor="add-amount"
                                className="text-[15px] text-muted-foreground"
                            >
                                Amount
                            </label>
                            <div className="mt-1 flex items-baseline gap-1 border-b border-border pb-2">
                                <span className="text-5xl font-light text-foreground">
                                    {currencySymbol}
                                </span>
                                <input
                                    id="add-amount"
                                    inputMode="decimal"
                                    autoComplete="off"
                                    value={form.data.amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'amount',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="0.00"
                                    className="w-full bg-transparent text-5xl font-light text-foreground amount outline-none placeholder:text-muted-foreground/60"
                                />
                            </div>
                            <InputError
                                message={form.errors.amount}
                                className="mt-2"
                            />
                        </div>

                        <div className="grid gap-2">
                            <label
                                htmlFor="add-merchant"
                                className="text-[15px] text-muted-foreground"
                            >
                                Merchant
                            </label>
                            <input
                                id="add-merchant"
                                value={form.data.merchant_name}
                                onChange={(event) =>
                                    form.setData(
                                        'merchant_name',
                                        event.target.value,
                                    )
                                }
                                className={fieldClasses}
                                maxLength={255}
                            />
                        </div>

                        <div className="grid gap-2">
                            <label
                                htmlFor="add-category"
                                className="text-[15px] text-muted-foreground"
                            >
                                Category
                            </label>
                            <select
                                id="add-category"
                                value={form.data.category_id}
                                onChange={(event) =>
                                    form.setData(
                                        'category_id',
                                        event.target.value,
                                    )
                                }
                                className={fieldClasses}
                            >
                                <option value="">Choose a category</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.id}
                                    >
                                        {`${' '.repeat(category.depth ?? 0)}${category.name}`}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.category_id} />
                        </div>

                        <div className="grid gap-2">
                            <label
                                htmlFor="add-date"
                                className="text-[15px] text-muted-foreground"
                            >
                                Date
                            </label>
                            <input
                                id="add-date"
                                type="date"
                                value={form.data.date}
                                onChange={(event) =>
                                    form.setData('date', event.target.value)
                                }
                                className={fieldClasses}
                            />
                            <InputError message={form.errors.date} />
                        </div>

                        {options.accounts.length > 1 ? (
                            <div className="grid gap-2">
                                <label
                                    htmlFor="add-wallet"
                                    className="text-[15px] text-muted-foreground"
                                >
                                    Wallet
                                </label>
                                <select
                                    id="add-wallet"
                                    value={form.data.account_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'account_id',
                                            event.target.value,
                                        )
                                    }
                                    className={fieldClasses}
                                >
                                    {options.accounts.map((option) => (
                                        <option
                                            key={option.id}
                                            value={option.id}
                                        >
                                            {option.name} · {option.currency}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ) : null}

                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="mt-auto h-14 w-full rounded-xl text-base font-medium"
                        >
                            {form.processing ? (
                                <>
                                    <Loader2 className="size-5 animate-spin" />
                                    Saving…
                                </>
                            ) : form.data.type === 'expense' ? (
                                'Save expense'
                            ) : (
                                'Save income'
                            )}
                        </Button>
                    </form>
                )}
            </SheetContent>
        </Sheet>
    );
}
