import Check from 'lucide-react/dist/esm/icons/check.js';
import ChevronDown from 'lucide-react/dist/esm/icons/chevron-down.js';
import Search from 'lucide-react/dist/esm/icons/search.js';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { IconTile } from '@/components/kit';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type PickerCategory = {
    id: number;
    name: string;
    path?: string;
    icon: string;
    tint?: string | null;
    depth?: number;
};

export type PickerGroup = {
    key: string;
    /** Heading above the group; omit for a single unlabelled list. */
    label?: string;
    options: PickerCategory[];
};

/**
 * Searchable category list with each category's round tinted icon. Type to filter
 * (matches the full path, "Food & Drink › Work Lunch"), click or Enter to pick.
 */
export function CategoryOptionList({
    groups,
    selectedId,
    onSelect,
    clearLabel,
    autoFocus = false,
    className,
}: {
    groups: PickerGroup[];
    selectedId: number | null;
    onSelect: (option: PickerCategory | null) => void;
    /** Adds a first row that clears the choice ("All categories"). */
    clearLabel?: string;
    autoFocus?: boolean;
    className?: string;
}) {
    const [query, setQuery] = useState('');
    const needle = query.trim().toLowerCase();
    const filtered = useMemo(
        () =>
            groups.map((group) => ({
                ...group,
                options:
                    needle === ''
                        ? group.options
                        : group.options.filter((option) =>
                              (option.path ?? option.name)
                                  .toLowerCase()
                                  .includes(needle),
                          ),
            })),
        [groups, needle],
    );
    const first = filtered.find((group) => group.options.length > 0)
        ?.options[0];

    const row = (
        key: string,
        selected: boolean,
        onClick: () => void,
        content: ReactNode,
        depth = 0,
    ) => (
        <button
            key={key}
            type="button"
            onClick={onClick}
            aria-pressed={selected}
            className={cn(
                'flex min-h-11 w-full items-center gap-3 rounded-lg pr-2.5 text-left text-[15px]',
                selected
                    ? 'bg-accent text-accent-foreground'
                    : 'text-foreground hover:bg-muted',
            )}
            style={{ paddingLeft: `${0.5 + depth * 1.25}rem` }}
        >
            {content}
            {selected ? (
                <Check className="size-4 shrink-0" strokeWidth={2} />
            ) : null}
        </button>
    );

    return (
        <div className={cn('flex min-h-0 flex-col', className)}>
            <div className="flex items-center gap-2 border-b border-border px-3">
                <Search
                    className="size-4 shrink-0 text-muted-foreground"
                    strokeWidth={1.75}
                />
                <input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter' && first) {
                            event.preventDefault();
                            onSelect(first);
                        }
                    }}
                    placeholder="Search categories"
                    aria-label="Search categories"
                    autoFocus={autoFocus}
                    className="h-11 w-full bg-transparent text-[15px] outline-none placeholder:text-muted-foreground"
                />
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-1.5">
                {clearLabel && needle === ''
                    ? row(
                          'clear',
                          selectedId === null,
                          () => onSelect(null),
                          <span className="flex-1 truncate py-1 text-muted-foreground">
                              {clearLabel}
                          </span>,
                      )
                    : null}
                {filtered.map((group) =>
                    group.options.length === 0 ? null : (
                        <div key={group.key} className="mb-1">
                            {group.label ? (
                                <p className="px-2.5 pt-2 pb-1 text-xs font-medium tracking-[0.12em] text-muted-foreground uppercase">
                                    {group.label}
                                </p>
                            ) : null}
                            {group.options.map((option) =>
                                row(
                                    `${group.key}-${option.id}`,
                                    option.id === selectedId,
                                    () => onSelect(option),
                                    <>
                                        <IconTile
                                            icon={option.icon}
                                            tint={option.tint}
                                            size="sm"
                                        />
                                        <span className="flex-1 truncate">
                                            {needle === ''
                                                ? option.name
                                                : (
                                                      option.path ?? option.name
                                                  ).replaceAll(' / ', ' › ')}
                                        </span>
                                    </>,
                                    needle === '' ? (option.depth ?? 0) : 0,
                                ),
                            )}
                        </div>
                    ),
                )}
                {filtered.every((group) => group.options.length === 0) ? (
                    <p className="px-2.5 py-3 text-[15px] text-muted-foreground">
                        No category matches “{query}”.
                    </p>
                ) : null}
            </div>
        </div>
    );
}

/**
 * A form field or filter that picks a category from a searchable dropdown with
 * icons (a native select can't show them).
 */
export function CategoryPicker({
    groups,
    value,
    onChange,
    placeholder = 'Choose a category',
    clearLabel,
    id,
    size = 'md',
    inDialog = false,
    className,
}: {
    groups: PickerGroup[];
    value: number | null;
    onChange: (id: number | null) => void;
    placeholder?: string;
    clearLabel?: string;
    id?: string;
    size?: 'md' | 'lg';
    /** Inside a sheet or dialog: keep the dropdown within it so it can take input. */
    inDialog?: boolean;
    className?: string;
}) {
    const [open, setOpen] = useState(false);
    const selected = useMemo(
        () =>
            groups
                .flatMap((group) => group.options)
                .find((option) => option.id === value) ?? null,
        [groups, value],
    );

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    id={id}
                    type="button"
                    aria-haspopup="listbox"
                    aria-expanded={open}
                    className={cn(
                        'inline-flex w-full items-center gap-2.5 rounded-lg border border-control/70 bg-card pr-3 pl-2 text-left text-foreground transition-colors hover:bg-muted/60',
                        size === 'lg' ? 'h-12 text-base' : 'h-11 text-[15px]',
                        className,
                    )}
                >
                    {selected ? (
                        <IconTile
                            icon={selected.icon}
                            tint={selected.tint}
                            size="sm"
                        />
                    ) : null}
                    <span
                        className={cn(
                            'min-w-0 flex-1 truncate',
                            selected ? undefined : 'pl-1.5',
                            selected || clearLabel
                                ? undefined
                                : 'text-muted-foreground',
                        )}
                    >
                        {selected?.name ?? clearLabel ?? placeholder}
                    </span>
                    <ChevronDown
                        className="size-4 shrink-0 text-muted-foreground"
                        strokeWidth={1.75}
                    />
                </button>
            </PopoverTrigger>
            <PopoverContent
                portal={!inDialog}
                className="flex max-h-[min(26rem,var(--radix-popover-content-available-height))] w-[max(var(--radix-popover-trigger-width),18rem)] flex-col p-0"
            >
                <CategoryOptionList
                    groups={groups}
                    selectedId={value}
                    clearLabel={clearLabel}
                    autoFocus
                    onSelect={(option) => {
                        onChange(option?.id ?? null);
                        setOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}
