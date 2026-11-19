import { Head, useForm, usePage } from '@inertiajs/react';
import FileUp from 'lucide-react/dist/esm/icons/file-up.js';
import Loader2 from 'lucide-react/dist/esm/icons/loader-2.js';
import { useState } from 'react';
import type { ChangeEvent, DragEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDate } from '@/lib/date-filters';
import type { BreadcrumbItem } from '@/types';

type ImportAccount = {
    id: number;
    name: string;
    institution: string | null;
    currency: string;
    last_import: { date_to: string | null } | null;
};

type Props = {
    accounts: ImportAccount[];
    selectedAccountId: number | null;
    extensions: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wallets', href: '/wallets' },
    { title: 'Import statement', href: '/imports/new' },
];

const panelClasses = 'rounded-card border border-border bg-card p-5 tablet:p-6';

const selectClasses =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm';

const BANK_TIPS: Array<{ bank: string; steps: string }> = [
    {
        bank: 'Revolut',
        steps: 'App → your GBP account → ⋯ → Statement → Excel → choose the period → Get statement.',
    },
    {
        bank: 'Barclays',
        steps: 'Online Banking → the account → Export / Download transactions → choose "Microsoft Money (.ofx)" if offered, otherwise CSV.',
    },
    {
        bank: 'Nationwide',
        steps: 'Internet Bank → the account → View statements → Download transactions → CSV.',
    },
    {
        bank: 'Santander (credit card)',
        steps: 'Online Banking → the card → Download transactions → "Microsoft Money" (OFX), or Text (.txt).',
    },
    {
        bank: 'Any other bank',
        steps: "Download a CSV or OFX file. If SpendLess doesn't recognise the layout, you'll pick the date, description and amount columns once and it's remembered.",
    },
];

export default function ImportCreate({
    accounts,
    selectedAccountId,
    extensions,
}: Props) {
    const page = usePage<{
        flash?: { error?: string | null };
        number_locale?: string;
    }>();
    const locale = page.props.number_locale ?? 'en-GB';
    const [isDragging, setIsDragging] = useState(false);
    const form = useForm<{ file: File | null; account_id: string }>({
        file: null,
        account_id: selectedAccountId !== null ? String(selectedAccountId) : '',
    });

    const pickFile = (file: File | null | undefined): void => {
        form.setData('file', file ?? null);
        form.clearErrors('file');
    };

    const submit = (): void => {
        form.post('/imports', { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Import statement" />

            <div className="mx-auto w-full max-w-3xl space-y-6">
                <Heading
                    variant="page"
                    title="Import a bank statement"
                    description="For banks SpendLess can't connect to directly. Download a statement from your bank and drop it here; you'll see what changes before anything is saved, and transactions already imported are skipped."
                />

                {page.props.flash?.error ? (
                    <p className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                        {page.props.flash.error}
                    </p>
                ) : null}

                <section className={`space-y-4 ${panelClasses}`}>
                    <div className="grid gap-2">
                        <Label htmlFor="account">Import into</Label>
                        <select
                            id="account"
                            className={selectClasses}
                            value={form.data.account_id}
                            onChange={(event) =>
                                form.setData('account_id', event.target.value)
                            }
                        >
                            <option value="">
                                New account (choose on the next step)
                            </option>
                            {accounts.map((account) => (
                                <option key={account.id} value={account.id}>
                                    {account.name} · {account.currency}
                                    {account.last_import?.date_to
                                        ? ` · up to ${formatDisplayDate(account.last_import.date_to, locale)}`
                                        : ''}
                                </option>
                            ))}
                        </select>
                    </div>

                    <label
                        htmlFor="file"
                        onDragOver={(event: DragEvent<HTMLLabelElement>) => {
                            event.preventDefault();
                            setIsDragging(true);
                        }}
                        onDragLeave={() => setIsDragging(false)}
                        onDrop={(event: DragEvent<HTMLLabelElement>) => {
                            event.preventDefault();
                            setIsDragging(false);
                            pickFile(event.dataTransfer.files[0]);
                        }}
                        className={`flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-10 text-center transition-colors ${
                            isDragging
                                ? 'border-brand bg-brand/10'
                                : 'border-border hover:border-border hover:bg-brand/5'
                        }`}
                    >
                        <FileUp className="size-7 text-brand" />
                        <span className="text-sm font-medium text-foreground">
                            {form.data.file
                                ? form.data.file.name
                                : 'Drop the statement file here, or click to choose'}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {extensions
                                .map((extension) => `.${extension}`)
                                .join(', ')}{' '}
                            · up to 10 MB
                        </span>
                        <input
                            id="file"
                            type="file"
                            className="sr-only"
                            accept={extensions
                                .map((extension) => `.${extension}`)
                                .join(',')}
                            onChange={(event: ChangeEvent<HTMLInputElement>) =>
                                pickFile(event.target.files?.[0])
                            }
                        />
                    </label>
                    <InputError message={form.errors.file} />

                    <div className="flex justify-end">
                        <Button
                            type="button"
                            onClick={submit}
                            disabled={
                                form.data.file === null || form.processing
                            }
                        >
                            {form.processing ? (
                                <>
                                    <Loader2 className="size-4 animate-spin" />
                                    Reading file…
                                </>
                            ) : (
                                'Preview import'
                            )}
                        </Button>
                    </div>
                </section>

                <section className={`space-y-3 ${panelClasses}`}>
                    <h3 className="font-medium">
                        Where to download a statement
                    </h3>
                    <p className="text-xs text-muted-foreground">
                        Menu names change now and then. Look for "Statement",
                        "Export" or "Download transactions".
                    </p>
                    <ul className="space-y-2 text-sm">
                        {BANK_TIPS.map((tip) => (
                            <li key={tip.bank}>
                                <span className="font-medium text-foreground">
                                    {tip.bank}:
                                </span>{' '}
                                <span className="text-muted-foreground">
                                    {tip.steps}
                                </span>
                            </li>
                        ))}
                    </ul>
                    <p className="text-xs text-muted-foreground">
                        Tip: download overlapping periods freely. SpendLess
                        recognises transactions it already has. Stick to one
                        file type per account (e.g. always OFX), because
                        different formats describe the same payment differently.
                    </p>
                </section>
            </div>
        </AppLayout>
    );
}
