import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { CategoryOptionLite } from '@/components/add-transaction-sheet';
import { CategoryOptionList } from '@/components/category-picker';
import type { PickerCategory, PickerGroup } from '@/components/category-picker';
import type { TagColorOption, TagOption } from '@/components/tag-chip';
import { TransactionTagPicker } from '@/components/transaction-tag-picker';
import { Button } from '@/components/ui/button';

export type EditableTransaction = {
    id: number;
    account_id: number;
    name: string | null;
    merchant_name: string | null;
    category_id: number | null;
    category_type: 'expense' | 'income' | 'transfer';
    amount: number;
    tag_ids: number[];
};

const fieldClasses =
    'h-11 w-full rounded-lg border border-control/70 bg-card px-3.5 text-[15px] text-foreground placeholder:text-muted-foreground';

function sameIds(left: number[], right: number[]): boolean {
    return (
        left.length === right.length && left.every((id) => right.includes(id))
    );
}

/** The editor's category list: income first for money in, with spending as money back. */
function CategoryList({
    options,
    selectedId,
    onSelect,
    incoming = false,
}: {
    options: {
        expense: CategoryOptionLite[];
        income: CategoryOptionLite[];
        transfer?: CategoryOptionLite[];
    };
    selectedId: number | null;
    onSelect: (option: PickerCategory) => void;
    incoming?: boolean;
}) {
    const groups = useMemo<PickerGroup[]>(() => {
        const expense = {
            key: 'expense',
            label: incoming ? 'Money back · lowers spending' : 'Expense',
            options: options.expense,
        };
        const income = {
            key: 'income',
            label: 'Income',
            options: options.income,
        };

        const transfer = {
            key: 'transfer',
            label: 'Transfer · not income or spending',
            options: options.transfer ?? [],
        };

        return incoming
            ? [income, expense, transfer]
            : [expense, income, transfer];
    }, [options, incoming]);

    return (
        <CategoryOptionList
            groups={groups}
            selectedId={selectedId}
            onSelect={(option) => (option ? onSelect(option) : undefined)}
            className="max-h-80 rounded-xl border border-border bg-card"
        />
    );
}

/**
 * Edit a transaction in place: category, how it's named, and its tags.
 * Changes save to the transaction's own account and come back to this page.
 */
export function TransactionEditor({
    transaction,
    categories,
    tags,
    colors,
    reload,
    onClose,
    from = 'activity',
}: {
    transaction: EditableTransaction;
    categories: {
        expense: CategoryOptionLite[];
        income: CategoryOptionLite[];
        transfer?: CategoryOptionLite[];
    };
    tags: TagOption[];
    colors: TagColorOption[];
    reload: string[];
    onClose: () => void;
    from?: 'activity' | 'account';
}) {
    const [categoryId, setCategoryId] = useState<number | null>(
        transaction.category_id,
    );
    const [merchant, setMerchant] = useState(transaction.merchant_name ?? '');
    const [name, setName] = useState(transaction.name ?? '');
    const [tagIds, setTagIds] = useState<number[]>(transaction.tag_ids);
    const [saving, setSaving] = useState(false);
    const colorMap = useMemo(
        () => new Map(colors.map((color) => [color.value, color])),
        [colors],
    );

    const changed =
        categoryId !== transaction.category_id ||
        merchant.trim() !== (transaction.merchant_name ?? '') ||
        name.trim() !== (transaction.name ?? '') ||
        !sameIds(tagIds, transaction.tag_ids);

    const save = (): void => {
        const payload: Record<string, string | number | number[] | null> = {};

        if (categoryId !== transaction.category_id && categoryId !== null) {
            payload.category_id = categoryId;
        }

        if (merchant.trim() !== (transaction.merchant_name ?? '')) {
            payload.merchant_name = merchant.trim() || null;
        }

        if (name.trim() !== (transaction.name ?? '')) {
            payload.name = name.trim() || null;
        }

        if (!sameIds(tagIds, transaction.tag_ids)) {
            payload.tag_ids = tagIds;
        }

        router.patch(
            `/accounts/${transaction.account_id}/transactions/${transaction.id}?from=${from}`,
            payload,
            {
                preserveScroll: true,
                preserveState: true,
                only: reload,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: onClose,
            },
        );
    };

    return (
        <div className="grid gap-5 rounded-2xl border border-border bg-background p-4 tablet:p-5 desk:grid-cols-2">
            <div className="grid content-start gap-2">
                <span className="text-[15px] text-muted-foreground">
                    Category
                </span>
                <CategoryList
                    options={categories}
                    selectedId={categoryId}
                    onSelect={(option) => setCategoryId(option.id)}
                    incoming={transaction.amount > 0}
                />
            </div>
            <div className="grid content-start gap-4">
                <label className="grid gap-2">
                    <span className="text-[15px] text-muted-foreground">
                        Merchant
                    </span>
                    <input
                        value={merchant}
                        onChange={(event) => setMerchant(event.target.value)}
                        maxLength={255}
                        className={fieldClasses}
                    />
                </label>
                <label className="grid gap-2">
                    <span className="text-[15px] text-muted-foreground">
                        Description
                    </span>
                    <input
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        maxLength={255}
                        className={fieldClasses}
                    />
                </label>
                <TransactionTagPicker
                    tags={tags}
                    selectedIds={tagIds}
                    onToggle={(tagId) =>
                        setTagIds((current) =>
                            current.includes(tagId)
                                ? current.filter((id) => id !== tagId)
                                : [...current, tagId],
                        )
                    }
                    onCreated={(tag) =>
                        setTagIds((current) =>
                            current.includes(tag.id)
                                ? current
                                : [...current, tag.id],
                        )
                    }
                    colors={colors}
                    colorMap={colorMap}
                    disabled={saving}
                />
                <div className="mt-1 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-11 rounded-lg px-4 text-[15px]"
                        onClick={onClose}
                        disabled={saving}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        className="h-11 rounded-lg px-5 text-[15px]"
                        onClick={save}
                        disabled={!changed || saving}
                    >
                        {saving ? 'Saving…' : 'Save changes'}
                    </Button>
                </div>
            </div>
        </div>
    );
}
