import { router } from '@inertiajs/react';
import Check from 'lucide-react/dist/esm/icons/check.js';
import Search from 'lucide-react/dist/esm/icons/search.js';
import { useMemo, useState } from 'react';
import type { CategoryOptionLite } from '@/components/add-transaction-sheet';
import { CategoryIcon } from '@/components/category-icon';
import type { TagColorOption, TagOption } from '@/components/tag-chip';
import { TransactionTagPicker } from '@/components/transaction-tag-picker';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type EditableTransaction = {
    id: number;
    account_id: number;
    name: string | null;
    merchant_name: string | null;
    category_id: number | null;
    category_type: 'expense' | 'income';
    tag_ids: number[];
};

const fieldClasses =
    'h-11 w-full rounded-lg border border-control/70 bg-card px-3.5 text-[15px] text-foreground placeholder:text-muted-foreground';

function sameIds(left: number[], right: number[]): boolean {
    return (
        left.length === right.length && left.every((id) => right.includes(id))
    );
}

/** Searchable category list: type to filter, click to pick. */
function CategoryList({
    options,
    selectedId,
    onSelect,
}: {
    options: { expense: CategoryOptionLite[]; income: CategoryOptionLite[] };
    selectedId: number | null;
    onSelect: (option: CategoryOptionLite) => void;
}) {
    const [query, setQuery] = useState('');
    const groups = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return (['expense', 'income'] as const).map((type) => ({
            type,
            items:
                needle === ''
                    ? options[type]
                    : options[type].filter((option) =>
                          option.path.toLowerCase().includes(needle),
                      ),
        }));
    }, [options, query]);

    return (
        <div className="rounded-xl border border-border bg-card">
            <div className="flex items-center gap-2 border-b border-border px-3">
                <Search
                    className="size-4 text-muted-foreground"
                    strokeWidth={1.75}
                />
                <input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Search categories"
                    aria-label="Search categories"
                    className="h-11 w-full bg-transparent text-[15px] outline-none placeholder:text-muted-foreground"
                />
            </div>
            <div className="max-h-64 overflow-y-auto p-1.5">
                {groups.map((group) =>
                    group.items.length === 0 ? null : (
                        <div key={group.type} className="mb-1">
                            <p className="px-2.5 pt-2 pb-1 text-xs font-medium tracking-[0.12em] text-muted-foreground uppercase">
                                {group.type === 'expense'
                                    ? 'Expense'
                                    : 'Income'}
                            </p>
                            {group.items.map((option) => {
                                const selected = option.id === selectedId;

                                return (
                                    <button
                                        key={option.id}
                                        type="button"
                                        onClick={() => onSelect(option)}
                                        className={cn(
                                            'flex min-h-10 w-full items-center gap-2.5 rounded-lg pr-2.5 text-left text-[15px]',
                                            selected
                                                ? 'bg-accent text-accent-foreground'
                                                : 'text-foreground hover:bg-muted',
                                        )}
                                        style={{
                                            paddingLeft: `${0.625 + (query.trim() === '' ? (option.depth ?? 0) * 1.25 : 0)}rem`,
                                        }}
                                    >
                                        <CategoryIcon
                                            name={option.icon}
                                            iconSize={16}
                                            strokeWidth={1.75}
                                        />
                                        <span className="flex-1 truncate">
                                            {query.trim() === ''
                                                ? option.name
                                                : option.path.replaceAll(
                                                      ' / ',
                                                      ' › ',
                                                  )}
                                        </span>
                                        {selected ? (
                                            <Check
                                                className="size-4"
                                                strokeWidth={2}
                                            />
                                        ) : null}
                                    </button>
                                );
                            })}
                        </div>
                    ),
                )}
                {groups.every((group) => group.items.length === 0) ? (
                    <p className="px-2.5 py-3 text-[15px] text-muted-foreground">
                        No category matches “{query}”.
                    </p>
                ) : null}
            </div>
        </div>
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
    categories: { expense: CategoryOptionLite[]; income: CategoryOptionLite[] };
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
