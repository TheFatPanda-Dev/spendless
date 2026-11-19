import { useMemo } from 'react';
import type { ReactNode, Ref } from 'react';
import { IconTile, useLocale } from '@/components/kit';
import { TagChip } from '@/components/tag-chip';
import type { TagColorOption, TagOption } from '@/components/tag-chip';
import { amountTone, formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

export type LedgerTransaction = {
    id: number;
    account?: string | null;
    name: string | null;
    merchant_name: string | null;
    counterparty: string | null;
    category: string;
    category_icon: string;
    category_color?: string | null;
    category_type?: 'expense' | 'income' | 'transfer';
    amount: number;
    currency: string;
    date: string | null;
    pending: boolean;
    tag_ids: number[];
};

/** Icon, merchant, category, description, amount: shared by day headers and rows. */
const COLUMNS =
    'tablet:grid-cols-[2.5rem_minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,1.6fr)_8.5rem]';

/** Column titles, shown in the first day's header row from tablet up. */
const COLUMN_LABEL =
    'hidden text-xs font-medium tracking-[0.12em] text-muted-foreground uppercase tablet:block';

export function leafCategory(path: string): string {
    const parts = path.split(' / ');

    return parts[parts.length - 1] ?? path;
}

export function payeeOf(transaction: LedgerTransaction): string {
    return (
        transaction.merchant_name ??
        transaction.counterparty ??
        transaction.name ??
        'Transaction'
    );
}

function dayLabel(date: string, locale: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Intl.DateTimeFormat(locale, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, month - 1, day)));
}

/**
 * The transaction list shared by Activity and an account page. From tablet up it is
 * one panel with day headers as rows and fixed icon and amount columns, so category
 * and wallet line up across days. On phones the rows sit on the canvas with a
 * hairline between days (kit phone-activity). A row opens its editor in place.
 *
 * Columns: merchant, category, the bank's description (when it says more than the
 * merchant) and the amount. Phones show "Category · description" under the merchant.
 */
