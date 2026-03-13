export function localizedSeparators(numberLocale: string): {
    decimal: string;
    group: string;
} {
    const formatter = new Intl.NumberFormat(numberLocale);
    const parts = formatter.formatToParts(12345.6);

    return {
        decimal:
            parts.find((part) => part.type === 'decimal')?.value ?? '.',
        group: parts.find((part) => part.type === 'group')?.value ?? ',',
    };
}

export function normalizeLocalizedNumber(
    value: string,
    numberLocale: string,
): string {
    const trimmedValue = value.trim();

    if (trimmedValue === '') {
        return '';
    }

    const { decimal, group } = localizedSeparators(numberLocale);
    const withoutWhitespace = trimmedValue.replace(/\s+/g, '');
    const withoutGroupSeparators = group
        ? withoutWhitespace.split(group).join('')
        : withoutWhitespace;

    if (decimal === '.') {
        return withoutGroupSeparators.replace(/,/g, '');
    }

    return withoutGroupSeparators.split(decimal).join('.');
}

export function formatLocalizedDecimal(
    value: string,
    numberLocale: string,
): string {
    const normalized = normalizeLocalizedNumber(value, numberLocale);

    if (normalized === '' || Number.isNaN(Number(normalized))) {
        return value;
    }

    return new Intl.NumberFormat(numberLocale, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(normalized));
}
