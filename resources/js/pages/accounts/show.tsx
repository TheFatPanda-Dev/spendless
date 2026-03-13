import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    ArrowDownCircle,
    ArrowUpCircle,
    CalendarDays,
    ChevronDown,
    ChevronRight,
    Circle,
    Landmark,
    Search,
} from 'lucide-react';
import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import { CategoryIcon } from '@/components/category-icon';
import { resolveCategoryColorPresentation } from '@/components/category-icon-utils';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import {
    buildDashboardHref,
    formatDisplayDate,
    formatLocalizedDateTime,
    formatLocalizedNumericDate,
    shiftMonthRange,
} from '@/lib/date-filters';
import {
    formatLocalizedDecimal,
    normalizeLocalizedNumber,
} from '@/lib/localized-number';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Account = {
    id: number;
    display_name: string | null;
    name: string | null;
    official_name: string | null;
    institution_name: string | null;
    provider: string | null;
    is_manual: boolean;
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
    category_id: number | null;
    assigned_category_id: number | null;
    category_icon: string;
    category_color: string;
    category_source: 'manual' | 'automatic' | 'fallback';
    category_type: 'expense' | 'income';
    provider_category: string | null;
    amount: number;
    currency: string | null;
    date: string | null;
    pending: boolean;
};

type CategoryOption = {
    id: number;
    name: string;
    path: string;
    type: 'expense' | 'income';
    icon: string;
    color: string;
};

type CategoryTreeNode = {
    id: number;
    name: string;
    path: string;
    icon: string;
    color: string;
    children: CategoryTreeNode[];
};

type CategoryColorOption = {
    value: string;
    label: string;
    background: string;
    foreground: string;
};

type CategoryType = 'expense' | 'income';
type CategoryPickerView = 'type' | 'category';

type Filters = {
    start_date: string;
    end_date: string;
};

type Props = {
    account: Account;
    filters: Filters;
    categoryOptions: {
        expense: CategoryOption[];
        income: CategoryOption[];
    };
    categoryColors: CategoryColorOption[];
    transactions: Transaction[];
};

function formatCurrency(value: number, currency: string): string {
    const sign = value > 0 ? '+' : '';

    return `${sign}${value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })} ${currency}`;
}

