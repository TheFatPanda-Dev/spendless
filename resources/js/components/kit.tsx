import { router, usePage } from '@inertiajs/react';
import CalendarIcon from 'lucide-react/dist/esm/icons/calendar.js';
import ChevronDown from 'lucide-react/dist/esm/icons/chevron-down.js';
import ChevronLeft from 'lucide-react/dist/esm/icons/chevron-left.js';
import ChevronRight from 'lucide-react/dist/esm/icons/chevron-right.js';
import type { ReactNode } from 'react';
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
        <div className="mb-6 flex flex-wrap items-end justify-between gap-x-6 gap-y-4 desk:mb-8">
            <div className="min-w-0">
                <h1 className="text-screen-title font-normal tracking-[-0.01em] text-foreground tablet:text-page-title">
                    {title}
                </h1>
                {description ? (
                    <p className="mt-2 max-w-[65ch] text-[15px] text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex flex-wrap items-center gap-3">
                    {actions}
                </div>
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

const controlClasses =
    'inline-flex h-11 items-center gap-2 rounded-lg border border-control/70 bg-card px-3.5 text-[15px] text-foreground transition-colors hover:bg-muted';

/**
 * Month-by-month browsing: step with the arrows or jump from the list. Keeps any
 * other query parameters (filters) in place.
 */
export function MonthPicker({
    month,
    query = {},
}: {
    month: { start: string; end: string };
    query?: Record<string, string | number | null | undefined>;
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
                className={cn(controlClasses, 'w-11 justify-center px-0')}
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
                            'min-w-[11rem] justify-between',
                        )}
                    >
                        <span className="flex items-center gap-2.5">
                            <CalendarIcon
                                className="size-5 text-muted-foreground"
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
                className={cn(controlClasses, 'w-11 justify-center px-0')}
                onClick={() => go(shiftMonthStart(month.start, 1))}
                aria-label="Next month"
            >
                <ChevronRight className="size-5" strokeWidth={1.75} />
            </button>
        </div>
    );
}

/** Icon tile for a transaction row: neutral for spending, mint for income. */
export function IconTile({
    icon,
    income = false,
    className,
}: {
    icon: string;
    income?: boolean;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'flex size-10 shrink-0 items-center justify-center rounded-xl border',
                income
                    ? 'border-transparent bg-hero text-positive'
                    : 'border-border bg-card text-muted-foreground',
                className,
            )}
            aria-hidden
        >
            <CategoryIcon name={icon} iconSize={20} strokeWidth={1.75} />
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
}: {
    percent: number;
    size?: number;
    label?: string;
}) {
    const stroke = size * 0.075;
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const sweep = 0.75; // an open ring: three quarters of a circle
    const track = circumference * sweep;
    const value = (track * Math.min(Math.max(percent, 0), 100)) / 100;

    return (
        <div
            className="relative shrink-0"
            style={{ width: size, height: size }}
            role="img"
            aria-label={`${percent}% ${label}`}
        >
            <svg
                width={size}
                height={size}
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
                <span className="text-[1.875rem] leading-none font-normal amount">
                    {percent}%
                </span>
                <span className="mt-1 text-sm text-hero-muted">{label}</span>
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
