import { Layers3 } from 'lucide-react';
import { DynamicIcon, iconNames } from 'lucide-react/dynamic';

type CategoryColorLike = {
    background: string;
    foreground: string;
    label: string;
};

type CategoryColorPresentation = {
    background: string;
    foreground: string;
    label: string;
};

type CategoryIconProps = {
    name: string;
    className?: string;
    iconClassName?: string;
    iconSize?: number;
    strokeWidth?: number;
    presentation?: CategoryColorPresentation;
    variant?: 'glyph' | 'badge';
};

export const categoryIconNames = iconNames;

function joinClasses(...classes: Array<string | undefined | false>): string {
    return classes.filter(Boolean).join(' ');
}

function hasRoundedClass(value?: string): boolean {
    return value?.split(/\s+/).some((className) => /^rounded(?:-|$|\[)/.test(className)) ?? false;
}

export function isHexColor(value: string): boolean {
    return /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(value);
}

export function normalizeHexColor(value: string): string {
    if (!isHexColor(value)) {
        return '#10b981';
    }

    if (value.length === 4) {
        return `#${value[1]}${value[1]}${value[2]}${value[2]}${value[3]}${value[3]}`.toLowerCase();
    }

    return value.toLowerCase();
}

function getContrastColor(hexColor: string): string {
    const normalized = normalizeHexColor(hexColor);
    const red = Number.parseInt(normalized.slice(1, 3), 16);
    const green = Number.parseInt(normalized.slice(3, 5), 16);
    const blue = Number.parseInt(normalized.slice(5, 7), 16);
    const luminance = (0.299 * red + 0.587 * green + 0.114 * blue) / 255;

    return luminance > 0.62 ? 'rgb(15 23 42)' : 'rgb(248 250 252)';
}

export function resolveCategoryColorPresentation(
    value: string,
    colorMap: Map<string, CategoryColorLike>,
): CategoryColorPresentation {
    const preset = colorMap.get(value);

    if (preset) {
        return {
            background: preset.background,
            foreground: preset.foreground,
            label: preset.label,
        };
    }

    if (isHexColor(value)) {
        const normalized = normalizeHexColor(value);

        return {
            background: normalized,
            foreground: getContrastColor(normalized),
            label: normalized.toUpperCase(),
        };
    }

    return {
        background: 'rgb(71 85 105)',
        foreground: 'rgb(248 250 252)',
        label: 'Slate',
    };
}

function parseColorToRgb(value: string): { red: number; green: number; blue: number } | null {
    if (isHexColor(value)) {
        const normalized = normalizeHexColor(value);

        return {
            red: Number.parseInt(normalized.slice(1, 3), 16),
            green: Number.parseInt(normalized.slice(3, 5), 16),
            blue: Number.parseInt(normalized.slice(5, 7), 16),
        };
    }

    const match = value.match(/rgb\((\d+)\s+(\d+)\s+(\d+)\)/);

    if (!match) {
        return null;
    }

    return {
        red: Number.parseInt(match[1], 10),
        green: Number.parseInt(match[2], 10),
        blue: Number.parseInt(match[3], 10),
    };
}

function buildCategoryBadgeStyle(color: CategoryColorPresentation): {
    backgroundColor: string;
    borderColor: string;
    color: string;
    boxShadow: string;
} {
    const rgb = parseColorToRgb(color.background);

    if (!rgb) {
        return {
            backgroundColor: color.background,
            borderColor: 'rgba(255, 255, 255, 0.1)',
            color: color.foreground,
            boxShadow: 'none',
        };
    }

    return {
        backgroundColor: `rgba(${rgb.red}, ${rgb.green}, ${rgb.blue}, 0.18)`,
        borderColor: `rgba(${rgb.red}, ${rgb.green}, ${rgb.blue}, 0.32)`,
        color: `rgb(${rgb.red} ${rgb.green} ${rgb.blue})`,
        boxShadow: `inset 0 1px 0 rgba(255,255,255,0.04), 0 0 0 1px rgba(${rgb.red}, ${rgb.green}, ${rgb.blue}, 0.08)`,
    };
}

export function CategoryIcon({
    name,
    className,
    iconClassName,
    iconSize = 24,
    strokeWidth,
    presentation,
    variant = 'glyph',
}: CategoryIconProps) {
    const icon = categoryIconNames.includes(name as (typeof categoryIconNames)[number])
        ? (
            <DynamicIcon
                name={name as (typeof categoryIconNames)[number]}
                size={iconSize}
                strokeWidth={strokeWidth}
                className={iconClassName}
            />
        )
        : <Layers3 size={iconSize} strokeWidth={strokeWidth} className={iconClassName} />;

    if (variant !== 'badge') {
        return icon;
    }

    return (
        <div
            className={joinClasses(
                'flex shrink-0 items-center justify-center border',
                className ? undefined : 'size-10',
                hasRoundedClass(className) ? undefined : 'rounded-[18px]',
                className,
            )}
            style={presentation ? buildCategoryBadgeStyle(presentation) : undefined}
        >
            {icon}
        </div>
    );
}

export function CategoryColorSwatch({
    presentation,
    className,
}: {
    presentation: CategoryColorPresentation;
    className?: string;
}) {
    return (
        <div
            className={joinClasses(
                'flex shrink-0 items-center justify-center border',
                className ? undefined : 'size-10',
                hasRoundedClass(className) ? undefined : 'rounded-[18px]',
                className,
            )}
            style={{
                backgroundColor: presentation.background,
                borderColor: 'rgba(255, 255, 255, 0.1)',
                boxShadow: 'inset 0 1px 0 rgba(255,255,255,0.05)',
            }}
        />
    );
}
