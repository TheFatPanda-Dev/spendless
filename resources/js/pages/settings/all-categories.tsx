import { Head, useForm } from '@inertiajs/react';
import {
    ArrowDownCircle,
    ArrowUpCircle,
    ChevronDown,
    ChevronRight,
    Layers3,
    Search,
    Sparkles,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    CategoryIcon,
    CategoryColorSwatch,
    categoryIconNames,
    isHexColor,
    normalizeHexColor,
    resolveCategoryColorPresentation,
} from '@/components/category-icon';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { BreadcrumbItem } from '@/types';

type CategoryNode = {
    id: number;
    name: string;
    type: 'expense' | 'income';
    icon: string;
    color: string;
    parent_id: number | null;
    children: CategoryNode[];
};

type ParentOption = {
    id: number;
    name: string;
    depth: number;
    icon: string;
    color: string;
};

type CategoryOption = {
    value: string;
    label: string;
};

type CategorySearchResult = {
    id: number;
    name: string;
    icon: string;
    color: string;
    depth: number;
    path: string;
    childCount: number;
};

type CategoryColorOption = CategoryOption & {
    background: string;
    foreground: string;
};

type FormState = {
    name: string;
    type: 'expense' | 'income';
    icon: string;
    color: string;
    parent_id: string;
};

type Props = {
    incomeCategories: CategoryNode[];
    expenseCategories: CategoryNode[];
    parentOptions: {
        income: ParentOption[];
        expense: ParentOption[];
    };
    categoryTypes: CategoryOption[];
    categoryColors: CategoryColorOption[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'All Categories',
        href: '/settings/all-categories',
    },
];

function toTitleLabel(value: string): string {
    return value
        .split('-')
        .map((segment) =>
            segment.length === 0
                ? segment
                : segment[0].toUpperCase() + segment.slice(1),
        )
        .join(' ');
}

const allIconOptions: CategoryOption[] = Array.from(
    new Map(
        categoryIconNames.map((name) => [
            name,
            {
                value: name,
                label: toTitleLabel(name),
            },
        ]),
    ).values(),
).sort((left, right) => left.label.localeCompare(right.label));

function collectBranchCategoryIds(categories: CategoryNode[]): number[] {
    return categories.flatMap((category) => {
        const childBranchIds = collectBranchCategoryIds(category.children);

        if (category.children.length === 0) {
            return childBranchIds;
        }

        return [category.id, ...childBranchIds];
    });
}

function collectAllCategoryIds(categories: CategoryNode[]): number[] {
    return categories.flatMap((category) => [
        category.id,
        ...collectAllCategoryIds(category.children),
    ]);
}

function flattenCategoryTree(
    categories: CategoryNode[],
    trail: string[] = [],
): CategorySearchResult[] {
    return categories.flatMap((category) => {
        const pathSegments = [...trail, category.name];

        return [
            {
                id: category.id,
                name: category.name,
                icon: category.icon,
                color: category.color,
                depth: trail.length,
                path: pathSegments.join(' → '),
                childCount: category.children.length,
            },
            ...flattenCategoryTree(category.children, pathSegments),
        ];
    });
}

