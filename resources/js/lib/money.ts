/**
 * Money as the identity kit asks for it: locale-aware, a true minus sign (−) for
 * outflows, a plus sign for inflows when the sign carries meaning, cents always shown.
 */
export type SignDisplay = 'auto' | 'always' | 'never';

const formatters = new Map<string, Intl.NumberFormat>();

function formatter(locale: string, currency: string): Intl.NumberFormat {
    const key = `${locale}|${currency}`;
    let cached = formatters.get(key);

    if (cached === undefined) {
        try {
            cached = new Intl.NumberFormat(locale, {
                style: 'currency',
                currency,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        } catch {
            cached = new Intl.NumberFormat('en-GB', {
                style: 'currency',
                currency: 'EUR',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }

        formatters.set(key, cached);
    }

    return cached;
}

export function formatMoney(
    amount: number,
    currency: string,
    locale = 'en-GB',
    sign: SignDisplay = 'auto',
): string {
    const value = Number.isFinite(amount) ? amount : 0;
    const absolute = formatter(locale, currency || 'EUR').format(
        Math.abs(value),
    );

    if (sign === 'never' || value === 0) {
        return absolute;
    }

    if (value < 0) {
        return `−${absolute}`;
    }

    return sign === 'always' ? `+${absolute}` : absolute;
}

/** "Nov 2026" / "November 2026" for a YYYY-MM-DD date. */
export function formatMonth(
    date: string,
    locale = 'en-GB',
    style: 'short' | 'long' = 'short',
): string {
    const [year, month] = date.split('-').map(Number);

    if (!year || !month) {
        return date;
    }

    return new Intl.DateTimeFormat(locale, {
        month: style,
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, month - 1, 1)));
}

/** "19 Nov 2026" for a YYYY-MM-DD date. */
export function formatDay(
    date: string,
    locale = 'en-GB',
    withYear = true,
): string {
    const [year, month, day] = date.split('-').map(Number);

    if (!year || !month || !day) {
        return date;
    }

    return new Intl.DateTimeFormat(locale, {
        day: 'numeric',
        month: 'short',
        ...(withYear ? { year: 'numeric' } : {}),
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, month - 1, day)));
}

/** First and last day of the month containing a YYYY-MM-DD date. */
export function monthRange(date: string): { start: string; end: string } {
    const [year, month] = date.split('-').map(Number);
    const last = new Date(Date.UTC(year, month, 0)).getUTCDate();
    const prefix = `${year}-${String(month).padStart(2, '0')}`;

    return {
        start: `${prefix}-01`,
        end: `${prefix}-${String(last).padStart(2, '0')}`,
    };
}

/** The month before (-1) or after (+1) a YYYY-MM-DD date, as its first day. */
export function shiftMonthStart(date: string, direction: -1 | 1): string {
    const [year, month] = date.split('-').map(Number);
    const shifted = new Date(Date.UTC(year, month - 1 + direction, 1));

    return `${shifted.getUTCFullYear()}-${String(shifted.getUTCMonth() + 1).padStart(2, '0')}-01`;
}

/**
 * Colour for an amount: money out (negative) is rose everywhere; money in is green
 * only where the amount is shown signed ("+€20.00"), otherwise plain.
 */
export function amountTone(amount: number, signed = true): string {
    if (amount < 0) {
        return 'text-negative';
    }

    return signed && amount > 0 ? 'text-positive' : 'text-foreground';
}
