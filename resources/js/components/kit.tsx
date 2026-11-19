import { router, usePage } from '@inertiajs/react';
import CalendarIcon from 'lucide-react/dist/esm/icons/calendar.js';
import ChevronDown from 'lucide-react/dist/esm/icons/chevron-down.js';
import ChevronLeft from 'lucide-react/dist/esm/icons/chevron-left.js';
import ChevronRight from 'lucide-react/dist/esm/icons/chevron-right.js';
import Plus from 'lucide-react/dist/esm/icons/plus.js';
import type { CSSProperties, ReactNode } from 'react';
import { CategoryIcon } from '@/components/category-icon';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatMonth, monthRange, shiftMonthStart } from '@/lib/money';
import { cn } from '@/lib/utils';

/**
 * Building blocks from the SpendLess identity kit, shared by every signed-in screen.
 */

export function useLocale(): string {
    const page = usePage<{ number_locale?: string }>();

    return page.props.number_locale ?? 'en-GB';
}

/** Page title with the screen's one primary action and its controls. */
export function PageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <div className="mb-6 desk:mb-8">
            <div className="flex items-center justify-between gap-x-4 gap-y-4 tablet:flex-wrap tablet:gap-x-6">
                <h1 className="min-w-0 text-screen-title font-normal tracking-[-0.01em] text-foreground tablet:text-page-title">
                    {title}
                </h1>
                {actions ? (
                    <div className="flex shrink-0 flex-wrap items-center justify-end gap-3">
                        {actions}
                    </div>
                ) : null}
            </div>
            {description ? (
                <p className="mt-2 max-w-[65ch] text-[15px] text-muted-foreground">
                    {description}
                </p>
            ) : null}
        </div>
    );
}

/** A white content card with the kit's 20px radius. */
export function Panel({
    title,
    action,
    children,
    className,
}: {
    title?: ReactNode;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'rounded-card border border-border bg-card p-5 text-card-foreground tablet:p-6',
                className,
            )}
        >
            {title || action ? (
                <div className="mb-5 flex items-center justify-between gap-4">
                    {title ? (
                        <h2 className="text-section font-medium text-foreground">
                            {title}
                        </h2>
                    ) : (
                        <span />
                    )}
                    {action}
                </div>
            ) : null}
            {children}
        </section>
    );
}

