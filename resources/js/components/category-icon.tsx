import { Layers3 } from 'lucide-react';
import dynamicIconImports from 'lucide-react/dynamicIconImports';
import { useEffect, useState } from 'react';
import {
    categoryIconNames,
    type CategoryColorPresentation,
    isHexColor,
    normalizeHexColor,
} from '@/components/category-icon-utils';

type CategoryIconProps = {
    name: string;
    className?: string;
    iconClassName?: string;
    iconSize?: number;
    strokeWidth?: number;
    presentation?: CategoryColorPresentation;
    variant?: 'glyph' | 'badge';
};

type LucideIconComponent = typeof Layers3;

function joinClasses(...classes: Array<string | undefined | false>): string {
    return classes.filter(Boolean).join(' ');
}

function hasRoundedClass(value?: string): boolean {
    return value?.split(/\s+/).some((className) => /^rounded(?:-|$|\[)/.test(className)) ?? false;
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
    const [IconComponent, setIconComponent] = useState<LucideIconComponent | null>(null);

    useEffect(() => {
        if (!categoryIconNames.includes(name as (typeof categoryIconNames)[number])) {
            setIconComponent(null);

            return;
        }

        let active = true;

        dynamicIconImports[name as keyof typeof dynamicIconImports]()
            .then((iconModule) => {
                if (!active) {
                    return;
                }

                setIconComponent(() => iconModule.default as LucideIconComponent);
            })
            .catch(() => {
                if (active) {
                    setIconComponent(null);
                }
            });

        return () => {
            active = false;
        };
    }, [name]);

    const icon = categoryIconNames.includes(name as (typeof categoryIconNames)[number])
        && IconComponent !== null
        ? <IconComponent size={iconSize} strokeWidth={strokeWidth} className={iconClassName} />
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
