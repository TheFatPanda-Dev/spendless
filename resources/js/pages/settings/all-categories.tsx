import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowDownCircle,
    ArrowUpCircle,
    ChevronDown,
    ChevronRight,
    Layers3,
    LoaderCircle,
    Palette,
    Pipette,
    Search,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { CategoryIcon, CategoryColorSwatch } from '@/components/category-icon';
import {
    categoryIconNames,
    isHexColor,
    normalizeHexColor,
    resolveCategoryColorPresentation,
} from '@/components/category-icon-utils';
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

type SearchableCategoryOption = CategoryOption & {
    searchTerms: string[];
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

const iconSearchAliases: Record<string, string[]> = {
    fuel: ['gas', 'gasoline', 'gas-station', 'gas station', 'petrol'],
};

const searchableIconOptions: SearchableCategoryOption[] = allIconOptions.map(
    (option) => ({
        ...option,
        searchTerms: Array.from(
            new Set([
                option.value.toLowerCase(),
                option.value.toLowerCase().replace(/-/g, ' '),
                option.label.toLowerCase(),
                ...(iconSearchAliases[option.value] ?? []).map((alias) =>
                    alias.toLowerCase(),
                ),
            ]),
        ),
    }),
);

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

const categoryDeleteButtonClasses =
    'absolute top-3 right-3 z-10 size-9 rounded-xl border border-red-400/20 bg-red-500/12 text-red-200 opacity-100 shadow-[0_10px_30px_rgba(0,0,0,0.18)] transition duration-200 hover:border-red-400/35 hover:bg-red-500/18 hover:text-red-100 md:top-1/2 md:right-2 md:size-10 md:-translate-y-1/2 md:translate-x-4 md:opacity-0 md:focus-visible:translate-x-0 md:focus-visible:opacity-100 md:group-hover/category:translate-x-0 md:group-hover/category:opacity-100 md:group-focus-within/category:translate-x-0 md:group-focus-within/category:opacity-100';

const studioPanelSurfaceClasses =
    'border border-brand/15 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(244,250,247,0.98))] shadow-[0_18px_45px_rgba(15,23,42,0.08)] dark:bg-black/10 dark:shadow-none';

const studioFieldSurfaceClasses =
    'border-brand/15 bg-white/92 shadow-[inset_0_1px_0_rgba(255,255,255,0.78)] dark:border-border dark:bg-background/60 dark:shadow-none';

const studioPopoverSurfaceClasses =
    'border border-brand/20 bg-[linear-gradient(180deg,rgba(255,255,255,0.99),rgba(246,251,248,0.98))] shadow-[0_24px_60px_rgba(15,23,42,0.14)] backdrop-blur dark:bg-background/95 dark:shadow-black/30';

const studioIdleOptionClasses =
    'border-brand/15 bg-white/90 text-muted-foreground shadow-[inset_0_1px_0_rgba(255,255,255,0.72)] hover:border-brand/25 hover:bg-brand/5 hover:text-foreground dark:border-border dark:bg-background/50 dark:shadow-none';

const studioSectionFrameClasses =
    'border border-brand/15 bg-white/85 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)] dark:border-white/8 dark:bg-black/15 dark:shadow-none';

function CategoryTree({
    categories,
    colorMap,
    expandedIds,
    onToggle,
    onDelete,
    deletingCategoryId,
    depth = 0,
}: {
    categories: CategoryNode[];
    colorMap: Map<string, CategoryColorOption>;
    expandedIds: Set<number>;
    onToggle: (categoryId: number) => void;
    onDelete: (category: CategoryNode) => void;
    deletingCategoryId: number | null;
    depth?: number;
}) {
    if (categories.length === 0) {
        return null;
    }

    return (
        <div className="min-w-0 space-y-3 overflow-x-hidden">
            {categories.map((category) => {
                const color = resolveCategoryColorPresentation(
                    category.color,
                    colorMap,
                );
                const hasChildren = category.children.length > 0;
                const isExpanded = expandedIds.has(category.id);
                const isDeleting = deletingCategoryId === category.id;

                return (
                    <div
                        key={category.id}
                        className="group/category min-w-0 space-y-3"
                    >
                        {hasChildren ? (
                            <div className="relative min-w-0 overflow-hidden rounded-2xl border border-brand/15 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(244,249,247,0.98))] shadow-[0_14px_36px_rgba(15,23,42,0.06)] transition hover:border-brand/25 hover:bg-[linear-gradient(180deg,rgba(255,255,255,1),rgba(240,249,245,1))] dark:bg-black/15 dark:shadow-sm dark:shadow-brand/5 dark:hover:bg-white/3">
                                <button
                                    type="button"
                                    onClick={() => onToggle(category.id)}
                                    aria-label={
                                        isExpanded
                                            ? `Collapse ${category.name}`
                                            : `Expand ${category.name}`
                                    }
                                    className="flex w-full min-w-0 items-center gap-2.5 p-3 pr-14 text-left md:gap-3 md:pr-16"
                                >
                                    <div className="flex size-8 shrink-0 items-center justify-center rounded-xl border border-brand/15 bg-white/80 text-muted-foreground shadow-[inset_0_1px_0_rgba(255,255,255,0.7)] transition hover:border-brand/25 hover:text-foreground dark:bg-background/50 dark:shadow-none">
                                        <ChevronRight
                                            className={`size-4 transition ${isExpanded ? 'rotate-90 text-brand' : ''}`}
                                        />
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

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => onDelete(category)}
                                    disabled={isDeleting}
                                    aria-label={`Delete ${category.name}`}
                                    className={categoryDeleteButtonClasses}
                                >
                                    {isDeleting ? (
                                        <LoaderCircle className="size-4 animate-spin" />
                                    ) : (
                                        <Trash2 className="size-4" />
                                    )}
                                </Button>
                            </div>
                        ) : (
                            <div className="relative min-w-0 overflow-hidden rounded-2xl border border-brand/15 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(244,249,247,0.98))] p-3 shadow-[0_14px_36px_rgba(15,23,42,0.06)] transition hover:border-brand/25 hover:bg-[linear-gradient(180deg,rgba(255,255,255,1),rgba(240,249,245,1))] dark:bg-black/15 dark:shadow-sm dark:shadow-brand/5 dark:hover:bg-white/3">
                                <div className="flex min-w-0 items-center gap-2.5 pr-14 md:gap-3 md:pr-16">
                                    <div className="flex size-8 shrink-0 items-center justify-center rounded-xl border border-dashed border-brand/15 bg-white/70 text-muted-foreground/70 shadow-[inset_0_1px_0_rgba(255,255,255,0.65)] dark:bg-background/35 dark:shadow-none">
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
                                            {depth === 0
                                                ? 'Main category'
                                                : 'Subcategory'}
                                        </p>
                                    </div>
                                </div>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => onDelete(category)}
                                    disabled={isDeleting}
                                    aria-label={`Delete ${category.name}`}
                                    className={categoryDeleteButtonClasses}
                                >
                                    {isDeleting ? (
                                        <LoaderCircle className="size-4 animate-spin" />
                                    ) : (
                                        <Trash2 className="size-4" />
                                    )}
                                </Button>
                            </div>
                        )}

                        {hasChildren && isExpanded ? (
                            <div className="min-w-0 border-l border-brand/15 pl-2 sm:pl-3">
                                <CategoryTree
                                    categories={category.children}
                                    colorMap={colorMap}
                                    expandedIds={expandedIds}
                                    onToggle={onToggle}
                                    onDelete={onDelete}
                                    deletingCategoryId={deletingCategoryId}
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
    onToggle,
    depth = 0,
}: {
    categories: CategoryNode[];
    colorMap: Map<string, CategoryColorOption>;
    expandedIds: Set<number>;
    selectedParentId: string;
    onSelect: (category: CategoryNode) => void;
    onToggle: (categoryId: number) => void;
    depth?: number;
}) {
    if (categories.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            {categories.map((category) => {
                const color = resolveCategoryColorPresentation(
                    category.color,
                    colorMap,
                );
                const hasChildren = category.children.length > 0;
                const isExpanded = expandedIds.has(category.id);
                const isSelected = selectedParentId === String(category.id);

                return (
                    <div key={category.id} className="space-y-2">
                        <div className="flex items-start gap-3">
                            {hasChildren ? (
                                <button
                                    type="button"
                                    onClick={() => onToggle(category.id)}
                                    aria-label={
                                        isExpanded
                                            ? `Collapse ${category.name}`
                                            : `Expand ${category.name}`
                                    }
                                    aria-expanded={isExpanded}
                                    className="flex size-9 shrink-0 items-center justify-center rounded-2xl border border-brand/15 bg-black/15 text-muted-foreground transition hover:border-brand/25 hover:text-foreground"
                                >
                                    <ChevronRight
                                        className={`size-4 transition ${isExpanded ? 'rotate-90 text-brand' : ''}`}
                                    />
                                </button>
                            ) : (
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-2xl border border-dashed border-brand/10 bg-background/35 text-muted-foreground/60">
                                    <div className="size-1.5 rounded-full bg-current" />
                                </div>
                            )}

                            <button
                                type="button"
                                onClick={() => onSelect(category)}
                                className={`flex min-w-0 flex-1 items-center gap-3 rounded-2xl border px-3 py-3 text-left transition ${
                                    isSelected
                                        ? 'border-brand/40 bg-brand/15 text-foreground'
                                        : 'border-border bg-background/50 text-muted-foreground hover:border-brand/20 hover:text-foreground'
                                }`}
                            >
                                <div
                                    className="flex min-w-0 flex-1 items-center gap-3"
                                    style={{
                                        paddingLeft: `${depth * 0.65}rem`,
                                    }}
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
                        </div>

                        {hasChildren && isExpanded ? (
                            <div className="border-l border-brand/15 pl-3">
                                <ParentCategoryTree
                                    categories={category.children}
                                    colorMap={colorMap}
                                    expandedIds={expandedIds}
                                    selectedParentId={selectedParentId}
                                    onSelect={onSelect}
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

export default function AllCategories({
    incomeCategories,
    expenseCategories,
    parentOptions,
    categoryTypes,
    categoryColors,
}: Props) {
    const [iconPickerOpen, setIconPickerOpen] = useState(false);
    const [colorDialogOpen, setColorDialogOpen] = useState(false);
    const [typePickerOpen, setTypePickerOpen] = useState(false);
    const [parentPickerOpen, setParentPickerOpen] = useState(false);
    const [parentPickerView, setParentPickerView] = useState<
        'choice' | 'browse'
    >('choice');
    const [iconQuery, setIconQuery] = useState('');
    const [parentQuery, setParentQuery] = useState('');
    const [deletingCategoryId, setDeletingCategoryId] = useState<number | null>(
        null,
    );
    const [deleteCandidate, setDeleteCandidate] = useState<CategoryNode | null>(
        null,
    );
    const iconPickerRef = useRef<HTMLDivElement | null>(null);
    const iconSearchInputRef = useRef<HTMLInputElement | null>(null);
    const nativeColorInputRef = useRef<HTMLInputElement | null>(null);
    const typePickerRef = useRef<HTMLDivElement | null>(null);
    const parentPickerRef = useRef<HTMLDivElement | null>(null);

    const { data, setData, post, processing, errors, reset } =
        useForm<FormState>({
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
    const selectedTypeOption =
        categoryTypes.find((option) => option.value === data.type) ??
        categoryTypes[0];
    const selectedParentOption =
        activeParentOptions.find(
            (option) => String(option.id) === data.parent_id,
        ) ?? null;
    const parentTriggerLabel = selectedParentOption
        ? `Subcategory of ${selectedParentOption.name}`
        : 'Main category';
    const selectedColor = resolveCategoryColorPresentation(
        data.color,
        colorMap,
    );
    const flattenedActiveCategories = useMemo(
        () => flattenCategoryTree(activeCategoryTree),
        [activeCategoryTree],
    );
    const filteredIconOptions = useMemo(() => {
        const query = iconQuery.trim().toLowerCase();

        if (query === '') {
            return [];
        }

        return searchableIconOptions
            .map((option) => {
                const label = option.label.toLowerCase();
                const value = option.value.toLowerCase();
                const startsWithScore =
                    label.startsWith(query) || value.startsWith(query) ? 0 : 1;
                const includesMatch = option.searchTerms.some((term) =>
                    term.includes(query),
                );

                if (!includesMatch) {
                    return null;
                }

                return {
                    option,
                    score: startsWithScore,
                };
            })
            .filter(
                (
                    entry,
                ): entry is {
                    option: SearchableCategoryOption;
                    score: number;
                } => entry !== null,
            )
            .sort((left, right) => {
                if (left.score !== right.score) {
                    return left.score - right.score;
                }

                return left.option.label.localeCompare(right.option.label);
            })
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
                    score: name.startsWith(query)
                        ? 0
                        : path.startsWith(query)
                          ? 1
                          : 2,
                };
            })
            .filter(
                (
                    entry,
                ): entry is { category: CategorySearchResult; score: number } =>
                    entry !== null,
            )
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
            const availableIds = new Set(
                collectAllCategoryIds(activeCategoryTree),
            );

            return new Set([...current].filter((id) => availableIds.has(id)));
        });
    }, [activeCategoryTree]);

    useEffect(() => {
        if (!iconPickerOpen) {
            return;
        }

        const frame = window.requestAnimationFrame(() => {
            iconSearchInputRef.current?.focus();
        });

        return () => {
            window.cancelAnimationFrame(frame);
        };
    }, [iconPickerOpen]);

    useEffect(() => {
        const handlePointerDown = (event: MouseEvent): void => {
            const target = event.target;

            if (!(target instanceof Node)) {
                return;
            }

            if (
                iconPickerRef.current !== null &&
                !iconPickerRef.current.contains(target)
            ) {
                setIconPickerOpen(false);
            }

            if (
                typePickerRef.current !== null &&
                !typePickerRef.current.contains(target)
            ) {
                setTypePickerOpen(false);
            }

            if (
                parentPickerRef.current !== null &&
                !parentPickerRef.current.contains(target)
            ) {
                setParentPickerOpen(false);
                setParentPickerView('choice');
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
                setColorDialogOpen(false);
                setTypePickerOpen(false);
                setParentPickerOpen(false);
                setParentPickerView('choice');
                setIconQuery('');
                setParentQuery('');
            },
        });
    };

    const confirmDeleteCategory = (): void => {
        if (deleteCandidate === null) {
            return;
        }

        const category = deleteCandidate;
        setDeletingCategoryId(category.id);

        router.delete(`/settings/all-categories/${category.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteCandidate(null);
            },
            onFinish: () => {
                setDeletingCategoryId((current) =>
                    current === category.id ? null : current,
                );
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
        setParentPickerOpen(false);
        setParentPickerView('choice');
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

                        <div className="relative flex justify-center text-center">
                            <div className="flex max-w-3xl flex-col items-center space-y-4">
                                <div className="inline-flex items-center gap-2 rounded-full border border-brand/25 bg-brand/10 px-3 py-1 text-xs font-semibold tracking-[0.22em] text-brand uppercase">
                                    <Sparkles className="size-3.5" />
                                    Category Studio
                                </div>

                                <Heading
                                    variant="small"
                                    title="Shape your income and expense structure"
                                    description="Create main categories, nest subcategories, and build a cleaner budgeting system around your own naming and color language."
                                />
                            </div>
                        </div>
                    </div>

                    <div className="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.25fr)]">
                        <Card className="relative min-w-0 overflow-visible border-brand/20 bg-[linear-gradient(180deg,rgba(255,255,255,0.99),rgba(242,250,246,0.98))] shadow-[0_24px_80px_-52px_rgba(16,185,129,0.28)] dark:bg-linear-to-br dark:from-brand/8 dark:via-card dark:to-card dark:shadow-[0_24px_80px_-48px_rgba(16,185,129,0.75)]">
                            <CardHeader className="border-b border-brand/10 pb-4">
                                <CardTitle className="text-xl">
                                    Create a new category
                                </CardTitle>
                                <CardDescription>
                                    Set the name first, then choose how to place
                                    it, type, icon, and color in one compact
                                    row.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="min-w-0 space-y-6 overflow-visible p-5">
                                <div
                                    className={`relative z-20 rounded-3xl p-3 sm:p-4 ${studioPanelSurfaceClasses}`}
                                >
                                    <div className="grid gap-3">
                                        <div className="grid min-w-0 gap-2">
                                            <Label htmlFor="category-name">
                                                Name
                                            </Label>
                                            <Input
                                                id="category-name"
                                                value={data.name}
                                                onChange={(event) =>
                                                    setData(
                                                        'name',
                                                        event.target.value
                                                            .length === 0
                                                            ? ''
                                                            : event.target.value[0].toUpperCase() +
                                                                  event.target.value.slice(
                                                                      1,
                                                                  ),
                                                    )
                                                }
                                                placeholder="New category name"
                                                maxLength={80}
                                                className={`h-11 rounded-2xl ${studioFieldSurfaceClasses}`}
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid min-w-0 gap-2">
                                            <Label htmlFor="category-parent-trigger">
                                                Create main or subcategory
                                            </Label>
                                            <div
                                                className="relative"
                                                ref={parentPickerRef}
                                            >
                                                <button
                                                    id="category-parent-trigger"
                                                    type="button"
                                                    onClick={() => {
                                                        setParentPickerOpen(
                                                            (current) => {
                                                                const next =
                                                                    !current;

                                                                if (next) {
                                                                    setParentPickerView(
                                                                        'choice',
                                                                    );
                                                                } else {
                                                                    setParentPickerView(
                                                                        'choice',
                                                                    );
                                                                    setParentQuery(
                                                                        '',
                                                                    );
                                                                }

                                                                return next;
                                                            },
                                                        );
                                                        setIconPickerOpen(
                                                            false,
                                                        );
                                                        setTypePickerOpen(
                                                            false,
                                                        );
                                                    }}
                                                    aria-haspopup="listbox"
                                                    aria-expanded={
                                                        parentPickerOpen
                                                    }
                                                    className={`flex h-11 w-full items-center justify-between rounded-2xl border px-3 text-left transition hover:border-brand/25 ${studioFieldSurfaceClasses}`}
                                                >
                                                    <div className="flex min-w-0 items-center gap-2.5">
                                                        {selectedParentOption ? (
                                                            <>
                                                                <CategoryIcon
                                                                    name={
                                                                        selectedParentOption.icon
                                                                    }
                                                                    variant="badge"
                                                                    presentation={resolveCategoryColorPresentation(
                                                                        selectedParentOption.color,
                                                                        colorMap,
                                                                    )}
                                                                    className="size-7 rounded-[14px]"
                                                                    iconSize={
                                                                        16
                                                                    }
                                                                />
                                                                <span className="truncate text-sm font-medium text-foreground">
                                                                    {
                                                                        parentTriggerLabel
                                                                    }
                                                                </span>
                                                            </>
                                                        ) : (
                                                            <span className="truncate text-sm font-medium text-foreground">
                                                                {
                                                                    parentTriggerLabel
                                                                }
                                                            </span>
                                                        )}
                                                    </div>
                                                    <ChevronDown className="size-4 text-muted-foreground" />
                                                </button>

                                                {parentPickerOpen ? (
                                                    <div
                                                        className={`absolute left-0 z-30 mt-2 w-full min-w-56 rounded-3xl p-2 ${studioPopoverSurfaceClasses}`}
                                                    >
                                                        <div className="grid gap-2">
                                                            {parentPickerView ===
                                                            'choice' ? (
                                                                <>
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => {
                                                                            setData(
                                                                                'parent_id',
                                                                                '',
                                                                            );
                                                                            setParentPickerOpen(
                                                                                false,
                                                                            );
                                                                            setParentPickerView(
                                                                                'choice',
                                                                            );
                                                                            setParentQuery(
                                                                                '',
                                                                            );
                                                                        }}
                                                                        className={`flex items-center gap-3 rounded-2xl border px-3 py-3 text-left transition ${
                                                                            data.parent_id ===
                                                                            ''
                                                                                ? 'border-brand/40 bg-brand/15 text-foreground'
                                                                                : studioIdleOptionClasses
                                                                        }`}
                                                                    >
                                                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-2xl border border-dashed border-brand/20 bg-brand/6 text-brand/80 dark:bg-black/15">
                                                                            <Layers3 className="size-4.5" />
                                                                        </div>
                                                                        <div className="min-w-0 flex-1">
                                                                            <p className="truncate text-sm font-semibold">
                                                                                Main
                                                                                category
                                                                            </p>
                                                                            <p className="truncate text-xs text-muted-foreground">
                                                                                Create
                                                                                this
                                                                                category
                                                                                at
                                                                                the
                                                                                top
                                                                                level.
                                                                            </p>
                                                                        </div>
                                                                    </button>

                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            setParentPickerView(
                                                                                'browse',
                                                                            )
                                                                        }
                                                                        className={`flex items-center gap-3 rounded-2xl border px-3 py-3 text-left transition ${
                                                                            selectedParentOption !==
                                                                            null
                                                                                ? 'border-brand/40 bg-brand/15 text-foreground'
                                                                                : studioIdleOptionClasses
                                                                        }`}
                                                                    >
                                                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-2xl border border-brand/15 bg-brand/6 text-brand dark:bg-black/15">
                                                                            {selectedParentOption ? (
                                                                                <CategoryIcon
                                                                                    name={
                                                                                        selectedParentOption.icon
                                                                                    }
                                                                                    variant="badge"
                                                                                    presentation={resolveCategoryColorPresentation(
                                                                                        selectedParentOption.color,
                                                                                        colorMap,
                                                                                    )}
                                                                                    className="size-9 rounded-2xl"
                                                                                    iconSize={
                                                                                        18
                                                                                    }
                                                                                />
                                                                            ) : (
                                                                                <ChevronRight className="size-4" />
                                                                            )}
                                                                        </div>
                                                                        <div className="min-w-0 flex-1">
                                                                            <p className="truncate text-sm font-semibold">
                                                                                {selectedParentOption
                                                                                    ? `Subcategory of ${selectedParentOption.name}`
                                                                                    : 'Subcategory of...'}
                                                                            </p>
                                                                            <p className="truncate text-xs text-muted-foreground">
                                                                                Choose
                                                                                where
                                                                                this
                                                                                subcategory
                                                                                should
                                                                                live.
                                                                            </p>
                                                                        </div>
                                                                    </button>
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <div
                                                                        className={`flex items-start gap-3 rounded-2xl p-3 ${studioSectionFrameClasses}`}
                                                                    >
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => {
                                                                                setParentPickerView(
                                                                                    'choice',
                                                                                );
                                                                                setParentQuery(
                                                                                    '',
                                                                                );
                                                                            }}
                                                                            className="flex size-9 shrink-0 items-center justify-center rounded-2xl border border-brand/15 bg-white/80 text-muted-foreground shadow-[inset_0_1px_0_rgba(255,255,255,0.7)] transition hover:border-brand/25 hover:text-foreground dark:bg-background/50 dark:shadow-none"
                                                                            aria-label="Back to category type selection"
                                                                        >
                                                                            <ArrowLeft className="size-4" />
                                                                        </button>

                                                                        <div className="min-w-0 flex-1">
                                                                            <p className="text-sm font-semibold text-foreground">
                                                                                Choose
                                                                                parent
                                                                                category
                                                                            </p>
                                                                            <p className="text-xs text-muted-foreground">
                                                                                You
                                                                                are
                                                                                choosing
                                                                                where
                                                                                this
                                                                                new
                                                                                subcategory
                                                                                will
                                                                                be
                                                                                placed
                                                                                in
                                                                                the
                                                                                tree.
                                                                            </p>
                                                                        </div>
                                                                    </div>

                                                                    <div className="relative">
                                                                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                                                        <Input
                                                                            value={
                                                                                parentQuery
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                setParentQuery(
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            placeholder="Search categories..."
                                                                            className="pl-9"
                                                                        />
                                                                    </div>

                                                                    <div className="grid max-h-72 gap-2 overflow-y-auto pr-1">
                                                                        {parentQuery.trim() !==
                                                                        '' ? (
                                                                            filteredParentResults.length ===
                                                                            0 ? (
                                                                                <div className="rounded-2xl border border-dashed border-border px-4 py-5 text-sm text-muted-foreground">
                                                                                    No
                                                                                    categories
                                                                                    matched
                                                                                    your
                                                                                    search.
                                                                                </div>
                                                                            ) : (
                                                                                filteredParentResults.map(
                                                                                    (
                                                                                        category,
                                                                                    ) => {
                                                                                        const isActive =
                                                                                            data.parent_id ===
                                                                                            String(
                                                                                                category.id,
                                                                                            );
                                                                                        const resultColor =
                                                                                            resolveCategoryColorPresentation(
                                                                                                category.color,
                                                                                                colorMap,
                                                                                            );

                                                                                        return (
                                                                                            <button
                                                                                                key={
                                                                                                    category.id
                                                                                                }
                                                                                                type="button"
                                                                                                onClick={() => {
                                                                                                    setData(
                                                                                                        'parent_id',
                                                                                                        String(
                                                                                                            category.id,
                                                                                                        ),
                                                                                                    );
                                                                                                    setParentPickerOpen(
                                                                                                        false,
                                                                                                    );
                                                                                                    setParentPickerView(
                                                                                                        'choice',
                                                                                                    );
                                                                                                    setParentQuery(
                                                                                                        '',
                                                                                                    );
                                                                                                }}
                                                                                                className={`flex items-center gap-3 rounded-2xl border px-3 py-3 text-left transition ${
                                                                                                    isActive
                                                                                                        ? 'border-brand/40 bg-brand/15 text-foreground'
                                                                                                        : studioIdleOptionClasses
                                                                                                }`}
                                                                                            >
                                                                                                <CategoryIcon
                                                                                                    name={
                                                                                                        category.icon
                                                                                                    }
                                                                                                    variant="badge"
                                                                                                    presentation={
                                                                                                        resultColor
                                                                                                    }
                                                                                                    className="size-9 rounded-2xl"
                                                                                                    iconSize={
                                                                                                        18
                                                                                                    }
                                                                                                />
                                                                                                <div className="min-w-0 flex-1">
                                                                                                    <p className="truncate text-sm font-semibold text-foreground">
                                                                                                        {
                                                                                                            category.name
                                                                                                        }
                                                                                                    </p>
                                                                                                    <p className="truncate text-xs text-muted-foreground">
                                                                                                        {
                                                                                                            category.path
                                                                                                        }
                                                                                                    </p>
                                                                                                </div>
                                                                                            </button>
                                                                                        );
                                                                                    },
                                                                                )
                                                                            )
                                                                        ) : (
                                                                            <ParentCategoryTree
                                                                                categories={
                                                                                    activeCategoryTree
                                                                                }
                                                                                colorMap={
                                                                                    colorMap
                                                                                }
                                                                                expandedIds={
                                                                                    expandedParentIds
                                                                                }
                                                                                selectedParentId={
                                                                                    data.parent_id
                                                                                }
                                                                                onSelect={
                                                                                    selectParentCategory
                                                                                }
                                                                                onToggle={
                                                                                    toggleParentCategory
                                                                                }
                                                                            />
                                                                        )}
                                                                    </div>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                ) : null}
                                            </div>
                                            <InputError
                                                message={errors.parent_id}
                                            />
                                        </div>

                                        <div className="grid gap-3 md:grid-cols-3 md:items-end">
                                            <div className="grid min-w-0 gap-2">
                                                <Label htmlFor="category-type-trigger">
                                                    Type
                                                </Label>
                                                <div
                                                    className="relative"
                                                    ref={typePickerRef}
                                                >
                                                    <button
                                                        id="category-type-trigger"
                                                        type="button"
                                                        onClick={() => {
                                                            setTypePickerOpen(
                                                                (current) =>
                                                                    !current,
                                                            );
                                                            setIconPickerOpen(
                                                                false,
                                                            );
                                                            setParentPickerOpen(
                                                                false,
                                                            );
                                                        }}
                                                        aria-haspopup="listbox"
                                                        aria-expanded={
                                                            typePickerOpen
                                                        }
                                                        className={`flex h-11 w-full items-center justify-between rounded-2xl border px-3 text-left transition hover:border-brand/25 ${studioFieldSurfaceClasses}`}
                                                    >
                                                        <div className="flex min-w-0 items-center gap-2.5">
                                                            <div
                                                                className={`flex size-7 shrink-0 items-center justify-center rounded-full border ${data.type === 'income' ? 'border-emerald-400/30 bg-emerald-500/15 text-emerald-300' : 'border-red-400/35 bg-red-500/18 text-red-300 shadow-[0_0_18px_rgba(239,68,68,0.18)]'}`}
                                                            >
                                                                {data.type ===
                                                                'income' ? (
                                                                    <ArrowUpCircle
                                                                        size={
                                                                            18
                                                                        }
                                                                    />
                                                                ) : (
                                                                    <ArrowDownCircle
                                                                        size={
                                                                            18
                                                                        }
                                                                    />
                                                                )}
                                                            </div>
                                                            <span className="truncate text-sm font-medium text-foreground">
                                                                {selectedTypeOption?.label ??
                                                                    'Select type'}
                                                            </span>
                                                        </div>
                                                        <ChevronDown className="size-4 text-muted-foreground" />
                                                    </button>

                                                    {typePickerOpen ? (
                                                        <div
                                                            className={`absolute inset-x-0 z-30 mt-2 w-full min-w-0 overflow-hidden rounded-3xl p-2 ${studioPopoverSurfaceClasses}`}
                                                        >
                                                            <div className="grid gap-2">
                                                                {categoryTypes.map(
                                                                    (
                                                                        typeOption,
                                                                    ) => {
                                                                        const isActive =
                                                                            data.type ===
                                                                            typeOption.value;
                                                                        const isIncome =
                                                                            typeOption.value ===
                                                                            'income';

                                                                        return (
                                                                            <button
                                                                                key={
                                                                                    typeOption.value
                                                                                }
                                                                                type="button"
                                                                                onClick={() => {
                                                                                    setData(
                                                                                        'type',
                                                                                        typeOption.value as FormState['type'],
                                                                                    );
                                                                                    setData(
                                                                                        'parent_id',
                                                                                        '',
                                                                                    );
                                                                                    setTypePickerOpen(
                                                                                        false,
                                                                                    );
                                                                                }}
                                                                                className={`flex w-full min-w-0 items-center gap-3 rounded-2xl border px-3 py-3 text-left transition ${
                                                                                    isActive
                                                                                        ? 'border-brand/40 bg-brand/15 text-foreground'
                                                                                        : studioIdleOptionClasses
                                                                                }`}
                                                                            >
                                                                                <div
                                                                                    className={`flex size-9 shrink-0 items-center justify-center rounded-2xl border ${isIncome ? 'border-emerald-200 bg-emerald-50 text-emerald-600 dark:border-emerald-400/30 dark:bg-emerald-500/15 dark:text-emerald-300' : 'border-red-200 bg-red-50 text-red-500 shadow-[0_10px_25px_rgba(239,68,68,0.08)] dark:border-red-400/35 dark:bg-red-500/18 dark:text-red-300 dark:shadow-[0_0_18px_rgba(239,68,68,0.18)]'}`}
                                                                                >
                                                                                    {isIncome ? (
                                                                                        <ArrowUpCircle className="size-4.5" />
                                                                                    ) : (
                                                                                        <ArrowDownCircle className="size-4.5" />
                                                                                    )}
                                                                                </div>
                                                                                <div className="min-w-0 flex-1">
                                                                                    <p className="truncate text-sm font-semibold">
                                                                                        {
                                                                                            typeOption.label
                                                                                        }
                                                                                    </p>
                                                                                    <p className="truncate text-xs text-muted-foreground">
                                                                                        {isIncome
                                                                                            ? 'Money coming in'
                                                                                            : 'Money going out'}
                                                                                    </p>
                                                                                </div>
                                                                            </button>
                                                                        );
                                                                    },
                                                                )}
                                                            </div>
                                                        </div>
                                                    ) : null}
                                                </div>
                                                <InputError
                                                    message={errors.type}
                                                />
                                            </div>

                                            <div className="grid min-w-0 gap-2">
                                                <Label>Icon</Label>
                                                <div
                                                    className="relative w-full"
                                                    ref={iconPickerRef}
                                                >
                                                    <div
                                                        className={`flex h-11 items-center gap-2 rounded-2xl border px-2 transition focus-within:border-brand/35 hover:border-brand/25 ${studioFieldSurfaceClasses}`}
                                                    >
                                                        <div
                                                            id="category-icon-trigger"
                                                            aria-hidden="true"
                                                            className="shrink-0 rounded-2xl"
                                                        >
                                                            <CategoryIcon
                                                                name={data.icon}
                                                                variant="badge"
                                                                presentation={
                                                                    selectedColor
                                                                }
                                                                className="size-8 rounded-[14px]"
                                                                iconSize={18}
                                                            />
                                                        </div>

                                                        <div className="relative min-w-0 flex-1">
                                                            <Search className="pointer-events-none absolute top-1/2 left-0 size-4 -translate-y-1/2 text-muted-foreground" />
                                                            <Input
                                                                ref={
                                                                    iconSearchInputRef
                                                                }
                                                                value={
                                                                    iconQuery
                                                                }
                                                                onFocus={() => {
                                                                    setIconPickerOpen(
                                                                        true,
                                                                    );
                                                                    setTypePickerOpen(
                                                                        false,
                                                                    );
                                                                    setParentPickerOpen(
                                                                        false,
                                                                    );
                                                                }}
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    setIconQuery(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    );
                                                                    setIconPickerOpen(
                                                                        true,
                                                                    );
                                                                }}
                                                                placeholder="Search icons"
                                                                className="h-9 border-0 bg-transparent px-0 pl-6 shadow-none focus-visible:ring-0"
                                                            />
                                                        </div>
                                                    </div>

                                                    {iconPickerOpen &&
                                                    iconQuery.trim() !== '' ? (
                                                        <div
                                                            className={`absolute left-0 z-30 mt-2 max-h-80 w-full overflow-y-auto rounded-3xl p-3 ${studioPopoverSurfaceClasses}`}
                                                        >
                                                            <div className="grid gap-2">
                                                                {filteredIconOptions.length ===
                                                                0 ? (
                                                                    <div className="rounded-2xl border border-dashed border-border px-4 py-5 text-sm text-muted-foreground">
                                                                        No icons
                                                                        matched
                                                                        your
                                                                        search.
                                                                    </div>
                                                                ) : (
                                                                    filteredIconOptions.map(
                                                                        (
                                                                            iconOption,
                                                                        ) => {
                                                                            const isActive =
                                                                                data.icon ===
                                                                                iconOption.value;

                                                                            return (
                                                                                <button
                                                                                    key={
                                                                                        iconOption.value
                                                                                    }
                                                                                    type="button"
                                                                                    onClick={() => {
                                                                                        setData(
                                                                                            'icon',
                                                                                            iconOption.value,
                                                                                        );
                                                                                        setIconQuery(
                                                                                            '',
                                                                                        );
                                                                                        setIconPickerOpen(
                                                                                            false,
                                                                                        );
                                                                                    }}
                                                                                    className={`flex items-center gap-3 rounded-2xl border px-3 py-3 text-left transition ${
                                                                                        isActive
                                                                                            ? 'border-brand/40 bg-brand/15 text-foreground'
                                                                                            : studioIdleOptionClasses
                                                                                    }`}
                                                                                >
                                                                                    <CategoryIcon
                                                                                        name={
                                                                                            iconOption.value
                                                                                        }
                                                                                        variant="badge"
                                                                                        presentation={
                                                                                            selectedColor
                                                                                        }
                                                                                        className="size-9 rounded-[18px]"
                                                                                        iconSize={
                                                                                            18
                                                                                        }
                                                                                    />
                                                                                    <div className="min-w-0 flex-1">
                                                                                        <p className="truncate text-sm font-semibold">
                                                                                            {
                                                                                                iconOption.label
                                                                                            }
                                                                                        </p>
                                                                                        <p className="truncate text-xs text-muted-foreground">
                                                                                            {
                                                                                                iconOption.value
                                                                                            }
                                                                                        </p>
                                                                                    </div>
                                                                                </button>
                                                                            );
                                                                        },
                                                                    )
                                                                )}
                                                            </div>
                                                        </div>
                                                    ) : null}
                                                </div>
                                                <InputError
                                                    message={errors.icon}
                                                />
                                            </div>

                                            <div className="grid min-w-0 gap-2">
                                                <Label>Color</Label>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setColorDialogOpen(true)
                                                    }
                                                    className={`flex h-11 w-full items-center gap-3 rounded-2xl border px-3 text-left transition hover:border-brand/25 ${studioFieldSurfaceClasses}`}
                                                >
                                                    <CategoryColorSwatch
                                                        presentation={
                                                            selectedColor
                                                        }
                                                        className="size-8 rounded-[14px]"
                                                    />
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate text-sm font-semibold text-foreground">
                                                            {isHexColor(
                                                                data.color,
                                                            )
                                                                ? normalizeHexColor(
                                                                      data.color,
                                                                  )
                                                                : selectedColor.label}
                                                        </p>
                                                        <p className="truncate text-xs text-muted-foreground">
                                                            Open picker
                                                        </p>
                                                    </div>
                                                    <Palette className="size-4 text-muted-foreground" />
                                                </button>
                                                <InputError
                                                    message={errors.color}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <Dialog
                                    open={colorDialogOpen}
                                    onOpenChange={setColorDialogOpen}
                                >
                                    <DialogContent className="max-h-[calc(100dvh-1rem)] w-[calc(100%-1rem)] overflow-hidden border-brand/25 bg-[linear-gradient(180deg,rgba(12,14,18,0.98),rgba(24,28,36,0.98))] p-0 text-foreground sm:max-h-[calc(100dvh-2rem)] sm:max-w-lg">
                                        <DialogHeader className="border-b border-white/8 px-6 pt-6 pb-4 text-left">
                                            <DialogTitle className="flex items-center gap-2 text-xl">
                                                <Palette className="size-5 text-brand" />
                                                Pick category color
                                            </DialogTitle>
                                            <DialogDescription>
                                                Choose a preset shade or dial in
                                                a custom color that fits your
                                                category system.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <div className="grid max-h-[calc(100dvh-8rem)] gap-4 overflow-y-auto px-4 pb-4 sm:max-h-[calc(100dvh-9rem)] sm:gap-6 sm:px-6 sm:pb-6">
                                            <div className="rounded-[28px] border border-white/8 bg-white/4 p-4">
                                                <div className="flex items-end justify-between gap-4">
                                                    <div>
                                                        <p className="text-xs tracking-[0.22em] text-muted-foreground uppercase">
                                                            Presets
                                                        </p>
                                                        <p className="mt-2 text-sm text-muted-foreground">
                                                            Pick from the
                                                            built-in palette, or
                                                            use a custom hex
                                                            below.
                                                        </p>
                                                    </div>
                                                    <div className="hidden items-center gap-3 rounded-full border border-white/8 bg-black/20 px-3 py-2 sm:flex">
                                                        <CategoryColorSwatch
                                                            presentation={
                                                                selectedColor
                                                            }
                                                            className="size-7 rounded-2xl border-white/15"
                                                        />
                                                        <div>
                                                            <p className="text-xs tracking-[0.16em] text-white/55 uppercase">
                                                                Selected
                                                            </p>
                                                            <p className="text-sm font-semibold text-foreground">
                                                                {isHexColor(
                                                                    data.color,
                                                                )
                                                                    ? normalizeHexColor(
                                                                          data.color,
                                                                      )
                                                                    : selectedColor.label}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="mt-5 grid grid-cols-5 gap-1.5">
                                                    {categoryColors.map(
                                                        (colorOption) => {
                                                            const isActive =
                                                                data.color ===
                                                                colorOption.value;
                                                            const colorPresentation =
                                                                resolveCategoryColorPresentation(
                                                                    colorOption.value,
                                                                    colorMap,
                                                                );

                                                            return (
                                                                <button
                                                                    key={
                                                                        colorOption.value
                                                                    }
                                                                    type="button"
                                                                    onClick={() =>
                                                                        setData(
                                                                            'color',
                                                                            colorOption.value,
                                                                        )
                                                                    }
                                                                    aria-label={`Choose ${colorOption.label}`}
                                                                    className={`flex aspect-square w-full items-center justify-center rounded-[14px] border p-0.5 transition ${
                                                                        isActive
                                                                            ? 'border-brand bg-brand/12 shadow-[0_0_0_1px_rgba(16,185,129,0.2)]'
                                                                            : 'border-white/8 bg-black/15 hover:border-brand/25'
                                                                    }`}
                                                                >
                                                                    <CategoryColorSwatch
                                                                        presentation={
                                                                            colorPresentation
                                                                        }
                                                                        className="size-full rounded-xl border-white/15"
                                                                    />
                                                                </button>
                                                            );
                                                        },
                                                    )}
                                                </div>
                                            </div>

                                            <div className="grid gap-3 rounded-[28px] border border-white/8 bg-white/4 p-4">
                                                <div className="flex items-center justify-between gap-3">
                                                    <div>
                                                        <p className="text-sm font-semibold text-foreground">
                                                            Custom color
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            Use the system color
                                                            wheel, then refine
                                                            the hex value if
                                                            needed.
                                                        </p>
                                                    </div>

                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className="rounded-full border-white/10 bg-black/20"
                                                        onClick={() =>
                                                            nativeColorInputRef.current?.click()
                                                        }
                                                    >
                                                        <Pipette className="size-4" />
                                                        Pick custom
                                                    </Button>
                                                </div>

                                                <input
                                                    ref={nativeColorInputRef}
                                                    type="color"
                                                    value={
                                                        isHexColor(data.color)
                                                            ? normalizeHexColor(
                                                                  data.color,
                                                              )
                                                            : '#10b981'
                                                    }
                                                    onChange={(event) =>
                                                        setData(
                                                            'color',
                                                            event.target.value,
                                                        )
                                                    }
                                                    className="sr-only"
                                                />

                                                <div className="grid gap-3 sm:grid-cols-[72px_minmax(0,1fr)] sm:items-center">
                                                    <CategoryColorSwatch
                                                        presentation={
                                                            selectedColor
                                                        }
                                                        className="size-18 rounded-3xl border-white/15"
                                                    />
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="category-color-hex">
                                                            Hex value
                                                        </Label>
                                                        <Input
                                                            id="category-color-hex"
                                                            value={
                                                                isHexColor(
                                                                    data.color,
                                                                )
                                                                    ? normalizeHexColor(
                                                                          data.color,
                                                                      )
                                                                    : data.color
                                                            }
                                                            onChange={(event) =>
                                                                setData(
                                                                    'color',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="#10b981"
                                                            spellCheck={false}
                                                            autoCapitalize="off"
                                                            autoCorrect="off"
                                                            className="h-11 rounded-2xl border-white/10 bg-black/20"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </DialogContent>
                                </Dialog>

                                <Dialog
                                    open={deleteCandidate !== null}
                                    onOpenChange={(open) => {
                                        if (
                                            !open &&
                                            deletingCategoryId === null
                                        ) {
                                            setDeleteCandidate(null);
                                        }
                                    }}
                                >
                                    <DialogContent className="border-red-500/20 bg-[linear-gradient(180deg,rgba(18,12,14,0.98),rgba(34,18,23,0.98))] text-foreground sm:max-w-md">
                                        <DialogHeader className="text-left">
                                            <DialogTitle>
                                                Delete{' '}
                                                {deleteCandidate?.name ??
                                                    'category'}
                                                ?
                                            </DialogTitle>
                                            <DialogDescription>
                                                {deleteCandidate?.children
                                                    .length
                                                    ? 'This will permanently remove the selected category and all of its subcategories.'
                                                    : 'This will permanently remove the selected category.'}
                                            </DialogDescription>
                                        </DialogHeader>

                                        <div className="rounded-2xl border border-red-400/15 bg-red-500/8 px-4 py-3 text-sm text-red-100/85">
                                            This action cannot be undone.
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={
                                                    deletingCategoryId !== null
                                                }
                                                onClick={() =>
                                                    setDeleteCandidate(null)
                                                }
                                                className="border-white/10 bg-black/20"
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                disabled={
                                                    deletingCategoryId !==
                                                        null ||
                                                    deleteCandidate === null
                                                }
                                                onClick={confirmDeleteCategory}
                                            >
                                                {deletingCategoryId !== null ? (
                                                    <>
                                                        <LoaderCircle className="size-4 animate-spin" />
                                                        Deleting...
                                                    </>
                                                ) : (
                                                    'Delete category'
                                                )}
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>

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

                        <div className="grid min-w-0 gap-6 xl:grid-cols-2">
                            <Card className="min-w-0 border-brand/20 bg-linear-to-br from-emerald-500/8 via-card to-card shadow-none">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <ArrowUpCircle className="size-5 text-emerald-400" />
                                        Income categories
                                    </CardTitle>
                                    <CardDescription>
                                        Organize salary, freelance,
                                        reimbursements, and any other money
                                        coming in.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="min-w-0">
                                    {incomeCategories.length === 0 ? (
                                        <div className="rounded-2xl border border-dashed border-brand/20 bg-black/10 p-4 text-sm text-muted-foreground">
                                            No income categories yet. Create
                                            your first one from the panel on the
                                            left.
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setExpandedIncomeIds(
                                                            new Set(
                                                                incomeBranchIds,
                                                            ),
                                                        )
                                                    }
                                                    className="h-8 rounded-full border border-brand/15 px-3 text-xs text-muted-foreground hover:border-brand/30 hover:bg-brand/10 hover:text-foreground"
                                                >
                                                    Expand all
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setExpandedIncomeIds(
                                                            new Set(),
                                                        )
                                                    }
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
                                                onDelete={setDeleteCandidate}
                                                deletingCategoryId={
                                                    deletingCategoryId
                                                }
                                            />
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <Card className="min-w-0 border-brand/20 bg-linear-to-br from-red-500/10 via-card to-card shadow-none">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <ArrowDownCircle className="size-5 text-red-300 drop-shadow-[0_0_10px_rgba(239,68,68,0.45)]" />
                                        Expense categories
                                    </CardTitle>
                                    <CardDescription>
                                        Build spending trees like Vehicles → Car
                                        → Petrol or Home → Repairs → Plumbing.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="min-w-0">
                                    {expenseCategories.length === 0 ? (
                                        <div className="rounded-2xl border border-dashed border-brand/20 bg-black/10 p-4 text-sm text-muted-foreground">
                                            No expense categories yet. Start by
                                            creating a main category, then layer
                                            subcategories under it.
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setExpandedExpenseIds(
                                                            new Set(
                                                                expenseBranchIds,
                                                            ),
                                                        )
                                                    }
                                                    className="h-8 rounded-full border border-brand/15 px-3 text-xs text-muted-foreground hover:border-brand/30 hover:bg-brand/10 hover:text-foreground"
                                                >
                                                    Expand all
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setExpandedExpenseIds(
                                                            new Set(),
                                                        )
                                                    }
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
                                                onDelete={setDeleteCandidate}
                                                deletingCategoryId={
                                                    deletingCategoryId
                                                }
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