/** Rough Roboto Light advance widths, in em, for fitting a figure to its box. */
function figureEms(text: string): number {
    let ems = 0;

    for (const char of text) {
        if (/[0-9]/.test(char)) {
            ems += 0.565;
        } else if (/[.,'\s]/.test(char)) {
            ems += 0.25;
        } else {
            ems += 0.64;
        }
    }

    return Math.max(ems * 1.05, 1);
}

/**
 * The hero figure ("Left to spend"): the kit's 3.5rem Light on phones and 4rem from
 * tablet up, shrinking only as far as needed so long amounts never clip. Its parent
 * must be a size container (`@container`).
 */
export function HeroFigure({
    value,
    className,
}: {
    value: string;
    className?: string;
}) {
    return (
        <p
            className={cn(
                'mt-1 leading-none font-light tracking-[-0.02em] whitespace-nowrap amount',
                'text-[length:min(3.5rem,calc(100cqi/var(--figure-ems)))] tablet:text-[length:min(4rem,calc(100cqi/var(--figure-ems)))]',
                className,
            )}
            style={
                { '--figure-ems': figureEms(value).toFixed(2) } as CSSProperties
            }
        >
            {value}
        </p>
    );
}

/** The phone top bar's 44px "+" (kit phone screens). */
export function PhoneAddButton({
    label,
    onClick,
}: {
    label: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            className="inline-flex size-11 items-center justify-center rounded-[14px] bg-primary text-primary-foreground transition-[transform,background-color] hover:bg-primary/90 active:scale-[0.96]"
        >
            <Plus className="size-6" strokeWidth={1.75} />
        </button>
    );
}

const controlClasses =
    'inline-flex h-11 items-center gap-2 rounded-lg border border-control/70 bg-card px-3.5 text-[15px] text-foreground transition-colors hover:bg-muted';

/**
 * Month-by-month browsing: step with the arrows or jump from the list. Keeps any
 * other query parameters (filters) in place.
 */
export function MonthPicker({
    month,
    query = {},
    quietOnPhone = false,
}: {
    month: { start: string; end: string };
    query?: Record<string, string | number | null | undefined>;
    /** Page-header use: plain "Nov 2026 ⌄" text beside the title on phones. */
    quietOnPhone?: boolean;
}) {
    const locale = useLocale();
    const today = new Date();
    const current = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-01`;
    const months = Array.from({ length: 24 }, (_, index) => {
        let date = current;

        for (let step = 0; step < index; step++) {
            date = shiftMonthStart(date, -1);
        }

        return date;
    });

    const go = (start: string): void => {
        const range = monthRange(start);
        const cleaned = Object.fromEntries(
            Object.entries(query).filter(
                ([, value]) =>
                    value !== null && value !== undefined && value !== '',
            ),
        );

        router.get(
            window.location.pathname,
            { ...cleaned, start_date: range.start, end_date: range.end },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <div className="flex items-center gap-1.5">
            <button
                type="button"
                className={cn(
                    controlClasses,
                    'hidden w-11 justify-center px-0 tablet:inline-flex',
                )}
                onClick={() => go(shiftMonthStart(month.start, -1))}
                aria-label="Previous month"
            >
                <ChevronLeft className="size-5" strokeWidth={1.75} />
            </button>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        className={cn(
                            controlClasses,
                            'min-w-[10.5rem] justify-between tablet:min-w-[11rem]',
                            quietOnPhone &&
                                'max-tablet:min-w-0 max-tablet:border-transparent max-tablet:bg-transparent max-tablet:px-2 max-tablet:text-lg max-tablet:text-muted-foreground',
                        )}
                    >
                        <span className="flex items-center gap-2.5">
                            <CalendarIcon
                                className={cn(
                                    'size-5 text-muted-foreground',
                                    quietOnPhone && 'max-tablet:hidden',
                                )}
                                strokeWidth={1.75}
                            />
                            {formatMonth(month.start, locale)}
                        </span>
                        <ChevronDown
                            className="size-4 text-muted-foreground"
                            strokeWidth={1.75}
                        />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    className="max-h-80 min-w-[11rem] overflow-y-auto rounded-xl"
                >
                    {months.map((start) => (
                        <DropdownMenuItem
                            key={start}
                            onSelect={() => go(start)}
                            className={cn(
                                'min-h-10 rounded-lg text-[15px]',
                                start === month.start.slice(0, 8) + '01'
                                    ? 'bg-accent font-medium text-accent-foreground'
                                    : '',
                            )}
                        >
                            {formatMonth(start, locale, 'long')}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
            <button
                type="button"
                className={cn(
                    controlClasses,
                    'hidden w-11 justify-center px-0 tablet:inline-flex',
                )}
                onClick={() => go(shiftMonthStart(month.start, 1))}
                aria-label="Next month"
            >
                <ChevronRight className="size-5" strokeWidth={1.75} />
            </button>
        </div>
    );
}

/**
 * A category's round icon tile, tinted with the category's own colour (picked on the
 * Categories page). Without a colour it falls back to neutral, or mint for income.
 */
export function IconTile({
    icon,
    tint,
    income = false,
    size = 'md',
    className,
}: {
    icon: string;
    tint?: string | null;
    income?: boolean;
    size?: 'md' | 'sm';
    className?: string;
}) {
    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full border',
                size === 'sm' ? 'size-8' : 'size-10',
                tint
                    ? 'border-[color-mix(in_srgb,var(--tint)_30%,transparent)] bg-[color-mix(in_srgb,var(--tint)_14%,var(--card))] text-[color-mix(in_srgb,var(--tint)_70%,black)] dark:border-[color-mix(in_srgb,var(--tint)_34%,transparent)] dark:bg-[color-mix(in_srgb,var(--tint)_18%,transparent)] dark:text-[var(--tint)]'
                    : income
                      ? 'border-transparent bg-hero text-positive'
                      : 'border-border bg-card text-muted-foreground',
                className,
            )}
            style={tint ? ({ '--tint': tint } as CSSProperties) : undefined}
            aria-hidden
        >
            <CategoryIcon
                name={icon}
                iconSize={size === 'sm' ? 16 : 20}
                strokeWidth={1.75}
            />
        </span>
    );
}

/**
 * The kit's open ring for one part-to-whole measure. Over 100% the arc caps at full
 * and the text says the real percentage.
 */
export function BudgetRing({
    percent,
    size = 148,
    label = 'used',
    className,
}: {
    percent: number;
    size?: number;
    label?: string;
    /** Sizes the ring responsively (e.g. "size-28 tablet:size-[132px]"); the drawing scales. */
    className?: string;
}) {
    const stroke = size * 0.075;
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const sweep = 0.75; // an open ring: three quarters of a circle
    const track = circumference * sweep;
    const value = (track * Math.min(Math.max(percent, 0), 100)) / 100;

    return (
        <div
            className={cn('@container relative shrink-0', className)}
            style={className ? undefined : { width: size, height: size }}
            role="img"
            aria-label={`${percent}% ${label}`}
        >
            <svg
                width="100%"
                height="100%"
                viewBox={`0 0 ${size} ${size}`}
                className="-rotate-90"
                aria-hidden
            >
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="var(--track)"
                    strokeWidth={stroke}
                    strokeLinecap="round"
                    strokeDasharray={`${track} ${circumference}`}
                />
                {value > 0 ? (
                    <circle
                        cx={size / 2}
                        cy={size / 2}
                        r={radius}
                        fill="none"
                        stroke={
                            percent > 100 ? 'var(--destructive)' : 'var(--plot)'
                        }
                        strokeWidth={stroke}
                        strokeLinecap="round"
                        strokeDasharray={`${value} ${circumference}`}
                        className="transition-[stroke-dasharray] duration-500 ease-out-quart"
                    />
                ) : null}
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center text-hero-foreground">
                <span className="text-[length:clamp(1.25rem,23cqi,1.875rem)] leading-none font-normal amount">
                    {percent}%
                </span>
                <span className="mt-1 text-[length:clamp(0.75rem,10.5cqi,0.875rem)] text-hero-muted">
                    {label}
                </span>
            </div>
        </div>
    );
}

/** Ranked category series (guide page 10): one shared linear scale. */
export const SERIES = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

export function seriesColor(index: number, isOther = false): string {
    return isOther
        ? 'var(--chart-5)'
        : SERIES[Math.min(index, SERIES.length - 1)];
}

/** A category's own colour, or the ranked series colour when it has none. */
export function categoryColor(
    tint: string | null | undefined,
    index: number,
    isOther = false,
): string {
    return !isOther && tint ? tint : seriesColor(index, isOther);
}

export function StackedBar({
    parts,
}: {
    parts: Array<{ key: string; amount: number; color: string; label: string }>;
}) {
    const total = parts.reduce(
        (sum, part) => sum + Math.max(part.amount, 0),
        0,
    );

    if (total <= 0) {
        return <div className="h-6 rounded-md bg-muted" aria-hidden />;
    }

    return (
        <div
            className="flex h-6 gap-[3px]"
            role="img"
            aria-label={parts.map((part) => part.label).join(', ')}
        >
            {parts
                .filter((part) => part.amount > 0)
                .map((part) => (
                    <span
                        key={part.key}
                        className="h-full rounded-[4px] first:rounded-l-md last:rounded-r-md"
                        style={{
                            width: `${(part.amount / total) * 100}%`,
                            minWidth: 6,
                            backgroundColor: part.color,
                        }}
                    />
                ))}
        </div>
    );
}

/** Small status line under a figure: where it came from and how fresh it is. */
export function Muted({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <p className={cn('text-sm text-muted-foreground', className)}>
            {children}
        </p>
    );
}
