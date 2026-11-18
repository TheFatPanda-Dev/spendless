import { Head, router, useForm, usePage } from '@inertiajs/react';
import Loader2 from 'lucide-react/dist/esm/icons/loader-2.js';
import TriangleAlert from 'lucide-react/dist/esm/icons/triangle-alert.js';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { walletCurrencyOptions } from '@/lib/currency-options';
import { formatDisplayDate } from '@/lib/date-filters';
import type { BreadcrumbItem } from '@/types';

type Mapping = {
    header_row: number;
    date: number | null;
    date_format: string;
    description: number[];
    amount: number | null;
    paid_out: number | null;
    paid_in: number | null;
    balance: number | null;
    reference: number | null;
    flip_sign: boolean;
};

type Props = {
    token: string;
    filename: string;
    statement: {
        format: string;
        format_label: string;
        bank: string | null;
        account_hint: string | null;
        currency: string | null;
        closing_balance: number | null;
        balance_date: string | null;
        warnings: string[];
        needs_mapping: boolean;
        is_generic: boolean;
        columns: string[];
        sample_rows: string[][];
        mapping: Mapping | null;
    };
    summary: {
        total: number;
        new: number;
        updated: number;
        removed: number;
        skipped: number;
        date_from: string | null;
        date_to: string | null;
        new_total: number;
        currency: string;
    };
    rows: Array<{
        date: string;
        description: string;
        amount: number;
        currency: string;
        pending: boolean;
    }>;
    target: string;
    accounts: Array<{ id: number; name: string; currency: string }>;
    suggestion: { name: string; institution: string | null; currency: string };
    dateFormats: string[];
};

const panelClasses =
    'rounded-xl border border-brand/20 bg-linear-to-br from-brand/6 via-card to-card p-4 sm:p-5 dark:from-brand/10';

const selectClasses =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm';

function columnLabel(columns: string[], index: number): string {
    const name = columns[index]?.trim();

    return name ? `${index + 1}. ${name}` : `Column ${index + 1}`;
}

function ColumnSelect({
    id,
    value,
    columns,
    optional = false,
    onChange,
}: {
    id: string;
    value: number | null;
    columns: string[];
    optional?: boolean;
    onChange: (value: number | null) => void;
}) {
    return (
        <select
            id={id}
            className={selectClasses}
            value={value === null ? '' : String(value)}
            onChange={(event) =>
                onChange(
                    event.target.value === ''
                        ? null
                        : Number(event.target.value),
                )
            }
        >
            <option value="">{optional ? 'None' : 'Choose a column'}</option>
            {columns.map((_, index) => (
                <option key={index} value={index}>
                    {columnLabel(columns, index)}
                </option>
            ))}
        </select>
    );
}

