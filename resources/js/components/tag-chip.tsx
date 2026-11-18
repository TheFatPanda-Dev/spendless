import { resolveCategoryColorPresentation } from '@/components/category-icon-utils';
import { cn } from '@/lib/utils';

export type TagOption = {
    id: number;
    name: string;
    color: string;
};

export type TagColorOption = {
    value: string;
    label: string;
    background: string;
    foreground: string;
};

type TagChipProps = {
    tag: TagOption;
    colorMap: Map<string, TagColorOption>;
    selected?: boolean;
    onClick?: () => void;
    disabled?: boolean;
    className?: string;
};

/**
 * A tag shown as a small pill with its color. Clickable chips toggle (aria-pressed).
 */
export function TagChip({
    tag,
    colorMap,
    selected = false,
    onClick,
    disabled = false,
    className,
}: TagChipProps) {
    const presentation = resolveCategoryColorPresentation(tag.color, colorMap);
    const baseClasses =
        'inline-flex max-w-full items-center gap-1.5 rounded-full border text-xs font-medium';

    if (onClick === undefined) {
        return (
            <span
                className={cn(
                    baseClasses,
                    'border-border/70 bg-background/60 px-2 py-0.5 text-foreground/80 dark:border-white/10 dark:bg-white/4 dark:text-slate-300',
                    className,
                )}
            >
                <span
                    aria-hidden
                    className="size-2 shrink-0 rounded-full"
                    style={{ backgroundColor: presentation.background }}
                />
                <span className="truncate">{tag.name}</span>
            </span>
        );
    }

    return (
        <button
            type="button"
            aria-pressed={selected}
            onClick={onClick}
            disabled={disabled}
            className={cn(
                baseClasses,
                'px-2.5 py-1 transition outline-none focus-visible:ring-2 focus-visible:ring-brand/40 disabled:opacity-60',
                selected
                    ? 'shadow-sm'
                    : 'border-border bg-background/70 text-muted-foreground hover:border-brand/40 hover:text-foreground dark:border-white/12 dark:bg-white/3',
                className,
            )}
            style={
                selected
                    ? {
                          backgroundColor: presentation.background,
                          borderColor: presentation.background,
                          color: presentation.foreground,
                      }
                    : undefined
            }
        >
            <span
                aria-hidden
                className="size-2 shrink-0 rounded-full"
                style={{
                    backgroundColor: selected
                        ? presentation.foreground
                        : presentation.background,
                }}
            />
            <span className="truncate">{tag.name}</span>
        </button>
    );
}