export function TransactionLedger<T extends LedgerTransaction>({
    transactions,
    openId,
    onToggle,
    renderEditor,
    tags,
    categoryColors,
    openRowRef,
}: {
    transactions: T[];
    openId: number | null;
    onToggle: (id: number | null) => void;
    renderEditor: (transaction: T) => ReactNode;
    tags: TagOption[];
    categoryColors: TagColorOption[];
    openRowRef?: Ref<HTMLLIElement>;
}) {
    const locale = useLocale();
    const tagsById = useMemo(
        () => new Map(tags.map((tag) => [tag.id, tag])),
        [tags],
    );
    const colorMap = useMemo(
        () => new Map(categoryColors.map((color) => [color.value, color])),
        [categoryColors],
    );
    const groups = useMemo(() => {
        const byDay = new Map<string, T[]>();

        for (const transaction of transactions) {
            const key = transaction.date ?? 'Undated';
            byDay.set(key, [...(byDay.get(key) ?? []), transaction]);
        }

        return [...byDay.entries()];
    }, [transactions]);

    return (
        <div className="tablet:overflow-hidden tablet:rounded-card tablet:border tablet:border-border tablet:bg-card">
            {groups.map(([day, items], groupIndex) => (
                <section
                    key={day}
                    aria-label={day === 'Undated' ? day : dayLabel(day, locale)}
                    className="border-border max-tablet:border-b max-tablet:pb-3 max-tablet:last:border-b-0"
                >
                    <div
                        className={cn(
                            'pt-6 pb-1 tablet:grid tablet:items-center tablet:gap-x-4 tablet:border-b tablet:border-border tablet:bg-muted/45 tablet:px-5 tablet:py-2.5',
                            COLUMNS,
                            groupIndex === 0
                                ? 'max-tablet:pt-1'
                                : 'tablet:border-t',
                        )}
                    >
                        <h2 className="text-sm font-medium tracking-[0.1em] text-muted-foreground uppercase tablet:col-span-2">
                            {day === 'Undated' ? day : dayLabel(day, locale)}
                        </h2>
                        {groupIndex === 0 ? (
                            <>
                                <span className={COLUMN_LABEL}>Category</span>
                                <span className={COLUMN_LABEL}>
                                    Description
                                </span>
                                <span
                                    className={cn(COLUMN_LABEL, 'text-right')}
                                >
                                    Amount
                                </span>
                            </>
                        ) : null}
                    </div>
                    <ul className="tablet:divide-y tablet:divide-border">
                        {items.map((transaction) => {
                            const open = openId === transaction.id;
                            const income = transaction.amount > 0;
                            const payee = payeeOf(transaction);
                            const category = leafCategory(transaction.category);
                            const description =
                                transaction.name &&
                                transaction.name.trim().toLowerCase() !==
                                    payee.trim().toLowerCase()
                                    ? transaction.name
                                    : null;
                            // Money in under a spending category: a friend's share, a refund.
                            const paidBack =
                                transaction.amount > 0 &&
                                transaction.category_type === 'expense';
                            const phoneMeta = [
                                paidBack ? `${category} · paid back` : category,
                                description,
                                transaction.pending ? 'pending' : null,
                            ]
                                .filter(Boolean)
                                .join(' · ');

                            return (
                                <li
                                    key={transaction.id}
                                    ref={open ? openRowRef : undefined}
                                >
                                    <button
                                        type="button"
                                        aria-expanded={open}
                                        onClick={() =>
                                            onToggle(
                                                open ? null : transaction.id,
                                            )
                                        }
                                        className={cn(
                                            '-mx-3 grid w-[calc(100%+1.5rem)] grid-cols-[2.5rem_minmax(0,1fr)_auto] items-center gap-x-4 rounded-xl px-3 py-3 text-left transition-colors tablet:mx-0 tablet:w-full tablet:rounded-none tablet:px-5 tablet:py-3.5',
                                            COLUMNS,
                                            open
                                                ? 'bg-accent/40'
                                                : 'hover:bg-muted/60',
                                        )}
                                    >
                                        <IconTile
                                            icon={transaction.category_icon}
                                            tint={transaction.category_color}
                                            income={income}
                                        />
                                        <span className="min-w-0">
                                            <span className="block truncate text-base font-medium text-foreground">
                                                {payee}
                                            </span>
                                            <span className="block truncate text-sm text-muted-foreground tablet:hidden">
                                                {phoneMeta}
                                            </span>
                                            {transaction.tag_ids.length > 0 ? (
                                                <span className="mt-1 flex flex-wrap gap-1">
                                                    {transaction.tag_ids.map(
                                                        (id) => {
                                                            const tag =
                                                                tagsById.get(
                                                                    id,
                                                                );

                                                            return tag ? (
                                                                <TagChip
                                                                    key={id}
                                                                    tag={tag}
                                                                    colorMap={
                                                                        colorMap
                                                                    }
                                                                />
                                                            ) : null;
                                                        },
                                                    )}
                                                </span>
                                            ) : null}
                                        </span>
                                        <span className="hidden truncate text-base text-muted-foreground tablet:block">
                                            {category}
                                            {paidBack ? ' · paid back' : ''}
                                            {transaction.pending
                                                ? ' · pending'
                                                : ''}
                                        </span>
                                        <span
                                            className="hidden truncate text-base text-muted-foreground tablet:block"
                                            title={description ?? undefined}
                                        >
                                            {description}
                                        </span>
                                        <span
                                            className={cn(
                                                'text-right text-base font-medium whitespace-nowrap amount',
                                                transaction.category_type ===
                                                    'transfer'
                                                    ? 'text-foreground'
                                                    : amountTone(
                                                          transaction.amount,
                                                      ),
                                            )}
                                        >
                                            {formatMoney(
                                                transaction.amount,
                                                transaction.currency,
                                                locale,
                                                'always',
                                            )}
                                        </span>
                                    </button>
                                    {open ? (
                                        <div className="pb-4 tablet:px-5">
                                            {renderEditor(transaction)}
                                        </div>
                                    ) : null}
                                </li>
                            );
                        })}
                    </ul>
                </section>
            ))}
        </div>
    );
}