function formatDayLabel(dateValue: string, locale: string): string {
    const parsed = new Date(`${dateValue}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return dateValue;
    }

    return formatLocalizedNumericDate(dateValue, locale);
}

function buildCategoryTree(options: CategoryOption[]): CategoryTreeNode[] {
    const tree: CategoryTreeNode[] = [];

    options.forEach((option) => {
        const segments = option.path
            .split(' / ')
            .map((segment) => segment.trim())
            .filter(Boolean);

        let level = tree;
        let currentPath = '';

        segments.forEach((segment, index) => {
            currentPath = currentPath === '' ? segment : `${currentPath} / ${segment}`;

            let node = level.find((entry) => entry.path === currentPath);

            if (!node) {
                node = {
                    id: index === segments.length - 1 ? option.id : -(currentPath.length + index + 1),
                    name: segment,
                    path: currentPath,
                    icon: option.icon,
                    color: option.color,
                    children: [],
                };

                level.push(node);
            }

            if (index === segments.length - 1) {
                node.id = option.id;
                node.icon = option.icon;
                node.color = option.color;
            }

            level = node.children;
        });
    });

    return tree;
}

function getCategoryLeafLabel(path: string): string {
    const segments = path
        .split(' / ')
        .map((segment) => segment.trim())
        .filter(Boolean);

    return segments[segments.length - 1] ?? path;
}

function formatCategoryPathLabel(path: string): string {
    return path
        .split(' / ')
        .map((segment) => segment.trim())
        .filter(Boolean)
        .join(' > ');
}

const categoryPickerPanelClasses =
    'rounded-3xl border border-brand/20 bg-[#f6fbf8] p-3 shadow-[0_16px_38px_-24px_rgba(15,23,42,0.3)] dark:border-brand/15 dark:bg-[#111916] dark:shadow-[0_16px_38px_-24px_rgba(2,6,23,0.72)]';

const categoryPickerTriggerClasses =
    'flex h-12 w-full items-center justify-between gap-3 rounded-2xl border border-brand/20 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(247,251,249,0.98))] px-3 text-left text-sm font-medium text-foreground shadow-sm transition hover:border-brand/25 hover:bg-brand/6 dark:border-brand/15 dark:bg-linear-to-br dark:from-brand/6 dark:via-card dark:to-card dark:text-slate-100 dark:hover:bg-card';

const categoryPickerOptionIdleClasses =
    'border-brand/15 bg-white text-foreground hover:border-brand/25 hover:bg-brand/5 dark:border-white/10 dark:bg-[#16201c] dark:text-slate-100 dark:hover:border-brand/20 dark:hover:bg-[#1a2722]';

const categoryPickerIndicatorClasses =
    'flex size-6 shrink-0 items-center justify-center rounded-lg border border-brand/15 bg-white text-muted-foreground dark:border-white/10 dark:bg-[#1c2823] dark:text-slate-500';

const accountShellClasses =
    'relative flex h-full flex-1 flex-col gap-4 overflow-x-hidden rounded-[28px] border border-brand/20 bg-[#f4faf7] p-3 shadow-[0_28px_72px_-48px_rgba(16,185,129,0.24)] sm:p-5 lg:p-7 dark:border-brand/15 dark:bg-[#0f1714] dark:shadow-none';

const accountPanelClasses =
    'rounded-2xl border border-brand/15 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(244,250,247,0.98))] shadow-[0_14px_36px_-24px_rgba(15,23,42,0.18)] dark:border-brand/15 dark:bg-linear-to-br dark:from-brand/6 dark:via-card dark:to-card dark:shadow-[0_16px_36px_-24px_rgba(2,6,23,0.85)]';

const accountControlClasses =
    'border-brand/20 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(247,251,249,0.98))] text-foreground shadow-sm hover:bg-brand/6 dark:border-brand/15 dark:bg-linear-to-br dark:from-brand/6 dark:via-card dark:to-card dark:text-foreground dark:hover:bg-card';

const transactionEditorCardClasses =
    'mt-3 rounded-3xl border border-brand/20 bg-[linear-gradient(180deg,rgba(248,252,250,0.98),rgba(239,248,244,0.98))] p-3 shadow-[0_18px_42px_-28px_rgba(16,185,129,0.22)] dark:border-brand/15 dark:bg-[linear-gradient(180deg,rgba(17,25,22,0.98),rgba(12,20,17,0.98))] dark:shadow-[0_18px_42px_-28px_rgba(2,6,23,0.6)]';

const transactionEditorFieldClasses =
    'h-10 rounded-xl border-brand/15 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(246,251,248,0.98))] text-sm text-foreground placeholder:text-muted-foreground shadow-sm focus-visible:border-brand/35 focus-visible:ring-2 focus-visible:ring-brand/25 focus-visible:ring-offset-0 dark:border-white/8 dark:bg-[linear-gradient(180deg,rgba(24,35,30,0.98),rgba(19,29,25,0.98))] dark:text-slate-100 dark:focus-visible:border-emerald-400/45 dark:focus-visible:ring-emerald-400/25';

const transactionEditorButtonClasses =
    'border-brand/15 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(246,251,248,0.98))] text-foreground shadow-sm hover:bg-brand/6 dark:border-white/10 dark:bg-[linear-gradient(180deg,rgba(24,35,30,0.98),rgba(19,29,25,0.98))] dark:text-slate-100 dark:hover:bg-[#1b2823]';

const manualTransactionFocusClasses =
    'border-brand/20 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(247,251,249,0.98))] text-foreground shadow-sm hover:bg-brand/6 focus-visible:border-[#39ff14] focus-visible:ring-2 focus-visible:ring-[#39ff14]/35 focus-visible:ring-offset-0 dark:border-brand/15 dark:bg-linear-to-br dark:from-brand/6 dark:via-card dark:to-card dark:text-foreground dark:hover:bg-card dark:focus-visible:border-[#39ff14] dark:focus-visible:ring-[#39ff14]/30';

function CategoryTypeSwitcher({
    activeType,
    onSelect,
}: {
    activeType: CategoryType;
    onSelect: (type: CategoryType) => void;
}) {
    return (
        <div className="grid gap-2">
            {(['expense', 'income'] as const).map((type) => {
                const isActive = activeType === type;
                const isIncome = type === 'income';
                const Icon = isIncome ? ArrowUpCircle : ArrowDownCircle;

                return (
                    <button
                        key={type}
                        type="button"
                        onClick={() => onSelect(type)}
                        className={`flex items-center gap-3 rounded-2xl border px-3 py-3 text-left transition ${
                            isActive
                                ? isIncome
                                    ? 'border-emerald-400/35 bg-emerald-500/14 text-emerald-950 shadow-[0_14px_28px_rgba(16,185,129,0.14)] dark:text-emerald-100 dark:shadow-[0_14px_28px_rgba(16,185,129,0.12)]'
                                    : 'border-rose-400/35 bg-rose-500/14 text-rose-950 shadow-[0_14px_28px_rgba(244,63,94,0.14)] dark:text-rose-100 dark:shadow-[0_14px_28px_rgba(244,63,94,0.12)]'
                                : 'border-brand/15 bg-white text-foreground hover:border-brand/25 hover:bg-brand/5 dark:border-white/10 dark:bg-[#16201c] dark:text-slate-100 dark:hover:border-brand/20 dark:hover:bg-[#1a2722]'
                        }`}
                    >
                        <div
                            className={`flex size-9 shrink-0 items-center justify-center rounded-2xl border ${
                                isIncome
                                    ? 'border-emerald-400/30 bg-emerald-500/15 text-emerald-600 dark:text-emerald-300'
                                    : 'border-rose-400/30 bg-rose-500/15 text-rose-600 dark:text-rose-300'
                            }`}
                        >
                            <Icon className="size-4.5" />
                        </div>

                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-semibold capitalize">
                                {type}
                            </p>
                            <p className="truncate text-xs text-muted-foreground dark:text-slate-400">
                                {isIncome
                                    ? 'Show income categories.'
                                    : 'Show expense categories.'}
                            </p>
                        </div>
                    </button>
                );
            })}
        </div>
    );
}

function TransactionCategoryEditor({
    activeType,
    pickerView,
    onTypeChange,
    selectedLabel,
    isCategoryPickerOpen,
    onTogglePicker,
    categorySearchQuery,
    onSearchChange,
    filteredCategorySearchResults,
    selectedCategoryId,
    categoryColorMap,
    activeCategoryTree,
    expandedCategoryPaths,
    onTogglePath,
    onSelectCategory,
}: {
    activeType: CategoryType;
    pickerView: CategoryPickerView;
    onTypeChange: (type: CategoryType) => void;
    selectedLabel: string;
    isCategoryPickerOpen: boolean;
    onTogglePicker: () => void;
    categorySearchQuery: string;
    onSearchChange: (value: string) => void;
    filteredCategorySearchResults: CategoryOption[];
    selectedCategoryId: string;
    categoryColorMap: Map<string, CategoryColorOption>;
    activeCategoryTree: CategoryTreeNode[];
    expandedCategoryPaths: Set<string>;
    onTogglePath: (path: string) => void;
    onSelectCategory: (categoryId: string) => void;
}) {
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!isCategoryPickerOpen) return;

        function handleClickOutside(event: MouseEvent) {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                onTogglePicker();
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [isCategoryPickerOpen, onTogglePicker]);

    return (
        <div ref={containerRef} className="relative min-w-0">
            <div className="relative min-w-0">
                <button
                    type="button"
                    onClick={onTogglePicker}
                    className={`${categoryPickerTriggerClasses} h-10 rounded-xl px-3 py-2`}
                >
                    <span className="min-w-0 truncate text-foreground dark:text-slate-100">
                        {selectedLabel}
                    </span>
                    <ChevronDown
                        className={`size-4 shrink-0 text-slate-500 transition-transform ${
                            isCategoryPickerOpen ? 'rotate-180 text-brand' : ''
                        }`}
                    />
                </button>

                {isCategoryPickerOpen ? (
                    <div className={`absolute top-[calc(100%+0.5rem)] left-0 z-20 w-full max-w-full lg:min-w-full lg:w-max ${categoryPickerPanelClasses}`}>
                        {pickerView === 'type' ? (
                            <div className="space-y-3">
                                <div>
                                    <p className="text-sm font-semibold text-foreground dark:text-slate-100">
                                        Choose category type
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground dark:text-slate-500">
                                        Pick Expense or Income first, then choose a category.
                                    </p>
                                </div>

                                <CategoryTypeSwitcher activeType={activeType} onSelect={onTypeChange} />
                            </div>
                        ) : (
                            <>
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-semibold capitalize text-foreground dark:text-slate-100">
                                            {activeType} categories
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground dark:text-slate-500">
                                            Search or browse the list below.
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        onClick={onTogglePicker}
                                        className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground transition hover:text-foreground dark:text-slate-500 dark:hover:text-slate-100"
                                    >
                                        Back
                                    </button>
                                </div>

                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-3 left-3 size-4 text-slate-500" />
                                    <Input
                                        value={categorySearchQuery}
                                        onChange={(event) => onSearchChange(event.target.value)}
                                        placeholder={`Search ${activeType} categories`}
                                        className="border-brand/15 bg-white pl-9 text-foreground placeholder:text-slate-500 dark:border-white/10 dark:bg-[#16201c] dark:text-slate-100"
                                    />
                                </div>

                                <div className="mt-3 max-h-60 overflow-y-auto pr-1 lg:max-h-64">
                                    {categorySearchQuery.trim() !== '' ? (
                                        <div className="space-y-1.5">
                                            {filteredCategorySearchResults.length > 0 ? (
                                                filteredCategorySearchResults.map((option) => {
                                                    const isSelectedOption = selectedCategoryId === String(option.id);
                                                    const optionPresentation = resolveCategoryColorPresentation(
                                                        option.color,
                                                        categoryColorMap,
                                                    );

                                                    return (
                                                        <button
                                                            key={option.id}
                                                            type="button"
                                                            onClick={() => onSelectCategory(String(option.id))}
                                                            className={`flex w-full items-center gap-3 rounded-2xl border px-3 py-2.5 text-left text-sm transition ${
                                                                isSelectedOption
                                                                    ? 'border-brand/25 bg-brand/12 text-slate-50'
                                                                    : categoryPickerOptionIdleClasses
                                                            }`}
                                                        >
                                                            <CategoryIcon
                                                                name={option.icon}
                                                                variant="badge"
                                                                presentation={optionPresentation}
                                                                className="size-9 rounded-2xl"
                                                                iconSize={18}
                                                            />
                                                            <div className="min-w-0 flex-1">
                                                                <p className="truncate font-medium text-foreground dark:text-slate-100">
                                                                    {option.name}
                                                                </p>
                                                                <p className="truncate text-xs text-muted-foreground dark:text-slate-500">
                                                                    {formatCategoryPathLabel(option.path)}
                                                                </p>
                                                            </div>
                                                        </button>
                                                    );
                                                })
                                            ) : (
                                                <div className="rounded-2xl border border-dashed border-white/8 px-3 py-5 text-center text-sm text-slate-500">
                                                    No categories found.
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <CategoryPickerTree
                                            nodes={activeCategoryTree}
                                            expandedPaths={expandedCategoryPaths}
                                            selectedCategoryId={selectedCategoryId}
                                            colorMap={categoryColorMap}
                                            onToggle={onTogglePath}
                                            onSelect={onSelectCategory}
                                        />
                                    )}
                                </div>
                            </>
                        )}
                    </div>
                ) : null}
            </div>
        </div>
    );
}

function CategoryPickerTree({
    nodes,
    expandedPaths,
    selectedCategoryId,
    colorMap,
    onToggle,
    onSelect,
    depth = 0,
}: {
    nodes: CategoryTreeNode[];
    expandedPaths: Set<string>;
    selectedCategoryId: string;
    colorMap: Map<string, CategoryColorOption>;
    onToggle: (path: string) => void;
    onSelect: (categoryId: string) => void;
    depth?: number;
}) {
    if (nodes.length === 0) {
        return null;
    }

    return (
        <div className="space-y-1.5">
            {nodes.map((node) => {
                const hasChildren = node.children.length > 0;
                const isExpanded = expandedPaths.has(node.path);
                const isSelected = selectedCategoryId === String(node.id);
                const presentation = resolveCategoryColorPresentation(
                    node.color,
                    colorMap,
                );

                return (
                    <div key={node.path} className="space-y-1.5">
                        <button
                            type="button"
                            onClick={() => {
                                if (hasChildren) {
                                    onToggle(node.path);

                                    return;
                                }

                                onSelect(String(node.id));
                            }}
                            className={`flex w-full items-center gap-3 rounded-2xl border px-3 py-2.5 text-left transition ${
                                isSelected
                                    ? 'border-brand/25 bg-brand/12 text-slate-50'
                                    : categoryPickerOptionIdleClasses
                            }`}
                            style={{ paddingLeft: `${depth * 0.95 + 0.75}rem` }}
                        >
                            <span className={categoryPickerIndicatorClasses}>
                                {hasChildren ? (
                                    <ChevronRight
                                        className={`size-3.5 transition ${isExpanded ? 'rotate-90 text-brand' : ''}`}
                                    />
                                ) : (
                                    <Circle className="size-2 fill-current stroke-none" />
                                )}
                            </span>

                            <CategoryIcon
                                name={node.icon}
                                variant="badge"
                                presentation={presentation}
                                className="size-9 rounded-2xl"
                                iconSize={18}
                            />

                            <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                {node.name}
                            </span>
                        </button>

                        {hasChildren && isExpanded ? (
                            <div className="border-l border-white/8 pl-2">
                                <CategoryPickerTree
                                    nodes={node.children}
                                    expandedPaths={expandedPaths}
                                    selectedCategoryId={selectedCategoryId}
                                    colorMap={colorMap}
                                    onToggle={onToggle}
                                    onSelect={onSelect}
                                    depth={depth + 1}
                                />
                            </div>
                        ) : null}
                    </div>
                );
            })}
        </div>
    );
}

export default function AccountShow({
    account,
    filters,
    categoryOptions,
    categoryColors,
    transactions,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: buildDashboardHref(dashboard()),
        },
        {
            title: account.display_name ?? account.name ?? 'Account',
            href: `/accounts/${account.id}`,
        },
    ];

    const currency = account.currency ?? 'EUR';
    const currentBalance = Number(account.balances?.current ?? 0);
    const availableBalance = account.balances?.available;
    const categoryColorMap = useMemo(
        () => new Map(categoryColors.map((option) => [option.value, option])),
        [categoryColors],
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

    const [counterpartyFilter, setCounterpartyFilter] = useState('all');
    const [keywordFilter, setKeywordFilter] = useState('');
    const [activeTransactionId, setActiveTransactionId] = useState<number | null>(null);
    const [selectedCategoryId, setSelectedCategoryId] = useState('');
    const [editedTransactionName, setEditedTransactionName] = useState('');
    const [selectedCategoryType, setSelectedCategoryType] = useState<CategoryType>('expense');
    const [isUpdatingCategory, setIsUpdatingCategory] = useState(false);
    const [isCategoryPickerOpen, setIsCategoryPickerOpen] = useState(false);
    const [categoryPickerView, setCategoryPickerView] = useState<CategoryPickerView>('type');
    const [categorySearchQuery, setCategorySearchQuery] = useState('');
    const [expandedCategoryPaths, setExpandedCategoryPaths] = useState<Set<string>>(new Set());
    const previousMonthRange = shiftMonthRange(filters.start_date, -1);
    const nextMonthRange = shiftMonthRange(filters.start_date, 1);
    const [showManualTransactionForm, setShowManualTransactionForm] =
        useState(false);
    const [manualSelectedCategoryId, setManualSelectedCategoryId] =
        useState('');
    const [manualSelectedCategoryType, setManualSelectedCategoryType] =
        useState<CategoryType>('expense');
    const [isManualCategoryPickerOpen, setIsManualCategoryPickerOpen] =
        useState(false);
    const [manualCategoryPickerView, setManualCategoryPickerView] =
        useState<CategoryPickerView>('type');
    const [manualCategorySearchQuery, setManualCategorySearchQuery] =
        useState('');
    const [manualExpandedCategoryPaths, setManualExpandedCategoryPaths] =
        useState<Set<string>>(new Set());
    const manualNativeDateInputRef = useRef<HTMLInputElement | null>(null);
    const [manualTransactionAmountInput, setManualTransactionAmountInput] =
        useState('');
    const page = usePage();
    const numberLocale =
        typeof page.props.number_locale === 'string'
            ? page.props.number_locale
            : 'en-GB';
    const manualTransactionForm = useForm({
        category_id: '',
        date: new Date().toISOString().slice(0, 10),
        merchant_name: '',
        transaction_name: '',
        amount: '',
    });

    const filteredTransactions = useMemo(() => {
        const keyword = keywordFilter.trim().toLowerCase();

        return transactions.filter((transaction) => {
            const personValue = transaction.counterparty ?? transaction.merchant_name ?? 'Unknown';
            const personMatch = counterpartyFilter === 'all' || personValue === counterpartyFilter;
            const keywordSource = [
                transaction.name,
                transaction.merchant_name,
                transaction.counterparty,
                transaction.category,
                transaction.provider_category,
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();
            const keywordMatch = keyword.length === 0 || keywordSource.includes(keyword);

            return personMatch && keywordMatch;
        });
    }, [counterpartyFilter, keywordFilter, transactions]);

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

    const activeTransaction = useMemo(
        () => transactions.find((transaction) => transaction.id === activeTransactionId) ?? null,
        [activeTransactionId, transactions],
    );

    const activeCategoryOptions = useMemo(() => {
        if (activeTransaction === null) {
            return [] as CategoryOption[];
        }

        return categoryOptions[selectedCategoryType] ?? [];
    }, [activeTransaction, categoryOptions, selectedCategoryType]);

    const activeCategoryTree = useMemo(
        () => buildCategoryTree(activeCategoryOptions),
        [activeCategoryOptions],
    );

    const isManualAccount = account.is_manual;

    const manualCategoryOptions = useMemo(
        () => categoryOptions[manualSelectedCategoryType] ?? [],
        [categoryOptions, manualSelectedCategoryType],
    );

    const manualCategoryTree = useMemo(
        () => buildCategoryTree(manualCategoryOptions),
        [manualCategoryOptions],
    );

    const manualSelectedCategoryOption = useMemo(
        () =>
            manualCategoryOptions.find(
                (option) => String(option.id) === manualSelectedCategoryId,
            ) ?? null,
        [manualCategoryOptions, manualSelectedCategoryId],
    );

    const manualFilteredCategorySearchResults = useMemo(() => {
        const query = manualCategorySearchQuery.trim().toLowerCase();

        if (query === '') {
            return [] as CategoryOption[];
        }

        return manualCategoryOptions.filter((option) => {
            return (
                option.name.toLowerCase().includes(query)
                || option.path.toLowerCase().includes(query)
            );
        });
    }, [manualCategoryOptions, manualCategorySearchQuery]);

    const manualDateDisplay = useMemo(() => {
        const rawDate = manualTransactionForm.data.date;

        if (rawDate.trim() === '') {
            return '';
        }

        return formatLocalizedNumericDate(rawDate, numberLocale);
    }, [manualTransactionForm.data.date, numberLocale]);

    const selectedCategoryOption = useMemo(
        () => activeCategoryOptions.find((option) => String(option.id) === selectedCategoryId) ?? null,
        [activeCategoryOptions, selectedCategoryId],
    );

    const filteredCategorySearchResults = useMemo(() => {
        const query = categorySearchQuery.trim().toLowerCase();

        if (query === '') {
            return [] as CategoryOption[];
        }

        return activeCategoryOptions.filter((option) => {
            return option.name.toLowerCase().includes(query)
                || option.path.toLowerCase().includes(query);
        });
    }, [activeCategoryOptions, categorySearchQuery]);

    const hasCategorySelectionChanged =
        activeTransaction !== null
        && selectedCategoryId !== (activeTransaction.category_id !== null ? String(activeTransaction.category_id) : '');

    const hasTransactionNameChanged =
        activeTransaction !== null
        && editedTransactionName !== (activeTransaction.name ?? '');

    const hasTransactionChanges = hasCategorySelectionChanged || hasTransactionNameChanged;

    const resetFilters = (): void => {
        setCounterpartyFilter('all');
        setKeywordFilter('');
    };

    const openTransactionEditor = (transaction: Transaction): void => {
        if (activeTransactionId === transaction.id) {
            closeTransactionEditor();

            return;
        }

        setActiveTransactionId(transaction.id);
        setSelectedCategoryId(transaction.category_id !== null ? String(transaction.category_id) : '');
        setEditedTransactionName(transaction.name ?? '');
        setSelectedCategoryType(transaction.category_type);
        setIsCategoryPickerOpen(false);
        setCategoryPickerView('type');
        setCategorySearchQuery('');
        setExpandedCategoryPaths(new Set());
    };

    const closeTransactionEditor = (): void => {
        if (isUpdatingCategory) {
            return;
        }

        setActiveTransactionId(null);
        setSelectedCategoryId('');
        setEditedTransactionName('');
        setSelectedCategoryType('expense');
        setIsCategoryPickerOpen(false);
        setCategoryPickerView('type');
        setCategorySearchQuery('');
        setExpandedCategoryPaths(new Set());
    };

    const updateTransaction = (): void => {
        if (activeTransaction === null || !hasTransactionChanges) {
            return;
        }

        const payload: { category_id?: number; name?: string | null } = {};

        if (hasCategorySelectionChanged && selectedCategoryId !== '') {
            payload.category_id = Number(selectedCategoryId);
        }

        if (hasTransactionNameChanged) {
            const normalizedName = editedTransactionName.trim();

            payload.name = normalizedName === '' ? null : normalizedName;
        }

        router.patch(
            `/accounts/${account.id}/transactions/${activeTransaction.id}`,
            payload,
            {
                preserveScroll: true,
                preserveState: true,
                preserveUrl: true,
                only: ['account', 'transactions'],
                onStart: () => {
                    setIsUpdatingCategory(true);
                },
                onFinish: () => {
                    setIsUpdatingCategory(false);
                },
                onSuccess: () => {
                    closeTransactionEditor();
                },
            },
        );
    };

    const toggleCategoryPath = (path: string): void => {
        setExpandedCategoryPaths((current) => {
            const next = new Set(current);

            if (next.has(path)) {
                next.delete(path);
            } else {
                next.add(path);
            }

            return next;
        });
    };

    const selectCategory = (categoryId: string): void => {
        setSelectedCategoryId(categoryId);
        setIsCategoryPickerOpen(false);
        setCategoryPickerView('type');
        setCategorySearchQuery('');
    };

    const selectCategoryType = (type: CategoryType): void => {
        setSelectedCategoryType(type);
        setSelectedCategoryId('');
        setCategoryPickerView('category');
        setIsCategoryPickerOpen(true);
        setCategorySearchQuery('');
        setExpandedCategoryPaths(new Set());
    };

    const toggleCategoryPicker = (): void => {
        if (isCategoryPickerOpen) {
            if (categoryPickerView === 'category') {
                setCategoryPickerView('type');
                setCategorySearchQuery('');
                setExpandedCategoryPaths(new Set());

                return;
            }

            setIsCategoryPickerOpen(false);
            setCategoryPickerView('type');

            return;
        }

        setIsCategoryPickerOpen(true);
        setCategoryPickerView('type');
        setCategorySearchQuery('');
        setExpandedCategoryPaths(new Set());
    };

    const resetManualTransactionForm = (): void => {
        const today = new Date().toISOString().slice(0, 10);

        setShowManualTransactionForm(false);
        setManualSelectedCategoryId('');
        setManualSelectedCategoryType('expense');
        setIsManualCategoryPickerOpen(false);
        setManualCategoryPickerView('type');
        setManualCategorySearchQuery('');
        setManualExpandedCategoryPaths(new Set());
        setManualTransactionAmountInput('');
        manualTransactionForm.reset();
        manualTransactionForm.setData('date', today);
    };

    const selectManualCategory = (categoryId: string): void => {
        setManualSelectedCategoryId(categoryId);
        manualTransactionForm.setData('category_id', categoryId);
        setIsManualCategoryPickerOpen(false);
        setManualCategoryPickerView('type');
        setManualCategorySearchQuery('');
    };

    const selectManualCategoryType = (type: CategoryType): void => {
        setManualSelectedCategoryType(type);
        setManualSelectedCategoryId('');
        manualTransactionForm.setData('category_id', '');
        setManualCategoryPickerView('category');
        setIsManualCategoryPickerOpen(true);
        setManualCategorySearchQuery('');
        setManualExpandedCategoryPaths(new Set());
    };

    const toggleManualCategoryPath = (path: string): void => {
        setManualExpandedCategoryPaths((current) => {
            const next = new Set(current);

            if (next.has(path)) {
                next.delete(path);
            } else {
                next.add(path);
            }

            return next;
        });
    };

    const toggleManualCategoryPicker = (): void => {
        if (isManualCategoryPickerOpen) {
            if (manualCategoryPickerView === 'category') {
                setManualCategoryPickerView('type');
                setManualCategorySearchQuery('');
                setManualExpandedCategoryPaths(new Set());

                return;
            }

            setIsManualCategoryPickerOpen(false);
            setManualCategoryPickerView('type');

            return;
        }

        setIsManualCategoryPickerOpen(true);
        setManualCategoryPickerView('type');
        setManualCategorySearchQuery('');
        setManualExpandedCategoryPaths(new Set());
    };

    const submitManualTransaction = (): void => {
        manualTransactionForm.post(
            `/accounts/${account.id}/manual-transactions?start_date=${filters.start_date}&end_date=${filters.end_date}`,
            {
                preserveScroll: true,
                preserveState: true,
                preserveUrl: true,
                only: ['account', 'transactions'],
                onSuccess: () => {
                    resetManualTransactionForm();
                },
            },
        );
    };

    const openManualDatePicker = (): void => {
        manualNativeDateInputRef.current?.showPicker?.();
        manualNativeDateInputRef.current?.focus();
        manualNativeDateInputRef.current?.click();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={account.display_name ?? account.name ?? 'Account'} />

            <div className={accountShellClasses}>
                <Card className={`relative py-4 ${accountPanelClasses}`}>
                    <CardContent className="flex flex-wrap items-start justify-between gap-4 px-4">
                        <div>
                            <p className="text-lg font-semibold text-foreground">
                                {account.display_name ?? account.name ?? account.official_name ?? 'Bank account'}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                <Landmark className="mr-1 inline size-4 text-brand" />
                                {account.institution_name ??
                                    (isManualAccount
                                        ? 'Manual wallet'
                                        : 'Institution')}
                                {account.mask ? ` • **** ${account.mask}` : ''}
                            </p>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Last synced: {formatLocalizedDateTime(account.last_synced_at, numberLocale)}
                            </p>
                        </div>

                        <div className="text-right">
                            <p className="text-sm text-muted-foreground">Current balance</p>
                            <p className="text-2xl font-semibold text-emerald-600 dark:text-emerald-400">
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

                <div className="flex w-full flex-wrap items-center justify-between gap-3">
                    <div className="flex w-full items-center justify-between gap-3 sm:w-auto sm:justify-start">
                        <h2 className="text-lg font-semibold">Transactions</h2>
                        {isManualAccount ? (
                            <Button
                                type="button"
                                disabled={
                                    showManualTransactionForm
                                    || manualTransactionForm.processing
                                }
                                className={`font-semibold transition-colors disabled:pointer-events-none ${
                                    showManualTransactionForm
                                        ? 'border border-brand/35 bg-brand/20 text-brand/80 dark:border-brand/30 dark:bg-brand/20 dark:text-brand/75'
                                        : 'border border-brand bg-brand text-white hover:bg-brand/90 dark:border-brand dark:bg-brand dark:text-white dark:hover:bg-brand/90'
                                }`}
                                onClick={() => {
                                    setShowManualTransactionForm(true);
                                }}
                            >
                                Add Transaction
                            </Button>
                        ) : null}
                    </div>

                    <div className="flex w-full min-w-0 flex-wrap items-center gap-2 lg:w-auto lg:flex-1 lg:justify-center">
                        <select
                            value={counterpartyFilter}
                            onChange={(event) => setCounterpartyFilter(event.target.value)}
                            className={`h-9 w-full rounded-md border px-3 pr-10 text-sm sm:min-w-44 sm:w-auto ${accountControlClasses}`}
                        >
                            <option value="all">Filter by Merchants</option>
                            {counterparties.map((name) => (
                                <option key={name} value={name}>
                                    {name}
                                </option>
                            ))}
                        </select>

                        <div className="relative w-full sm:min-w-56 sm:flex-1 sm:max-w-md">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={keywordFilter}
                                onChange={(event) => setKeywordFilter(event.target.value)}
                                placeholder="Search by Keyword"
                                className={accountControlClasses + ' h-9 pl-9 pr-9'}
                            />
                            {counterpartyFilter !== 'all' || keywordFilter !== '' ? (
                                <button
                                    type="button"
                                    onClick={resetFilters}
                                    className="absolute top-1/2 right-3 -translate-y-1/2 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    Clear
                                </button>
                            ) : null}
                        </div>
                    </div>

                    <div className="flex w-full flex-wrap items-center justify-center gap-2 md:w-auto md:justify-start">
                        <Button
                            variant="outline"
                            size="icon"
                            className={accountControlClasses}
                            onClick={() => {
                                router.get(
                                    `/accounts/${account.id}`,
                                    {
                                        start_date: previousMonthRange.startDate,
                                        end_date: previousMonthRange.endDate,
                                    },
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        preserveUrl: true,
                                        replace: true,
                                        showProgress: false,
                                        only: ['account', 'filters', 'transactions'],
                                    },
                                );
                            }}
                        >
                            <ArrowLeft className="size-4" />
                        </Button>

                        <div className={`flex items-center gap-2 rounded-md border px-3 py-2 text-sm ${accountControlClasses}`}>
                            <CalendarDays className="size-4 text-muted-foreground" />
                            <span>
                                {formatDisplayDate(filters.start_date, numberLocale)} - {formatDisplayDate(filters.end_date, numberLocale)}
                            </span>
                        </div>

                        <Button
                            variant="outline"
                            size="icon"
                            className={accountControlClasses}
                            onClick={() => {
                                router.get(
                                    `/accounts/${account.id}`,
                                    {
                                        start_date: nextMonthRange.startDate,
                                        end_date: nextMonthRange.endDate,
                                    },
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        preserveUrl: true,
                                        replace: true,
                                        showProgress: false,
                                        only: ['account', 'filters', 'transactions'],
                                    },
                                );
                            }}
                        >
                            <ArrowRight className="size-4" />
                        </Button>
                    </div>
                </div>

                {isManualAccount ? (
                    <div
                        className={`grid transition-all duration-300 ease-out ${
                            showManualTransactionForm
                                ? 'grid-rows-[1fr] overflow-visible opacity-100'
                                : 'grid-rows-[0fr] opacity-0'
                        }`}
                    >
                        <div
                            className={`min-h-0 ${
                                showManualTransactionForm
                                    ? 'overflow-visible'
                                    : 'overflow-hidden'
                            }`}
                        >
                            <Card
                                className={`relative mt-1 overflow-visible py-0 ${accountPanelClasses}`}
                            >
                                <CardContent className="px-4 py-4">
                                    <div className="grid gap-3 lg:grid-cols-[1.2fr_0.9fr_1fr_1fr_0.8fr_auto] lg:items-end">
                                        <div className="grid gap-2">
                                            <label className="text-xs font-medium text-muted-foreground">
                                                Category
                                            </label>
                                            <TransactionCategoryEditor
                                                activeType={manualSelectedCategoryType}
                                                pickerView={manualCategoryPickerView}
                                                onTypeChange={selectManualCategoryType}
                                                selectedLabel={
                                                    manualSelectedCategoryOption
                                                        ? formatCategoryPathLabel(
                                                            manualSelectedCategoryOption.path,
                                                        )
                                                        : 'Choose category'
                                                }
                                                isCategoryPickerOpen={isManualCategoryPickerOpen}
                                                onTogglePicker={toggleManualCategoryPicker}
                                                categorySearchQuery={manualCategorySearchQuery}
                                                onSearchChange={setManualCategorySearchQuery}
                                                filteredCategorySearchResults={manualFilteredCategorySearchResults}
                                                selectedCategoryId={manualSelectedCategoryId}
                                                categoryColorMap={categoryColorMap}
                                                activeCategoryTree={manualCategoryTree}
                                                expandedCategoryPaths={manualExpandedCategoryPaths}
                                                onTogglePath={toggleManualCategoryPath}
                                                onSelectCategory={selectManualCategory}
                                            />
                                            {manualTransactionForm.errors.category_id ? (
                                                <p className="text-xs text-destructive">
                                                    {
                                                        manualTransactionForm
                                                            .errors.category_id
                                                    }
                                                </p>
                                            ) : null}
                                        </div>

                                        <div className="grid gap-2">
                                            <label
                                                htmlFor="manual-transaction-date"
                                                className="text-xs font-medium text-muted-foreground"
                                            >
                                                Date
                                            </label>
                                            <div className="relative">
                                                <Input
                                                    id="manual-transaction-date"
                                                    type="text"
                                                    readOnly
                                                    value={manualDateDisplay}
                                                    onClick={openManualDatePicker}
                                                    className={`${manualTransactionFocusClasses} cursor-pointer pr-10`}
                                                />

                                                <input
                                                    ref={manualNativeDateInputRef}
                                                    type="date"
                                                    value={manualTransactionForm.data.date}
                                                    onChange={(event) =>
                                                        manualTransactionForm.setData(
                                                            'date',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="pointer-events-none absolute h-0 w-0 opacity-0"
                                                    tabIndex={-1}
                                                    aria-hidden="true"
                                                />

                                                <button
                                                    type="button"
                                                    onClick={openManualDatePicker}
                                                    className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground transition hover:text-foreground"
                                                    aria-label="Open date picker"
                                                >
                                                    <CalendarDays className="size-4" />
                                                </button>
                                            </div>
                                            {manualTransactionForm.errors.date ? (
                                                <p className="text-xs text-destructive">
                                                    {manualTransactionForm.errors.date}
                                                </p>
                                            ) : null}
                                        </div>

                                        <div className="grid gap-2">
                                            <label
                                                htmlFor="manual-transaction-merchant"
                                                className="text-xs font-medium text-muted-foreground"
                                            >
                                                Merchant (optional)
                                            </label>
                                            <Input
                                                id="manual-transaction-merchant"
                                                value={manualTransactionForm.data.merchant_name}
                                                onChange={(event) =>
                                                    manualTransactionForm.setData(
                                                        'merchant_name',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Merchant name"
                                                className={manualTransactionFocusClasses}
                                            />
                                            {manualTransactionForm.errors.merchant_name ? (
                                                <p className="text-xs text-destructive">
                                                    {
                                                        manualTransactionForm
                                                            .errors.merchant_name
                                                    }
                                                </p>
                                            ) : null}
                                        </div>

                                        <div className="grid gap-2">
                                            <label
                                                htmlFor="manual-transaction-name"
                                                className="text-xs font-medium text-muted-foreground"
                                            >
                                                Transaction (optional)
                                            </label>
                                            <Input
                                                id="manual-transaction-name"
                                                value={manualTransactionForm.data.transaction_name}
                                                onChange={(event) =>
                                                    manualTransactionForm.setData(
                                                        'transaction_name',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Transaction name"
                                                className={manualTransactionFocusClasses}
                                            />
                                            {manualTransactionForm.errors.transaction_name ? (
                                                <p className="text-xs text-destructive">
                                                    {
                                                        manualTransactionForm
                                                            .errors.transaction_name
                                                    }
                                                </p>
                                            ) : null}
                                        </div>

                                        <div className="grid gap-2">
                                            <label
                                                htmlFor="manual-transaction-amount"
                                                className="text-xs font-medium text-muted-foreground"
                                            >
                                                Amount ({currency})
                                            </label>
                                            <Input
                                                id="manual-transaction-amount"
                                                type="text"
                                                inputMode="decimal"
                                                value={manualTransactionAmountInput}
                                                onChange={(event) => {
                                                    const nextValue =
                                                        event.target.value;

                                                    setManualTransactionAmountInput(
                                                        nextValue,
                                                    );
                                                    manualTransactionForm.setData(
                                                        'amount',
                                                        normalizeLocalizedNumber(
                                                            nextValue,
                                                            numberLocale,
                                                        ),
                                                    );
                                                }}
                                                onBlur={(event) => {
                                                    const nextValue =
                                                        event.target.value;

                                                    if (
                                                        nextValue.trim() === ''
                                                    ) {
                                                        return;
                                                    }

                                                    setManualTransactionAmountInput(
                                                        formatLocalizedDecimal(
                                                            nextValue,
                                                            numberLocale,
                                                        ),
                                                    );
                                                }}
                                                placeholder={formatLocalizedDecimal(
                                                    '0',
                                                    numberLocale,
                                                )}
                                                className={manualTransactionFocusClasses}
                                            />
                                            {manualTransactionForm.errors.amount ? (
                                                <p className="text-xs text-destructive">
                                                    {
                                                        manualTransactionForm
                                                            .errors.amount
                                                    }
                                                </p>
                                            ) : null}
                                        </div>

                                        <div className="flex items-end justify-end gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className={accountControlClasses}
                                                onClick={() => {
                                                    resetManualTransactionForm();
                                                }}
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="button"
                                                onClick={submitManualTransaction}
                                                disabled={
                                                    manualTransactionForm.processing
                                                }
                                                className="bg-brand text-white! shadow-sm hover:bg-brand/90 disabled:opacity-100 disabled:bg-brand/65 disabled:text-white!"
                                            >
                                                {manualTransactionForm.processing
                                                    ? 'Saving...'
                                                    : 'Add Transaction'}
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                ) : null}

                <Card className={`relative w-full overflow-visible py-0 ${accountPanelClasses}`}>
                    <div className="divide-y divide-brand/10 dark:divide-white/6">
                        {groupedTransactions.map(([day, dayTransactions]) => {
                            const dayTotal = dayTransactions.reduce(
                                (carry, transaction) => carry + transaction.amount,
                                0,
                            );

                            return (
                                <section key={day} className="px-4 py-4">
                                    <div className="mb-3 flex items-center justify-between border-b border-brand/10 pb-3 dark:border-white/8">
                                        <h3 className="text-xl font-semibold tracking-tight text-foreground dark:text-slate-100">
                                            {day === 'Unknown date' ? day : formatDayLabel(day, numberLocale)}
                                        </h3>
                                        <p
                                            className={`text-lg font-semibold ${
                                                dayTotal > 0
                                                                                                        ? 'text-emerald-400'
                                                                                                        : dayTotal < 0
                                                                                                            ? 'text-rose-400'
                                                      : 'text-muted-foreground dark:text-slate-500'
                                            }`}
                                        >
                                            {formatCurrency(dayTotal, currency)}
                                        </p>
                                    </div>

                                    <div className="space-y-1.5 lg:hidden">
                                        {dayTransactions.map((transaction) => {
                                            const isActive = activeTransactionId === transaction.id;
                                            const amountClass =
                                                transaction.amount > 0
                                                    ? 'text-emerald-400'
                                                    : transaction.amount < 0
                                                      ? 'text-rose-400'
                                                      : 'text-slate-400';
                                            const categoryPresentation = resolveCategoryColorPresentation(
                                                transaction.category_color,
                                                categoryColorMap,
                                            );

                                            return (
                                                <div
                                                    key={transaction.id}
                                                    className={`overflow-visible rounded-3xl border transition ${
                                                        isActive
                                                            ? 'border-brand/25 bg-[linear-gradient(180deg,rgba(16,185,129,0.12),rgba(240,249,245,0.96))] shadow-[0_26px_70px_-42px_rgba(16,185,129,0.22)] dark:bg-[linear-gradient(180deg,rgba(16,185,129,0.12),rgba(11,16,21,0.95))] dark:shadow-[0_26px_70px_-42px_rgba(16,185,129,0.55)]'
                                                            : 'border-brand/10 bg-white/50 hover:border-brand/15 hover:bg-white/78 dark:border-white/6 dark:bg-white/2 dark:hover:border-white/8 dark:hover:bg-white/3'
                                                    }`}
                                                >
                                                    <button
                                                        type="button"
                                                        onClick={() => openTransactionEditor(transaction)}
                                                        className="grid w-full gap-2.5 px-3 py-2.5 text-left sm:px-4"
                                                    >
                                                        <div className="flex min-w-0 items-center gap-3">
                                                            <CategoryIcon
                                                                name={transaction.category_icon}
                                                                variant="badge"
                                                                presentation={categoryPresentation}
                                                                className="size-10 rounded-2xl"
                                                                iconSize={16}
                                                            />

                                                            <div className="min-w-0">
                                                                <p className="truncate text-sm font-medium text-foreground dark:text-slate-100">
                                                                    {getCategoryLeafLabel(transaction.category)}
                                                                </p>
                                                            </div>
                                                        </div>

                                                        <div className="min-w-0 text-left">
                                                            <p className="truncate text-sm font-medium text-foreground dark:text-slate-200">
                                                                {transaction.merchant_name ?? transaction.name ?? 'Transaction'}
                                                            </p>
                                                            <p className="mt-0.5 truncate text-xs text-muted-foreground dark:text-slate-500">
                                                                {transaction.counterparty ?? transaction.name ?? '---'}
                                                            </p>
                                                        </div>

                                                        <div className="flex min-w-0 items-center justify-end gap-3 text-right">
                                                            <p className={`text-sm font-semibold ${amountClass}`}>
                                                                {formatCurrency(
                                                                    transaction.amount,
                                                                    transaction.currency ?? currency,
                                                                )}
                                                            </p>
                                                            <ChevronRight
                                                                className={`size-4 text-muted-foreground transition-transform dark:text-slate-500 ${
                                                                    isActive ? 'rotate-90 text-brand' : ''
                                                                }`}
                                                            />
                                                        </div>
                                                    </button>

                                                    <div
                                                        className={`grid transition-all duration-300 ease-out ${
                                                            isActive
                                                                ? 'grid-rows-[1fr] overflow-visible opacity-100'
                                                                : 'grid-rows-[0fr] overflow-hidden opacity-0'
                                                        }`}
                                                    >
                                                        <div className="min-h-0">
                                                            {isActive ? (
                                                                <div className="border-t border-brand/10 px-3 pb-3 dark:border-white/8 sm:px-4 sm:pb-4">
                                                                    <div className={transactionEditorCardClasses}>
                                                                        <div className="grid gap-2 lg:grid-cols-[30%_30%_24%_16%] lg:items-center lg:gap-0">
                                                                            <div className="min-w-0">
                                                                                <TransactionCategoryEditor
                                                                                    activeType={selectedCategoryType}
                                                                                    pickerView={categoryPickerView}
                                                                                    onTypeChange={selectCategoryType}
                                                                                    selectedLabel={selectedCategoryOption ? formatCategoryPathLabel(selectedCategoryOption.path) : 'Choose category'}
                                                                                    isCategoryPickerOpen={isCategoryPickerOpen}
                                                                                    onTogglePicker={toggleCategoryPicker}
                                                                                    categorySearchQuery={categorySearchQuery}
                                                                                    onSearchChange={setCategorySearchQuery}
                                                                                    filteredCategorySearchResults={filteredCategorySearchResults}
                                                                                    selectedCategoryId={selectedCategoryId}
                                                                                    categoryColorMap={categoryColorMap}
                                                                                    activeCategoryTree={activeCategoryTree}
                                                                                    expandedCategoryPaths={expandedCategoryPaths}
                                                                                    onTogglePath={toggleCategoryPath}
                                                                                    onSelectCategory={selectCategory}
                                                                                />
                                                                            </div>

                                                                            <div className="flex min-w-0 items-center lg:px-3">
                                                                                <p className="truncate text-sm font-medium text-foreground dark:text-slate-100">
                                                                                    {transaction.merchant_name ?? 'No merchant details'}
                                                                                </p>
                                                                            </div>

                                                                            <div className="min-w-0 lg:px-3">
                                                                                <Input
                                                                                    value={editedTransactionName}
                                                                                    onChange={(event) => setEditedTransactionName(event.target.value)}
                                                                                    placeholder="Transaction"
                                                                                    className={transactionEditorFieldClasses}
                                                                                />
                                                                            </div>

                                                                            <div className="flex w-full justify-end gap-2 lg:flex-nowrap lg:px-3">
                                                                                <Button
                                                                                    type="button"
                                                                                    variant="outline"
                                                                                    onClick={closeTransactionEditor}
                                                                                    disabled={isUpdatingCategory}
                                                                                    className={`w-24 ${transactionEditorButtonClasses}`}
                                                                                >
                                                                                    Cancel
                                                                                </Button>
                                                                                <Button
                                                                                    type="button"
                                                                                    onClick={updateTransaction}
                                                                                    className="w-24 bg-brand text-white! shadow-sm hover:bg-brand/90 disabled:opacity-100 disabled:bg-brand/65 disabled:text-white!"
                                                                                    disabled={
                                                                                        isUpdatingCategory
                                                                                        || !hasTransactionChanges
                                                                                    }
                                                                                >
                                                                                    {isUpdatingCategory ? 'Saving...' : 'Save'}
                                                                                </Button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            ) : null}
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    <div className="hidden lg:block">
                                        <table className="w-full table-fixed border-separate border-spacing-y-1.5">
                                            <colgroup>
                                                <col className="w-[30%]" />
                                                <col className="w-[30%]" />
                                                <col className="w-[24%]" />
                                                <col className="w-[16%]" />
                                            </colgroup>
                                            <thead>
                                                <tr className="text-[11px] font-semibold uppercase tracking-[0.22em] text-muted-foreground dark:text-slate-500">
                                                    <th className="px-3 pb-2 text-left">Category</th>
                                                    <th className="px-3 pb-2 text-left">Merchant</th>
                                                    <th className="px-3 pb-2 text-left">Transaction</th>
                                                    <th className="px-3 pb-2 text-right">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {dayTransactions.map((transaction) => {
                                                    const isActive = activeTransactionId === transaction.id;
                                                    const amountClass =
                                                        transaction.amount > 0
                                                            ? 'text-emerald-400'
                                                            : transaction.amount < 0
                                                              ? 'text-rose-400'
                                                              : 'text-slate-400';
                                                    const categoryPresentation = resolveCategoryColorPresentation(
                                                        transaction.category_color,
                                                        categoryColorMap,
                                                    );

                                                    return (
                                                        <Fragment key={transaction.id}>
                                                            <tr
                                                                onClick={() => openTransactionEditor(transaction)}
                                                                onKeyDown={(event) => {
                                                                    if (event.key === 'Enter' || event.key === ' ') {
                                                                        event.preventDefault();
                                                                        openTransactionEditor(transaction);
                                                                    }
                                                                }}
                                                                tabIndex={0}
                                                                role="button"
                                                                aria-expanded={isActive}
                                                                className={`cursor-pointer outline-none transition focus-visible:ring-2 focus-visible:ring-brand/40 ${
                                                                    isActive
                                                                        ? 'rounded-3xl bg-[linear-gradient(180deg,rgba(16,185,129,0.12),rgba(240,249,245,0.96))] shadow-[0_26px_70px_-42px_rgba(16,185,129,0.22)] dark:bg-[linear-gradient(180deg,rgba(16,185,129,0.12),rgba(11,16,21,0.95))] dark:shadow-[0_26px_70px_-42px_rgba(16,185,129,0.55)]'
                                                                        : 'bg-white/42 hover:bg-white/82 dark:bg-white/2 dark:hover:bg-white/3'
                                                                }`}
                                                            >
                                                                <td className={`rounded-l-3xl border-y border-l px-3 py-2.5 align-middle ${isActive ? 'border-brand/25' : 'border-brand/8 dark:border-white/8'}`}>
                                                                    <div className="flex min-w-0 items-center gap-3">
                                                                        <CategoryIcon
                                                                            name={transaction.category_icon}
                                                                            variant="badge"
                                                                            presentation={categoryPresentation}
                                                                            className="size-10 rounded-2xl"
                                                                            iconSize={16}
                                                                        />
                                                                        <p className="truncate text-sm font-medium text-foreground dark:text-slate-100">
                                                                            {getCategoryLeafLabel(transaction.category)}
                                                                        </p>
                                                                    </div>
                                                                </td>
                                                                <td className={`border-y px-3 py-2.5 align-middle ${isActive ? 'border-brand/25' : 'border-brand/8 dark:border-white/8'}`}>
                                                                    <p className="truncate text-sm font-medium text-foreground dark:text-slate-200">
                                                                        {transaction.merchant_name ?? transaction.name ?? 'Transaction'}
                                                                    </p>
                                                                </td>
                                                                <td className={`border-y px-3 py-2.5 align-middle ${isActive ? 'border-brand/25' : 'border-brand/8 dark:border-white/8'}`}>
                                                                    <p className="truncate text-sm font-medium text-muted-foreground dark:text-slate-500">
                                                                        {transaction.counterparty ?? transaction.name ?? '---'}
                                                                    </p>
                                                                </td>
                                                                <td className={`rounded-r-3xl border-y border-r px-3 py-2.5 align-middle ${isActive ? 'border-brand/25' : 'border-brand/8 dark:border-white/8'}`}>
                                                                    <div className="flex items-center justify-end gap-3 text-right">
                                                                        <p className={`text-sm font-semibold ${amountClass}`}>
                                                                            {formatCurrency(
                                                                                transaction.amount,
                                                                                transaction.currency ?? currency,
                                                                            )}
                                                                        </p>
                                                                        <ChevronRight
                                                                            className={`size-4 text-muted-foreground transition-transform dark:text-slate-500 ${
                                                                                isActive ? 'rotate-90 text-brand' : ''
                                                                            }`}
                                                                        />
                                                                    </div>
                                                                </td>
                                                            </tr>

                                                            {isActive ? (
                                                                <tr>
                                                                    <td colSpan={4} className="px-0 pt-0 pb-2">
                                                                        <div className={transactionEditorCardClasses.replace('mt-3', 'mt-2')}>
                                                                                <div className="grid gap-2 lg:grid-cols-[30%_30%_24%_16%] lg:items-center lg:gap-0">
                                                                                    <div className="min-w-0">
                                                                                    <TransactionCategoryEditor
                                                                                        activeType={selectedCategoryType}
                                                                                        pickerView={categoryPickerView}
                                                                                        onTypeChange={selectCategoryType}
                                                                                        selectedLabel={selectedCategoryOption ? formatCategoryPathLabel(selectedCategoryOption.path) : 'Choose category'}
                                                                                        isCategoryPickerOpen={isCategoryPickerOpen}
                                                                                        onTogglePicker={toggleCategoryPicker}
                                                                                        categorySearchQuery={categorySearchQuery}
                                                                                        onSearchChange={setCategorySearchQuery}
                                                                                        filteredCategorySearchResults={filteredCategorySearchResults}
                                                                                        selectedCategoryId={selectedCategoryId}
                                                                                        categoryColorMap={categoryColorMap}
                                                                                        activeCategoryTree={activeCategoryTree}
                                                                                        expandedCategoryPaths={expandedCategoryPaths}
                                                                                        onTogglePath={toggleCategoryPath}
                                                                                        onSelectCategory={selectCategory}
                                                                                    />
                                                                                </div>

                                                                                <div className="flex min-w-0 items-center lg:px-3">
                                                                                    <p className="truncate text-sm font-medium text-foreground dark:text-slate-100">
                                                                                        {transaction.merchant_name ?? 'No merchant details'}
                                                                                    </p>
                                                                                </div>

                                                                                <div className="min-w-0 lg:px-3">
                                                                                    <Input
                                                                                        value={editedTransactionName}
                                                                                        onChange={(event) => setEditedTransactionName(event.target.value)}
                                                                                        placeholder="Transaction"
                                                                                        className={transactionEditorFieldClasses}
                                                                                    />
                                                                                </div>

                                                                                <div className="flex w-full justify-end gap-2 lg:flex-nowrap lg:px-3">
                                                                                    <Button
                                                                                        type="button"
                                                                                        variant="outline"
                                                                                        onClick={closeTransactionEditor}
                                                                                        disabled={isUpdatingCategory}
                                                                                        className={`w-24 ${transactionEditorButtonClasses}`}
                                                                                    >
                                                                                        Cancel
                                                                                    </Button>
                                                                                    <Button
                                                                                        type="button"
                                                                                        onClick={updateTransaction}
                                                                                        className="w-24 bg-brand text-white! shadow-sm hover:bg-brand/90 disabled:opacity-100 disabled:bg-brand/65 disabled:text-white!"
                                                                                        disabled={
                                                                                            isUpdatingCategory
                                                                                            || !hasTransactionChanges
                                                                                        }
                                                                                    >
                                                                                        {isUpdatingCategory ? 'Saving...' : 'Save'}
                                                                                    </Button>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            ) : null}
                                                        </Fragment>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            );
                        })}

                        {groupedTransactions.length === 0 ? (
                            <div className="px-4 py-8 text-center text-sm text-slate-500">
                                No transactions match the selected filters.
                            </div>
                        ) : null}
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
