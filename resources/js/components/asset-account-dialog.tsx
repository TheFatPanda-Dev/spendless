import { useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { walletCurrencyOptions } from '@/lib/currency-options';

export type IsaTerms = {
    amount: number;
    rate: number;
    years: number;
    start_date: string;
};

type Mode =
    | { type: 'create' }
    | { type: 'edit-isa'; connectionId: number; terms: IsaTerms }
    | { type: 'pension-value'; connectionId: number };

type AssetAccountDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: Mode;
};

const selectClasses =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm';

function today(): string {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

function FieldError({ message }: { message?: string }) {
    return message ? (
        <p className="text-xs text-destructive">{message}</p>
    ) : null;
}

export default function AssetAccountDialog({
    open,
    onOpenChange,
    mode,
}: AssetAccountDialogProps) {
    const initial = useMemo(
        () => ({
            kind: 'cash_isa' as 'cash_isa' | 'pension',
            name: 'Cash ISA',
            institution: 'Al Rayan Bank',
            currency: 'GBP',
            amount: mode.type === 'edit-isa' ? String(mode.terms.amount) : '',
            rate: mode.type === 'edit-isa' ? String(mode.terms.rate) : '',
            years: mode.type === 'edit-isa' ? String(mode.terms.years) : '1',
            start_date:
                mode.type === 'edit-isa' ? mode.terms.start_date : today(),
            value: '',
            date: today(),
        }),
        [mode],
    );
    const form = useForm(initial);

    useEffect(() => {
        if (open) {
            form.setData(initial);
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const kind =
        mode.type === 'create'
            ? form.data.kind
            : mode.type === 'edit-isa'
              ? 'cash_isa'
              : 'pension';
    const amount = Number(form.data.amount);
    const rate = Number(form.data.rate);
    const years = Number(form.data.years);
    const profit =
        amount > 0 && rate >= 0 && years > 0
            ? (amount * rate * years) / 100
            : null;

    const switchKind = (next: 'cash_isa' | 'pension'): void => {
        form.setData({
            ...form.data,
            kind: next,
            name: next === 'pension' ? 'Pension' : 'Cash ISA',
            institution: next === 'pension' ? 'PensionBee' : 'Al Rayan Bank',
        });
    };

    const submit = (): void => {
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (mode.type === 'edit-isa') {
            form.transform((data) => ({
                amount: data.amount,
                rate: data.rate,
                years: data.years,
                start_date: data.start_date,
            }));
            form.patch(`/assets/${mode.connectionId}`, options);

            return;
        }

        if (mode.type === 'pension-value') {
            form.transform((data) => ({ value: data.value, date: data.date }));
            form.post(`/assets/${mode.connectionId}/valuations`, options);

            return;
        }

        form.transform((data) => data);
        form.post('/assets', options);
    };

    const title =
        mode.type === 'create'
            ? 'Add Cash ISA or pension'
            : mode.type === 'edit-isa'
              ? 'Edit Cash ISA'
              : 'Update pension value';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        {kind === 'cash_isa'
                            ? 'Enter the amount, the term and the expected profit rate. Profit is paid at the end of the term; until then SpendLess shows the profit earned so far.'
                            : 'Enter the value shown in your pension app. Update it whenever you check; SpendLess keeps the history.'}
                    </DialogDescription>
                </DialogHeader>

                {mode.type === 'create' ? (
                    <div
                        className="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1"
                        role="tablist"
                    >
                        {(
                            [
                                ['cash_isa', 'Cash ISA'],
                                ['pension', 'Pension'],
                            ] as const
                        ).map(([value, label]) => (
                            <button
                                key={value}
                                type="button"
                                role="tab"
                                aria-selected={form.data.kind === value}
                                onClick={() => switchKind(value)}
                                className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                    form.data.kind === value
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-2">
                    {mode.type === 'create' ? (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="asset-name">Name</Label>
                                <Input
                                    id="asset-name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                />
                                <FieldError message={form.errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="asset-institution">
                                    Provider
                                </Label>
                                <Input
                                    id="asset-institution"
                                    value={form.data.institution}
                                    onChange={(event) =>
                                        form.setData(
                                            'institution',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        </>
                    ) : null}

                    {kind === 'cash_isa' && mode.type !== 'pension-value' ? (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="asset-amount">Amount</Label>
                                <Input
                                    id="asset-amount"
                                    inputMode="decimal"
                                    value={form.data.amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'amount',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="e.g. 20000"
                                />
                                <FieldError message={form.errors.amount} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="asset-rate">
                                    Expected profit rate (% a year)
                                </Label>
                                <Input
                                    id="asset-rate"
                                    inputMode="decimal"
                                    value={form.data.rate}
                                    onChange={(event) =>
                                        form.setData('rate', event.target.value)
                                    }
                                    placeholder="e.g. 4.25"
                                />
                                <FieldError message={form.errors.rate} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="asset-start">Start date</Label>
                                <Input
                                    id="asset-start"
                                    type="date"
                                    value={form.data.start_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'start_date',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError message={form.errors.start_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="asset-years">
                                    Term (years)
                                </Label>
                                <Input
                                    id="asset-years"
                                    inputMode="decimal"
                                    value={form.data.years}
                                    onChange={(event) =>
                                        form.setData(
                                            'years',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="e.g. 2"
                                />
                                <FieldError message={form.errors.years} />
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="asset-value">Value</Label>
                                <Input
                                    id="asset-value"
                                    inputMode="decimal"
                                    value={form.data.value}
                                    onChange={(event) =>
                                        form.setData(
                                            'value',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="e.g. 18450.32"
                                />
                                <FieldError message={form.errors.value} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="asset-date">Value on</Label>
                                <Input
                                    id="asset-date"
                                    type="date"
                                    max={today()}
                                    value={form.data.date}
                                    onChange={(event) =>
                                        form.setData('date', event.target.value)
                                    }
                                />
                                <FieldError message={form.errors.date} />
                            </div>
                        </>
                    )}

                    {mode.type === 'create' ? (
                        <div className="grid gap-2">
                            <Label htmlFor="asset-currency">Currency</Label>
                            <select
                                id="asset-currency"
                                className={selectClasses}
                                value={form.data.currency}
                                onChange={(event) =>
                                    form.setData('currency', event.target.value)
                                }
                            >
                                {walletCurrencyOptions.map((option) => (
                                    <option
                                        key={option.code}
                                        value={option.code}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    ) : null}
                </div>

                {kind === 'cash_isa' &&
                mode.type !== 'pension-value' &&
                profit !== null ? (
                    <p className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                        Profit at the end of the term:{' '}
                        <span className="font-medium text-foreground">
                            {new Intl.NumberFormat(undefined, {
                                style: 'currency',
                                currency: form.data.currency || 'GBP',
                            }).format(profit)}
                        </span>{' '}
                        (total{' '}
                        {new Intl.NumberFormat(undefined, {
                            style: 'currency',
                            currency: form.data.currency || 'GBP',
                        }).format(amount + profit)}
                        ).
                    </p>
                ) : null}

                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={form.processing}
                        onClick={submit}
                    >
                        {form.processing
                            ? 'Saving…'
                            : mode.type === 'create'
                              ? 'Add'
                              : 'Save'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
