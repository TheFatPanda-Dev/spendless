import { iconNames } from 'lucide-react/dynamic';

type CategoryColorLike = {
    background: string;
    foreground: string;
    label: string;
};

export type CategoryColorPresentation = {
    background: string;
    foreground: string;
    label: string;
};

export const categoryIconNames = iconNames;

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