export default function ImportPreview({
    token,
    filename,
    statement,
    summary,
    rows,
    target,
    accounts,
    suggestion,
    dateFormats,
}: Props) {
    const page = usePage<{ number_locale?: string }>();
    const locale = page.props.number_locale ?? 'en-GB';
    const money = (value: number, currency: string): string =>
        new Intl.NumberFormat(locale, { style: 'currency', currency }).format(
            value,
        );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Wallets', href: '/wallets' },
        { title: 'Import statement', href: '/imports/new' },
        { title: 'Preview', href: `/imports/${token}` },
    ];

    const columnCount = Math.max(
        statement.columns.length,
        ...statement.sample_rows.map((row) => row.length),
        0,
    );
    const columns = Array.from(
        { length: columnCount },
        (_, index) => statement.columns[index] ?? '',
    );
    const initialMapping: Mapping = statement.mapping ?? {
        header_row: 0,
        date: null,
        date_format: 'auto',
        description: [],
        amount: null,
        paid_out: null,
        paid_in: null,
        balance: null,
        reference: null,
        flip_sign: false,
    };
    const [mapping, setMapping] = useState<Mapping>(initialMapping);
    const [amountMode, setAmountMode] = useState<'single' | 'split'>(
        initialMapping.amount === null &&
            (initialMapping.paid_in !== null ||
                initialMapping.paid_out !== null)
            ? 'split'
            : 'single',
    );
    const [showMapping, setShowMapping] = useState(statement.needs_mapping);
    const [mappingErrors, setMappingErrors] = useState<Record<string, string>>(
        {},
    );
    const [isSavingMapping, setIsSavingMapping] = useState(false);

    const confirmForm = useForm({
        target,
        name: suggestion.name,
        institution: suggestion.institution ?? '',
        currency: suggestion.currency,
        kind: 'current',
        balance: '',
    });

    const isNewAccount = target === 'new';
    const needsBalance = statement.closing_balance === null;

    const changeTarget = (value: string): void => {
        router.get(
            `/imports/${token}`,
            { account: value },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ['summary', 'rows', 'target', 'statement'],
            },
        );
        confirmForm.setData('target', value);
    };

    const applyMapping = (): void => {
        setIsSavingMapping(true);
        router.patch(
            `/imports/${token}/mapping`,
            {
                ...mapping,
                amount: amountMode === 'single' ? mapping.amount : null,
                paid_out: amountMode === 'split' ? mapping.paid_out : null,
                paid_in: amountMode === 'split' ? mapping.paid_in : null,
                account: target,
            },
            {
                preserveScroll: true,
                onError: (errors) => setMappingErrors(errors),
                onSuccess: () => {
                    setMappingErrors({});
                    setShowMapping(false);
                },
                onFinish: () => setIsSavingMapping(false),
            },
        );
    };

    const confirm = (): void => {
        confirmForm.post(`/imports/${token}/confirm`, { preserveScroll: true });
    };

    const cancel = (): void => {
        router.delete(`/imports/${token}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Import preview" />

            <div className="mx-auto w-full max-w-4xl space-y-6">
                <Heading
                    title="Check before importing"
                    description={`${filename} · ${statement.format_label}${statement.bank ? ` · ${statement.bank}` : ''}${statement.account_hint ? ` · ${statement.account_hint}` : ''}`}
                />

                {statement.warnings.map((warning) => (
                    <p
                        key={warning}
                        className="flex items-start gap-2 rounded-md border border-amber-300/70 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-700/50 dark:bg-amber-950/30 dark:text-amber-200"
                    >
                        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                        {warning}
                    </p>
                ))}

                {statement.is_generic ? (
                    <section className={`space-y-4 ${panelClasses}`}>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 className="font-medium">Columns</h3>
                                <p className="text-sm text-muted-foreground">
                                    {statement.needs_mapping
                                        ? "SpendLess doesn't know this layout yet. Tell it which columns to use; it remembers them for this account."
                                        : 'Layout recognised from the column names. Check the preview below; adjust the columns if something looks off.'}
                                </p>
                            </div>
                            {!showMapping ? (
                                <Button
                                    variant="outline"
                                    onClick={() => setShowMapping(true)}
                                >
                                    Adjust columns
                                </Button>
                            ) : null}
                        </div>

                        {columns.length > 0 ? (
                            <div className="overflow-x-auto rounded-lg border border-border">
                                <table className="w-full text-xs">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            {columns.map((_, index) => (
                                                <th
                                                    key={index}
                                                    className="px-2 py-1.5 font-medium whitespace-nowrap"
                                                >
                                                    {columnLabel(
                                                        columns,
                                                        index,
                                                    )}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {statement.sample_rows.map(
                                            (row, rowIndex) => (
                                                <tr
                                                    key={rowIndex}
                                                    className="border-t border-border"
                                                >
                                                    {columns.map((_, index) => (
                                                        <td
                                                            key={index}
                                                            className="px-2 py-1.5 whitespace-nowrap text-muted-foreground"
                                                        >
                                                            {row[index] ?? ''}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        ) : null}

                        {showMapping ? (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="map-date">Date</Label>
                                    <ColumnSelect
                                        id="map-date"
                                        value={mapping.date}
                                        columns={columns}
                                        onChange={(value) =>
                                            setMapping({
                                                ...mapping,
                                                date: value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="map-date-format">
                                        Date format
                                    </Label>
                                    <select
                                        id="map-date-format"
                                        className={selectClasses}
                                        value={mapping.date_format}
                                        onChange={(event) =>
                                            setMapping({
                                                ...mapping,
                                                date_format: event.target.value,
                                            })
                                        }
                                    >
                                        {dateFormats.map((format) => (
                                            <option key={format} value={format}>
                                                {format === 'auto'
                                                    ? 'Detect automatically'
                                                    : format}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>
                                        Description (tick one or more)
                                    </Label>
                                    <div className="flex flex-wrap gap-x-4 gap-y-2">
                                        {columns.map((_, index) => (
                                            <label
                                                key={index}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={mapping.description.includes(
                                                        index,
                                                    )}
                                                    onChange={(event) =>
                                                        setMapping({
                                                            ...mapping,
                                                            description: event
                                                                .target.checked
                                                                ? [
                                                                      ...mapping.description,
                                                                      index,
                                                                  ].sort(
                                                                      (a, b) =>
                                                                          a - b,
                                                                  )
                                                                : mapping.description.filter(
                                                                      (value) =>
                                                                          value !==
                                                                          index,
                                                                  ),
                                                        })
                                                    }
                                                />
                                                {columnLabel(columns, index)}
                                            </label>
                                        ))}
                                    </div>
                                    <InputError
                                        message={mappingErrors.description}
                                    />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>Amounts are in</Label>
                                    <div className="flex flex-wrap gap-4 text-sm">
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="radio"
                                                checked={
                                                    amountMode === 'single'
                                                }
                                                onChange={() =>
                                                    setAmountMode('single')
                                                }
                                            />
                                            One column (minus = money out)
                                        </label>
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="radio"
                                                checked={amountMode === 'split'}
                                                onChange={() =>
                                                    setAmountMode('split')
                                                }
                                            />
                                            Separate "paid out" and "paid in"
                                            columns
                                        </label>
                                    </div>
                                </div>
                                {amountMode === 'single' ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="map-amount">
                                            Amount
                                        </Label>
                                        <ColumnSelect
                                            id="map-amount"
                                            value={mapping.amount}
                                            columns={columns}
                                            onChange={(value) =>
                                                setMapping({
                                                    ...mapping,
                                                    amount: value,
                                                })
                                            }
                                        />
                                        <InputError
                                            message={mappingErrors.amount}
                                        />
                                    </div>
                                ) : (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="map-out">
                                                Paid out
                                            </Label>
                                            <ColumnSelect
                                                id="map-out"
                                                value={mapping.paid_out}
                                                columns={columns}
                                                optional
                                                onChange={(value) =>
                                                    setMapping({
                                                        ...mapping,
                                                        paid_out: value,
                                                    })
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="map-in">
                                                Paid in
                                            </Label>
                                            <ColumnSelect
                                                id="map-in"
                                                value={mapping.paid_in}
                                                columns={columns}
                                                optional
                                                onChange={(value) =>
                                                    setMapping({
                                                        ...mapping,
                                                        paid_in: value,
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={mappingErrors.amount}
                                            />
                                        </div>
                                    </>
                                )}
                                <div className="grid gap-2">
                                    <Label htmlFor="map-balance">
                                        Running balance (optional)
                                    </Label>
                                    <ColumnSelect
                                        id="map-balance"
                                        value={mapping.balance}
                                        columns={columns}
                                        optional
                                        onChange={(value) =>
                                            setMapping({
                                                ...mapping,
                                                balance: value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="map-reference">
                                        Transaction ID (optional)
                                    </Label>
                                    <ColumnSelect
                                        id="map-reference"
                                        value={mapping.reference}
                                        columns={columns}
                                        optional
                                        onChange={(value) =>
                                            setMapping({
                                                ...mapping,
                                                reference: value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="map-header">
                                        Header row number
                                    </Label>
                                    <Input
                                        id="map-header"
                                        type="number"
                                        min={1}
                                        value={mapping.header_row + 1}
                                        onChange={(event) =>
                                            setMapping({
                                                ...mapping,
                                                header_row: Math.max(
                                                    0,
                                                    Number(event.target.value) -
                                                        1,
                                                ),
                                            })
                                        }
                                    />
                                </div>
                                <label className="flex items-center gap-2 self-end text-sm">
                                    <input
                                        type="checkbox"
                                        checked={mapping.flip_sign}
                                        onChange={(event) =>
                                            setMapping({
                                                ...mapping,
                                                flip_sign: event.target.checked,
                                            })
                                        }
                                    />
                                    Flip signs (purchases show as positive, e.g.
                                    some credit card exports)
                                </label>
                                <div className="flex gap-2 sm:col-span-2">
                                    <Button
                                        onClick={applyMapping}
                                        disabled={isSavingMapping}
                                    >
                                        {isSavingMapping ? (
                                            <Loader2 className="size-4 animate-spin" />
                                        ) : null}
                                        Apply columns
                                    </Button>
                                    {!statement.needs_mapping ? (
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                setShowMapping(false)
                                            }
                                        >
                                            Close
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                        ) : null}
                    </section>
                ) : null}

                {!statement.needs_mapping ? (
                    <section className={`space-y-4 ${panelClasses}`}>
                        <div className="grid gap-3 sm:grid-cols-4">
                            {[
                                { label: 'New', value: summary.new },
                                {
                                    label: 'Already imported',
                                    value: summary.updated,
                                },
                                {
                                    label: 'Removed (cancelled)',
                                    value: summary.removed,
                                },
                                {
                                    label: 'Skipped (other currency)',
                                    value: summary.skipped,
                                },
                            ].map((item) => (
                                <div
                                    key={item.label}
                                    className="rounded-lg border border-border bg-background/70 px-3 py-2"
                                >
                                    <p className="text-xs text-muted-foreground">
                                        {item.label}
                                    </p>
                                    <p className="text-lg font-semibold">
                                        {item.value}
                                    </p>
                                </div>
                            ))}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {summary.date_from && summary.date_to
                                ? `Statement covers ${formatDisplayDate(summary.date_from, locale)} – ${formatDisplayDate(summary.date_to, locale)}. `
                                : ''}
                            {summary.new > 0
                                ? `New transactions add up to ${money(summary.new_total, summary.currency)}.`
                                : 'Nothing new in this file.'}
                            {statement.closing_balance !== null &&
                            statement.balance_date
                                ? ` Balance on ${formatDisplayDate(statement.balance_date, locale)}: ${money(statement.closing_balance, summary.currency)}.`
                                : ''}
                        </p>

                        {rows.length > 0 ? (
                            <div className="overflow-x-auto rounded-lg border border-border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                Date
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Description
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Amount
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row, index) => (
                                            <tr
                                                key={index}
                                                className="border-t border-border"
                                            >
                                                <td className="px-3 py-2 whitespace-nowrap text-muted-foreground">
                                                    {formatDisplayDate(
                                                        row.date,
                                                        locale,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {row.description}
                                                    {row.pending ? (
                                                        <span className="ml-2 rounded-full bg-secondary px-2 py-0.5 text-[11px]">
                                                            Pending
                                                        </span>
                                                    ) : null}
                                                </td>
                                                <td
                                                    className={`px-3 py-2 text-right font-medium whitespace-nowrap ${
                                                        row.amount < 0
                                                            ? 'text-rose-600 dark:text-rose-400'
                                                            : 'text-emerald-600 dark:text-emerald-400'
                                                    }`}
                                                >
                                                    {money(
                                                        row.amount,
                                                        row.currency,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {summary.new > rows.length ? (
                                    <p className="border-t border-border px-3 py-2 text-xs text-muted-foreground">
                                        …and {summary.new - rows.length} more.
                                    </p>
                                ) : null}
                            </div>
                        ) : null}
                    </section>
                ) : null}

                <section className={`space-y-4 ${panelClasses}`}>
                    <div className="grid gap-2">
                        <Label htmlFor="target">Import into</Label>
                        <select
                            id="target"
                            className={selectClasses}
                            value={target}
                            onChange={(event) =>
                                changeTarget(event.target.value)
                            }
                        >
                            <option value="new">New account</option>
                            {accounts.map((account) => (
                                <option
                                    key={account.id}
                                    value={String(account.id)}
                                >
                                    {account.name} · {account.currency}
                                </option>
                            ))}
                        </select>
                    </div>

                    {isNewAccount ? (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Account name</Label>
                                <Input
                                    id="name"
                                    value={confirmForm.data.name}
                                    onChange={(event) =>
                                        confirmForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="e.g. Barclays Current"
                                />
                                <InputError message={confirmForm.errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="institution">Bank</Label>
                                <Input
                                    id="institution"
                                    value={confirmForm.data.institution}
                                    onChange={(event) =>
                                        confirmForm.setData(
                                            'institution',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="e.g. Barclays"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="currency">Currency</Label>
                                <select
                                    id="currency"
                                    className={selectClasses}
                                    value={confirmForm.data.currency}
                                    onChange={(event) =>
                                        confirmForm.setData(
                                            'currency',
                                            event.target.value,
                                        )
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
                                <InputError
                                    message={confirmForm.errors.currency}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="kind">Account type</Label>
                                <select
                                    id="kind"
                                    className={selectClasses}
                                    value={confirmForm.data.kind}
                                    onChange={(event) =>
                                        confirmForm.setData(
                                            'kind',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="current">
                                        Current account
                                    </option>
                                    <option value="savings">
                                        Savings account
                                    </option>
                                    <option value="credit_card">
                                        Credit card
                                    </option>
                                </select>
                            </div>
                        </div>
                    ) : null}

                    {needsBalance ? (
                        <div className="grid gap-2 sm:max-w-xs">
                            <Label htmlFor="balance">
                                Current balance
                                {isNewAccount ? '' : ' (optional)'}
                            </Label>
                            <Input
                                id="balance"
                                inputMode="decimal"
                                value={confirmForm.data.balance}
                                onChange={(event) =>
                                    confirmForm.setData(
                                        'balance',
                                        event.target.value,
                                    )
                                }
                                placeholder={
                                    confirmForm.data.kind === 'credit_card'
                                        ? 'e.g. -845.20 (owed)'
                                        : 'e.g. 1234.56'
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                This file has no balance column. Enter today's
                                balance from your bank app
                                {isNewAccount
                                    ? ''
                                    : ', or leave empty to add the new transactions to the current balance'}
                                .
                            </p>
                            <InputError message={confirmForm.errors.balance} />
                        </div>
                    ) : null}

                    <InputError
                        message={
                            (confirmForm.errors as Record<string, string>)
                                .mapping
                        }
                    />

                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            variant="outline"
                            onClick={cancel}
                            disabled={confirmForm.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={confirm}
                            disabled={
                                confirmForm.processing ||
                                statement.needs_mapping
                            }
                        >
                            {confirmForm.processing ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : null}
                            {summary.new > 0
                                ? `Import ${summary.new} transaction${summary.new === 1 ? '' : 's'}`
                                : 'Import'}
                        </Button>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