function CategoryTree({
    categories,
    colorMap,
    expandedIds,
    onToggle,
    depth = 0,
}: {
    categories: CategoryNode[];
    colorMap: Map<string, CategoryColorOption>;
    expandedIds: Set<number>;
    onToggle: (categoryId: number) => void;
    depth?: number;
}) {
    if (categories.length === 0) {
        return null;
    }

    return (
        <div className="space-y-3">
            {categories.map((category) => {
                const color = resolveCategoryColorPresentation(category.color, colorMap);
                const hasChildren = category.children.length > 0;
                const isExpanded = expandedIds.has(category.id);

                return (
                    <div key={category.id} className="space-y-3">
                        {hasChildren ? (
                            <button
                                type="button"
                                onClick={() => onToggle(category.id)}
                                aria-label={isExpanded ? `Collapse ${category.name}` : `Expand ${category.name}`}
                                className="flex w-full items-center gap-3 rounded-2xl border border-brand/15 bg-black/15 p-3 text-left shadow-sm shadow-brand/5 transition hover:bg-white/3"
                            >
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-xl border border-brand/15 bg-background/50 text-muted-foreground transition hover:border-brand/25 hover:text-foreground">
                                    <ChevronRight className={`size-4 transition ${isExpanded ? 'rotate-90 text-brand' : ''}`} />
                                </div>

                                <CategoryIcon
                                    name={category.icon}
                                    variant="badge"
                                    presentation={color}
                                    iconSize={24}
                                />

                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-foreground">
                                        {category.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {`${category.children.length} subcategories`}
                                    </p>
                                </div>
                            </button>
                        ) : (
                            <div className="rounded-2xl border border-brand/15 bg-black/15 p-3 shadow-sm shadow-brand/5">
                                <div className="flex items-center gap-3">
                                    <div className="flex size-8 shrink-0 items-center justify-center rounded-xl border border-dashed border-brand/10 bg-background/35 text-muted-foreground/60">
                                        <div className="size-1.5 rounded-full bg-current" />
                                    </div>

                                    <CategoryIcon
                                        name={category.icon}
                                        variant="badge"
                                        presentation={color}
                                        iconSize={24}
                                    />

                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-foreground">
                                            {category.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {depth === 0 ? 'Main category' : 'Subcategory'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {hasChildren && isExpanded ? (
                            <div className="border-l border-brand/15 pl-3">
                                <CategoryTree
                                    categories={category.children}
                                    colorMap={colorMap}
                                    expandedIds={expandedIds}
                                    onToggle={onToggle}
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

function ParentCategoryTree({
    categories,
    colorMap,
    expandedIds,
    selectedParentId,
    onSelect,
    depth = 0,
}: {
    categories: CategoryNode[];
    colorMap: Map<string, CategoryColorOption>;
    expandedIds: Set<number>;
    selectedParentId: string;
    onSelect: (category: CategoryNode) => void;
    depth?: number;
}) {
    if (categories.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            {categories.map((category) => {
                const color = resolveCategoryColorPresentation(category.color, colorMap);
                const hasChildren = category.children.length > 0;
                const isExpanded = expandedIds.has(category.id);
                const isSelected = selectedParentId === String(category.id);

                return (
                    <div key={category.id} className="space-y-2">
                        <button
                            type="button"
                            onClick={() => onSelect(category)}
                            aria-expanded={hasChildren ? isExpanded : undefined}
                            className={`flex w-full items-center gap-3 rounded-2xl border px-3 py-3 text-left transition ${
                                isSelected
                                    ? 'border-brand/40 bg-brand/15 text-foreground'
                                    : 'border-border bg-background/50 text-muted-foreground hover:border-brand/20 hover:text-foreground'
                            }`}
                        >
                            <div className={`flex size-9 shrink-0 items-center justify-center rounded-2xl border ${hasChildren ? 'border-brand/15 bg-black/15 text-muted-foreground' : 'border-dashed border-brand/10 bg-background/35 text-muted-foreground/60'}`}>
                                {hasChildren ? (
                                    <ChevronRight className={`size-4 transition ${isExpanded ? 'rotate-90 text-brand' : ''}`} />
                                ) : (
                                    <div className="size-1.5 rounded-full bg-current" />
                                )}
                            </div>

                            <div
                                className="flex min-w-0 flex-1 items-center gap-3"
                                style={{ paddingLeft: `${depth * 0.65}rem` }}
                            >
                                <CategoryIcon
                                    name={category.icon}
                                    variant="badge"
                                    presentation={color}
                                    className="size-9 rounded-2xl"
                                    iconSize={18}
                                />

                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-foreground">
                                        {category.name}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {hasChildren
                                            ? `${category.children.length} subcategories`
                                            : depth === 0
                                                ? 'Main category'
                                                : 'Subcategory'}
                                    </p>
                                </div>
                            </div>
                        </button>

                        {hasChildren && isExpanded ? (
                            <div className="border-l border-brand/15 pl-3">
                                <ParentCategoryTree
                                    categories={category.children}
                                    colorMap={colorMap}
                                    expandedIds={expandedIds}
                                    selectedParentId={selectedParentId}
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

export default function AllCategories({
    incomeCategories,
    expenseCategories,
    parentOptions,
    categoryTypes,
    categoryColors,
}: Props) {
    const [iconPickerOpen, setIconPickerOpen] = useState(false);
    const [colorPickerOpen, setColorPickerOpen] = useState(false);
    const [typePickerOpen, setTypePickerOpen] = useState(false);
    const [parentPickerOpen, setParentPickerOpen] = useState(false);
    const [iconQuery, setIconQuery] = useState('');
    const [parentQuery, setParentQuery] = useState('');
    const iconPickerRef = useRef<HTMLDivElement | null>(null);
    const colorPickerRef = useRef<HTMLDivElement | null>(null);
    const typePickerRef = useRef<HTMLDivElement | null>(null);
    const parentPickerRef = useRef<HTMLDivElement | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm<FormState>({
        name: '',
        type: 'expense',
        icon: 'wallet',
        color: 'emerald',
        parent_id: '',
    });

    const activeParentOptions = parentOptions[data.type] ?? [];
    const activeCategoryTree =
        data.type === 'income' ? incomeCategories : expenseCategories;
    const colorMap = new Map(
        categoryColors.map((option) => [option.value, option]),
    );
    const incomeBranchIds = useMemo(
        () => collectBranchCategoryIds(incomeCategories),
        [incomeCategories],
    );
    const expenseBranchIds = useMemo(
        () => collectBranchCategoryIds(expenseCategories),
        [expenseCategories],
    );
    const [expandedIncomeIds, setExpandedIncomeIds] = useState<Set<number>>(
        () => new Set(),
    );
    const [expandedExpenseIds, setExpandedExpenseIds] = useState<Set<number>>(
        () => new Set(),
    );
    const [expandedParentIds, setExpandedParentIds] = useState<Set<number>>(
        () => new Set(),
    );
    const totalRootCategories =
        incomeCategories.length + expenseCategories.length;
    const selectedTypeOption =
        categoryTypes.find((option) => option.value === data.type)
        ?? categoryTypes[0];
    const selectedParentOption =
        activeParentOptions.find(
            (option) => String(option.id) === data.parent_id,
        ) ?? null;
    const selectedColor = resolveCategoryColorPresentation(data.color, colorMap);
    const flattenedActiveCategories = useMemo(
        () => flattenCategoryTree(activeCategoryTree),
        [activeCategoryTree],
    );
    const filteredIconOptions = useMemo(() => {
        const query = iconQuery.trim().toLowerCase();

        if (query === '') {
            return [];
        }

        return allIconOptions
            .map((option) => {
                const label = option.label.toLowerCase();
                const value = option.value.toLowerCase();
                const startsWithScore =
                    label.startsWith(query) || value.startsWith(query) ? 0 : 1;
                const includesMatch =
                    label.includes(query) || value.includes(query);

                if (!includesMatch) {
                    return null;
                }

                return {
                    option,
                    score: startsWithScore,
                };
            })
            .filter((entry): entry is { option: CategoryOption; score: number } => entry !== null)
            .sort((left, right) => {
                if (left.score !== right.score) {
                    return left.score - right.score;
                }

                return left.option.label.localeCompare(right.option.label);
            })
            .slice(0, 50)
            .map((entry) => entry.option);
    }, [iconQuery]);
    const filteredParentResults = useMemo(() => {
        const query = parentQuery.trim().toLowerCase();

        if (query === '') {
            return [];
        }

        return flattenedActiveCategories
            .map((category) => {
                const name = category.name.toLowerCase();
                const path = category.path.toLowerCase();
                const matches = name.includes(query) || path.includes(query);

                if (!matches) {
                    return null;
                }

                return {
                    category,
                    score: name.startsWith(query) ? 0 : path.startsWith(query) ? 1 : 2,
                };
            })
            .filter((entry): entry is { category: CategorySearchResult; score: number } => entry !== null)
            .sort((left, right) => {
                if (left.score !== right.score) {
                    return left.score - right.score;
                }

                return left.category.path.localeCompare(right.category.path);
            })
            .slice(0, 50)
            .map((entry) => entry.category);
    }, [flattenedActiveCategories, parentQuery]);

    useEffect(() => {
        setExpandedIncomeIds((current) => {
            const availableIds = new Set(incomeBranchIds);

            return new Set([...current].filter((id) => availableIds.has(id)));
        });
    }, [incomeBranchIds]);

    useEffect(() => {
        setExpandedExpenseIds((current) => {
            const availableIds = new Set(expenseBranchIds);

            return new Set([...current].filter((id) => availableIds.has(id)));
        });
    }, [expenseBranchIds]);

    useEffect(() => {
        setExpandedParentIds((current) => {
            const availableIds = new Set(collectAllCategoryIds(activeCategoryTree));

            return new Set([...current].filter((id) => availableIds.has(id)));
        });
    }, [activeCategoryTree]);

    useEffect(() => {
        const handlePointerDown = (event: MouseEvent): void => {
            const target = event.target;

            if (!(target instanceof Node)) {
                return;
            }

            if (
                iconPickerRef.current !== null
                && !iconPickerRef.current.contains(target)
            ) {
                setIconPickerOpen(false);
            }

            if (
                colorPickerRef.current !== null
                && !colorPickerRef.current.contains(target)
            ) {
                setColorPickerOpen(false);
            }

            if (
                typePickerRef.current !== null
                && !typePickerRef.current.contains(target)
            ) {
                setTypePickerOpen(false);
            }

            if (
                parentPickerRef.current !== null
                && !parentPickerRef.current.contains(target)
            ) {
                setParentPickerOpen(false);
                setParentQuery('');
            }
        };

        document.addEventListener('mousedown', handlePointerDown);

        return () => {
            document.removeEventListener('mousedown', handlePointerDown);
        };
    }, []);

    const submit = (): void => {
        post('/settings/all-categories', {
            preserveScroll: true,
            onSuccess: () => {
                reset('name', 'parent_id');
                setIconPickerOpen(false);
                setColorPickerOpen(false);
                setTypePickerOpen(false);
                setParentPickerOpen(false);
                setIconQuery('');
                setParentQuery('');
            },
        });
    };

    const toggleParentCategory = (categoryId: number): void => {
        setExpandedParentIds((current) => {
            const next = new Set(current);

            if (next.has(categoryId)) {
                next.delete(categoryId);
            } else {
                next.add(categoryId);
            }

            return next;
        });
    };

    const selectParentCategory = (category: CategoryNode): void => {
        setData('parent_id', String(category.id));

        if (category.children.length > 0) {
            toggleParentCategory(category.id);

            return;
        }

        setParentPickerOpen(false);
        setParentQuery('');
    };

    const toggleIncomeCategory = (categoryId: number): void => {
        setExpandedIncomeIds((current) => {
            const next = new Set(current);

            if (next.has(categoryId)) {
                next.delete(categoryId);
            } else {
                next.add(categoryId);
            }

            return next;
        });
    };

    const toggleExpenseCategory = (categoryId: number): void => {
        setExpandedExpenseIds((current) => {
            const next = new Set(current);

            if (next.has(categoryId)) {
                next.delete(categoryId);
            } else {
                next.add(categoryId);
            }

            return next;
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="All Categories" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="relative overflow-hidden rounded-[28px] border border-brand/20 bg-linear-to-br from-brand/10 via-background to-background p-5 sm:p-6">
                        <div className="absolute inset-y-0 right-0 hidden w-1/2 bg-[radial-gradient(circle_at_top_right,rgba(16,185,129,0.22),transparent_65%)] lg:block" />

                        <div className="relative grid gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,0.9fr)] lg:items-start">
                            <div className="space-y-4">
                                <div className="inline-flex items-center gap-2 rounded-full border border-brand/25 bg-brand/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-brand">
                                    <Sparkles className="size-3.5" />
                                    Category Studio
                                </div>

                                <Heading
                                    variant="small"
                                    title="Shape your income and expense structure"
                                    description="Create main categories, nest subcategories, and build a cleaner budgeting system around your own naming and color language."
                                />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                                <Card className="border-brand/20 bg-black/20 py-4 shadow-none">
                                    <CardContent className="px-4">
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-10 items-center justify-center rounded-2xl bg-emerald-500/15 text-emerald-300">
                                                <ArrowUpCircle className="size-5" />
                                            </div>
                                            <div>
                                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    Income
                                                </p>
                                                <p className="text-2xl font-semibold text-foreground">
                                                    {incomeCategories.length}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="border-brand/20 bg-black/20 py-4 shadow-none">
                                    <CardContent className="px-4">
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-10 items-center justify-center rounded-2xl border border-red-400/20 bg-red-500/18 text-red-300 shadow-[0_0_24px_rgba(239,68,68,0.28)]">
                                                <ArrowDownCircle className="size-5" />
                                            </div>
                                            <div>
                                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    Expense
                                                </p>
                                                <p className="text-2xl font-semibold text-foreground">
                                                    {expenseCategories.length}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card className="border-brand/20 bg-black/20 py-4 shadow-none">
                                    <CardContent className="px-4">
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-10 items-center justify-center rounded-2xl bg-sky-500/15 text-sky-300">
                                                <Layers3 className="size-5" />
                                            </div>
                                            <div>
                                                <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">
                                                    Main categories
                                                </p>
                                                <p className="text-2xl font-semibold text-foreground">
                                                    {totalRootCategories}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.25fr)]">
                        <Card className="relative overflow-visible border-brand/20 bg-linear-to-br from-brand/8 via-card to-card shadow-[0_24px_80px_-48px_rgba(16,185,129,0.75)]">
                            <CardHeader className="border-b border-brand/10 pb-4">
                                <CardTitle className="text-xl">
                                    Create a new category
                                </CardTitle>
                                <CardDescription>
                                    Set the name first, then choose how to place it, type, icon, and color in one compact row.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="space-y-6 overflow-visible p-5">
                                <div className="relative z-20 rounded-3xl border border-brand/15 bg-black/10 p-3 sm:p-4">
                                    <div className="grid gap-3">
                                        <div className="grid min-w-0 gap-2">
                                            <Label htmlFor="category-name">Name</Label>
                                            <Input
                                                id="category-name"
                                                value={data.name}
                                                onChange={(event) =>
                                                    setData(
                                                        'name',
                                                        event.target.value.length === 0
                                                            ? ''
                                                            : event.target.value[0].toUpperCase() +
                                                                  event.target.value.slice(1),
                                                    )
                                                }
                                                placeholder="New category name"
                                                maxLength={80}
                                                className="h-11 rounded-2xl bg-background/60"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-3 md:grid-cols-[140px_minmax(0,1.3fr)] md:items-end">
                                            <div className="grid min-w-0 gap-2">
                                                <Label htmlFor="category-type-trigger">Type</Label>
                                                <div className="relative" ref={typePickerRef}>
                                                    <button
                                                        id="category-type-trigger"
                                                        type="button"
                                                        onClick={() => {
                                                            setTypePickerOpen((current) => !current);
                                                            setIconPickerOpen(false);
                                                            setColorPickerOpen(false);
                                                            setParentPickerOpen(false);
                                                        }}
                                                        aria-haspopup="listbox"
                                                        aria-expanded={typePickerOpen}
                                                        className="flex h-11 w-full items-center justify-between rounded-2xl border border-border bg-background/60 px-3 text-left transition hover:border-brand/25"
                                                    >
                                                        <div className="flex min-w-0 items-center gap-2.5">
                                                            <div className={`flex size-7 shrink-0 items-center justify-center rounded-full border ${data.type === 'income' ? 'border-emerald-400/30 bg-emerald-500/15 text-emerald-300' : 'border-red-400/35 bg-red-500/18 text-red-300 shadow-[0_0_18px_rgba(239,68,68,0.18)]'}`}>
                                                                {data.type === 'income' ? (
                                                                    <ArrowUpCircle size={18} />
                                                                ) : (
                                                                    <ArrowDownCircle size={18} />
                                                                )}
                                                            </div>
                                                            <span className="truncate text-sm font-medium text-foreground">
                                                                {selectedTypeOption?.label ?? 'Select type'}
                                                            </span>
                                                        </div>
                                                        <ChevronDown className="size-4 text-muted-foreground" />
                                                    </button>

                                                    {typePickerOpen ? (
                                                        <div className="absolute left-0 z-30 mt-2 w-full min-w-44 rounded-3xl border border-brand/20 bg-background/95 p-2 shadow-2xl shadow-black/30 backdrop-blur">
                                                            <div className="grid gap-2">
                                                                {categoryTypes.map((typeOption) => {
                                                                    const isActive = data.type === typeOption.value;
                                                                    const isIncome = typeOption.value === 'income';

                                                                    return (
                                                                        <button
                                                                            key={typeOption.value}
                                                                            type="button"
                                                                            onClick={() => {
                                                                                setData('type', typeOption.value as FormState['type']);
                                                                                setData('parent_id', '');
                                                                                setTypePickerOpen(false);
                                                                            }}
                                                                            className={`flex items-center gap-3 rounded-2xl border px-3 py-3 text-left transition ${
                                                                                isActive
                                                                                    ? 'border-brand/40 bg-brand/15 text-foreground'
                                                                                    : 'border-border bg-background/50 text-muted-foreground hover:border-brand/20 hover:text-foreground'
                                                                            }`}
                                                                        >
                                                                            <div className={`flex size-9 shrink-0 items-center justify-center rounded-2xl border ${isIncome ? 'border-emerald-400/30 bg-emerald-500/15 text-emerald-300' : 'border-red-400/35 bg-red-500/18 text-red-300 shadow-[0_0_18px_rgba(239,68,68,0.18)]'}`}>
                                                                                {isIncome ? (
                                                                                    <ArrowUpCircle className="size-4.5" />
                                                                                ) : (
                                                                                    <ArrowDownCircle className="size-4.5" />
                                                                                )}
                                                                            </div>
                                                                            <div className="min-w-0 flex-1">
                                                                                <p className="truncate text-sm font-semibold">
                                                                                    {typeOption.label}
                                                                                </p>
                                                                                <p className="truncate text-xs text-muted-foreground">
                                                                                    {isIncome ? 'Money coming in' : 'Money going out'}
                                                                                </p>
                                                                            </div>
                                                                        </button>
                                                                    );
                                                                })}
                                                            </div>
                                                        </div>
                                                    ) : null}
                                                </div>
                                                <InputError message={errors.type} />
                                            </div>

                                            <div className="grid min-w-0 gap-2">
                                                <Label htmlFor="category-parent-trigger">Create as</Label>
                                                <div className="relative" ref={parentPickerRef}>
                                                    <button
                                                        id="category-parent-trigger"
                                                        type="button"
                                                        onClick={() => {
                                                            setParentPickerOpen((current) => !current);
                                                            setIconPickerOpen(false);
                                                            setColorPickerOpen(false);
                                                            setTypePickerOpen(false);
                                                        }}
                                                        aria-haspopup="listbox"
                                                        aria-expanded={parentPickerOpen}
                                                        className="flex h-11 w-full items-center justify-between rounded-2xl border border-border bg-background/60 px-3 text-left transition hover:border-brand/25"
                                                    >
                                                        <div className="flex min-w-0 items-center gap-2.5">
                                                            {selectedParentOption ? (
                                                                <>
                                                                    <CategoryIcon
                                                                        name={selectedParentOption.icon}
                                                                        variant="badge"
                                                                        presentation={resolveCategoryColorPresentation(selectedParentOption.color, colorMap)}
                                                                        className="size-7 rounded-[14px]"
                                                                        iconSize={16}
                                                                    />
                                                                    <span className="truncate text-sm font-medium text-foreground">
                                                                        {selectedParentOption.name}
                                                                    </span>
                                                                </>
                                                            ) : (
                                                                <span className="truncate text-sm font-medium text-foreground">
                                                                    Main category
                                                                </span>
                                                            )}
                                                        </div>
                                                        <ChevronDown className="size-4 text-muted-foreground" />
                                                    </button>

                                                    {parentPickerOpen ? (
                                                        <div className="absolute left-0 z-30 mt-2 w-full min-w-56 rounded-3xl border border-brand/20 bg-background/95 p-2 shadow-2xl shadow-black/30 backdrop-blur">
                                                            <div className="grid gap-2">
                                                                <div className="relative">
                                                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                                                    <Input
                                                                        value={parentQuery}
                                                                        onChange={(event) =>
                                                                            setParentQuery(event.target.value)
                                                                        }
                                                                        placeholder="Search categories..."
                                                                        className="pl-9"
                                                                    />
                                                                </div>

                                                                <div className="grid max-h-72 gap-2 overflow-y-auto pr-1">
                                                                    {parentQuery.trim() !== '' ? (
                                                                        filteredParentResults.length === 0 ? (
                                                                            <div className="rounded-2xl border border-dashed border-border px-4 py-5 text-sm text-muted-foreground">
                                                                                No categories matched your search.
                                                                            </div>
                                                                        ) : (
                                                                            filteredParentResults.map((category) => {
                                                                                const isActive = data.parent_id === String(category.id);
                                                                                const resultColor = resolveCategoryColorPresentation(category.color, colorMap);

                                                                                return (
                                                                                    <button
                                                                                        key={category.id}
                                                                                        type="button"
                                                                                        onClick={() => {
                                                                                            setData('parent_id', String(category.id));
                                                                                            setParentPickerOpen(false);
                                                                                            setParentQuery('');
                                                                                        }}
                                                                                        className={`flex items-center gap-3 rounded-2xl border px-3 py-3 text-left transition ${
                                                                                            isActive
                                                                                                ? 'border-brand/40 bg-brand/15 text-foreground'
                                                                                                : 'border-border bg-background/50 text-muted-foreground hover:border-brand/20 hover:text-foreground'
                                                                                        }`}
                                                                                    >
                                                                                        <CategoryIcon
                                                                                            name={category.icon}
                                                                                            variant="badge"
                                                                                            presentation={resultColor}
                                                                                            className="size-9 rounded-2xl"
                                                                                            iconSize={18}
                                                                                        />
                                                                                        <div className="min-w-0 flex-1">
                                                                                            <p className="truncate text-sm font-semibold text-foreground">
                                                                                                {category.name}
                                                                                            </p>
                                                                                            <p className="truncate text-xs text-muted-foreground">
                                                                                                {category.path}
                                                                                            </p>
                                                                                        </div>
                                                                                    </button>
                                                                                );
                                                                            })
                                                                        )
                                                                    ) : (
                                                                        <>
                                                                            <button
                                                                                type="button"
                                                                                onClick={() => {
                                                                                    setData('parent_id', '');
                                                                                    setParentPickerOpen(false);
                                                                                    setParentQuery('');
                                                                                }}
                                                                                className={`flex items-center gap-3 rounded-2xl border px-3 py-3 text-left transition ${
                                                                                    data.parent_id === ''
                                                                                        ? 'border-brand/40 bg-brand/15 text-foreground'
                                                                                        : 'border-border bg-background/50 text-muted-foreground hover:border-brand/20 hover:text-foreground'
                                                                                }`}
                                                                            >
                                                                                <div className="flex size-9 shrink-0 items-center justify-center rounded-2xl border border-dashed border-brand/20 bg-black/15 text-brand/80">
                                                                                    <Layers3 className="size-4.5" />
                                                                                </div>
                                                                                <div className="min-w-0 flex-1">
                                                                                    <p className="truncate text-sm font-semibold">Main category</p>
                                                                                    <p className="truncate text-xs text-muted-foreground">No parent. Create a new main category.</p>
                                                                                </div>
                                                                            </button>

                                                                            <ParentCategoryTree
                                                                                categories={activeCategoryTree}
                                                                                colorMap={colorMap}
                                                                                expandedIds={expandedParentIds}
                                                                                selectedParentId={data.parent_id}
                                                                                onSelect={selectParentCategory}
                                                                            />
                                                                        </>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ) : null}
                                                </div>
                                                <InputError message={errors.parent_id} />
                                            </div>
                                        </div>

                                        <div className="grid gap-3 md:grid-cols-[72px_72px] md:items-end">

                                        <div className="grid min-w-0 gap-2">
                                            <Label>Icon</Label>
                                            <div className="relative" ref={iconPickerRef}>
                                                <button
                                                    id="category-icon-trigger"
                                                    type="button"
                                                    onClick={() => {
                                                        setIconPickerOpen((current) => !current);
                                                        setColorPickerOpen(false);
                                                        setTypePickerOpen(false);
                                                        setParentPickerOpen(false);
                                                    }}
                                                    aria-haspopup="dialog"
                                                    aria-expanded={iconPickerOpen}
                                                    className="flex h-11 w-full items-center justify-between rounded-2xl border border-border bg-background/60 px-3 text-left transition hover:border-brand/25"
                                                >
                                                    <CategoryIcon
                                                        name={data.icon}
                                                        variant="badge"
                                                        presentation={selectedColor}
                                                        className="size-8 rounded-[14px]"
                                                        iconSize={18}
                                                    />
                                                    <ChevronDown className="size-4 text-muted-foreground" />
                                                </button>

                                                {iconPickerOpen ? (
                                                    <div className="absolute left-0 z-30 mt-2 w-80 rounded-3xl border border-brand/20 bg-background/95 p-3 shadow-2xl shadow-black/30 backdrop-blur">
                                                        <Input
                                                            value={iconQuery}
                                                            onChange={(event) =>
                                                                setIconQuery(event.target.value)
                                                            }
                                                            placeholder="Search Lucide icons..."
                                                            className="mb-3"
                                                        />

                                                        <div className="grid gap-2">
                                                            {iconQuery.trim() === '' ? (
                                                                <div className="rounded-2xl border border-dashed border-border px-4 py-5 text-sm text-muted-foreground">
                                                                    Search across all Lucide icons.
                                                                </div>
                                                            ) : filteredIconOptions.length === 0 ? (
                                                                <div className="rounded-2xl border border-dashed border-border px-4 py-5 text-sm text-muted-foreground">
                                                                    No icons matched your search.
                                                                </div>
                                                            ) : (
                                                                filteredIconOptions.map((iconOption) => {
                                                                    const isActive = data.icon === iconOption.value;

                                                                    return (
                                                                        <button
                                                                            key={iconOption.value}
                                                                            type="button"
                                                                            onClick={() => {
                                                                                setData('icon', iconOption.value);
                                                                                setIconPickerOpen(false);
                                                                            }}
                                                                            className={`flex items-center gap-3 rounded-2xl border px-3 py-3 text-left transition ${
                                                                                isActive
                                                                                    ? 'border-brand/40 bg-brand/15 text-foreground'
                                                                                    : 'border-border bg-background/50 text-muted-foreground hover:border-brand/20 hover:text-foreground'
                                                                            }`}
                                                                        >
                                                                            <CategoryIcon
                                                                                name={iconOption.value}
                                                                                variant="badge"
                                                                                presentation={selectedColor}
                                                                                className="size-9 rounded-[18px]"
                                                                                iconSize={18}
                                                                            />
                                                                            <div className="min-w-0 flex-1">
                                                                                <p className="truncate text-sm font-semibold">
                                                                                    {iconOption.label}
                                                                                </p>
                                                                                <p className="truncate text-xs text-muted-foreground">
                                                                                    {iconOption.value}
                                                                                </p>
                                                                            </div>
                                                                        </button>
                                                                    );
                                                                })
                                                            )}
                                                        </div>
                                                    </div>
                                                ) : null}
                                            </div>
                                            <InputError message={errors.icon} />
                                        </div>

                                        <div className="grid min-w-0 gap-2">
                                            <Label>Color</Label>
                                            <div className="relative" ref={colorPickerRef}>
                                                <button
                                                    id="category-color-trigger"
                                                    type="button"
                                                    onClick={() => {
                                                        setColorPickerOpen((current) => !current);
                                                        setIconPickerOpen(false);
                                                        setTypePickerOpen(false);
                                                        setParentPickerOpen(false);
                                                    }}
                                                    aria-haspopup="dialog"
                                                    aria-expanded={colorPickerOpen}
                                                    className="flex h-11 w-full items-center justify-between rounded-2xl border border-border bg-background/60 px-3 text-left transition hover:border-brand/25"
                                                >
                                                    <CategoryColorSwatch
                                                        presentation={selectedColor}
                                                        className="size-8 rounded-[14px]"
                                                    />
                                                    <ChevronDown className="size-4 text-muted-foreground" />
                                                </button>

                                                {colorPickerOpen ? (
                                                    <div className="absolute left-0 z-30 mt-2 w-80 rounded-3xl border border-brand/20 bg-background/95 p-4 shadow-2xl shadow-black/30 backdrop-blur">
                                                        <div className="grid gap-3 sm:grid-cols-[auto_minmax(0,1fr)] sm:items-center">
                                                            <div className="relative size-14">
                                                                <CategoryColorSwatch
                                                                    presentation={selectedColor}
                                                                    className="size-14 rounded-[20px]"
                                                                />
                                                                <input
                                                                    type="color"
                                                                    value={
                                                                        isHexColor(data.color)
                                                                            ? normalizeHexColor(data.color)
                                                                            : '#10b981'
                                                                    }
                                                                    onChange={(event) =>
                                                                        setData(
                                                                            'color',
                                                                            event.target.value,
                                                                        )
                                                                    }
                                                                    className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                                                />
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <div>
                                                                    <p className="text-sm font-semibold text-foreground">
                                                                        Custom color picker
                                                                    </p>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        Pick any color and edit the exact hex value here.
                                                                    </p>
                                                                </div>
                                                                <Input
                                                                    id="category-color-hex"
                                                                    value={
                                                                        isHexColor(data.color)
                                                                            ? normalizeHexColor(data.color)
                                                                            : ''
                                                                    }
                                                                    onChange={(event) =>
                                                                        setData(
                                                                            'color',
                                                                            event.target.value,
                                                                        )
                                                                    }
                                                                    placeholder="#10b981"
                                                                    spellCheck={false}
                                                                    autoCapitalize="off"
                                                                    autoCorrect="off"
                                                                />
                                                                <p className="text-xs text-muted-foreground">
                                                                    Use values like #10b981 or #2dd4bf.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ) : null}
                                            </div>
                                            <InputError message={errors.color} />
                                        </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-end">
                                    <Button
                                        type="button"
                                        disabled={processing}
                                        onClick={submit}
                                        className="rounded-full px-5"
                                    >
                                        {processing
                                            ? 'Creating...'
                                            : 'Create category'}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="grid gap-6 xl:grid-cols-2">
                            <Card className="border-brand/20 bg-linear-to-br from-emerald-500/8 via-card to-card shadow-none">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <ArrowUpCircle className="size-5 text-emerald-400" />
                                        Income categories
                                    </CardTitle>
                                    <CardDescription>
                                        Organize salary, freelance, reimbursements, and any other money coming in.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {incomeCategories.length === 0 ? (
                                        <div className="rounded-2xl border border-dashed border-brand/20 bg-black/10 p-4 text-sm text-muted-foreground">
                                            No income categories yet. Create your first one from the panel on the left.
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    onClick={() => setExpandedIncomeIds(new Set(incomeBranchIds))}
                                                    className="h-8 rounded-full border border-brand/15 px-3 text-xs text-muted-foreground hover:border-brand/30 hover:bg-brand/10 hover:text-foreground"
                                                >
                                                    Expand all
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    onClick={() => setExpandedIncomeIds(new Set())}
                                                    className="h-8 rounded-full border border-brand/15 px-3 text-xs text-muted-foreground hover:border-brand/30 hover:bg-brand/10 hover:text-foreground"
                                                >
                                                    Collapse all
                                                </Button>
                                            </div>

                                            <CategoryTree
                                                categories={incomeCategories}
                                                colorMap={colorMap}
                                                expandedIds={expandedIncomeIds}
                                                onToggle={toggleIncomeCategory}
                                            />
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <Card className="border-brand/20 bg-linear-to-br from-red-500/10 via-card to-card shadow-none">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <ArrowDownCircle className="size-5 text-red-300 drop-shadow-[0_0_10px_rgba(239,68,68,0.45)]" />
                                        Expense categories
                                    </CardTitle>
                                    <CardDescription>
                                        Build spending trees like Vehicles → Car → Petrol or Home → Repairs → Plumbing.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {expenseCategories.length === 0 ? (
                                        <div className="rounded-2xl border border-dashed border-brand/20 bg-black/10 p-4 text-sm text-muted-foreground">
                                            No expense categories yet. Start by creating a main category, then layer subcategories under it.
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    onClick={() => setExpandedExpenseIds(new Set(expenseBranchIds))}
                                                    className="h-8 rounded-full border border-brand/15 px-3 text-xs text-muted-foreground hover:border-brand/30 hover:bg-brand/10 hover:text-foreground"
                                                >
                                                    Expand all
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    onClick={() => setExpandedExpenseIds(new Set())}
                                                    className="h-8 rounded-full border border-brand/15 px-3 text-xs text-muted-foreground hover:border-brand/30 hover:bg-brand/10 hover:text-foreground"
                                                >
                                                    Collapse all
                                                </Button>
                                            </div>

                                            <CategoryTree
                                                categories={expenseCategories}
                                                colorMap={colorMap}
                                                expandedIds={expandedExpenseIds}
                                                onToggle={toggleExpenseCategory}
                                            />
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
