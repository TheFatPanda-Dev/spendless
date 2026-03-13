import type { InertiaLinkProps } from '@inertiajs/react';
import { toUrl } from '@/lib/utils';

export function parseDate(dateValue: string): Date | null {
    const [year, month, day] = dateValue.split('-').map(Number);

    if (
        !Number.isInteger(year) ||
        !Number.isInteger(month) ||
        !Number.isInteger(day)
    ) {
        return null;
    }

    const parsed = new Date(year, month - 1, day);

    if (
        parsed.getFullYear() !== year ||
        parsed.getMonth() !== month - 1 ||
        parsed.getDate() !== day
    ) {
        return null;
    }

    return parsed;
}

export function formatDateForFilter(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function formatLocalizedNumericDate(
    dateValue: string,
    locale: string,
): string {
    const parsed = parseDate(dateValue);

    if (!parsed) {
        return dateValue;
    }

    const formatter = new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });

    const orderedParts = formatter
        .formatToParts(parsed)
        .filter(
            (part) =>
                part.type === 'day'
                || part.type === 'month'
                || part.type === 'year',
        )
        .map((part) => part.value);

    if (orderedParts.length === 3) {
        return orderedParts.join('/');
    }

    return formatter.format(parsed);
}

export function formatLocalizedNumericDateFromDate(
    value: Date,
    locale: string,
): string {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');

    return formatLocalizedNumericDate(`${year}-${month}-${day}`, locale);
}

export function formatLocalizedDateTime(
    value: string | null,
    locale: string,
): string {
    if (!value) {
        return 'Never';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleString(locale, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatDisplayDate(
    dateValue: string,
    locale = 'en-GB',
): string {
    return formatLocalizedNumericDate(dateValue, locale);
}

export function shiftMonth(dateValue: string, direction: -1 | 1): string {
    const parsed = parseDate(dateValue);

    if (!parsed) {
        return dateValue;
    }

    const originalDay = parsed.getDate();
    const targetYear = parsed.getFullYear();
    const targetMonth = parsed.getMonth() + direction;
    const lastDayOfTargetMonth = new Date(
        targetYear,
        targetMonth + 1,
        0,
    ).getDate();

    const shifted = new Date(
        targetYear,
        targetMonth,
        Math.min(originalDay, lastDayOfTargetMonth),
    );

    return formatDateForFilter(shifted);
}

export function shiftMonthRange(
    startDateValue: string,
    direction: -1 | 1,
): {
    startDate: string;
    endDate: string;
} {
    const parsedStartDate = parseDate(startDateValue);

    if (!parsedStartDate) {
        return {
            startDate: startDateValue,
            endDate: startDateValue,
        };
    }

    const targetYear = parsedStartDate.getFullYear();
    const targetMonth = parsedStartDate.getMonth() + direction;

    return {
        startDate: formatDateForFilter(new Date(targetYear, targetMonth, 1)),
        endDate: formatDateForFilter(new Date(targetYear, targetMonth + 1, 0)),
    };
}

export function getCurrentMonthFilterRange(referenceDate: Date = new Date()): {
    startDate: string;
    endDate: string;
} {
    const year = referenceDate.getFullYear();
    const month = referenceDate.getMonth();

    return {
        startDate: formatDateForFilter(new Date(year, month, 1)),
        endDate: formatDateForFilter(new Date(year, month + 1, 0)),
    };
}

export function buildDashboardHref(
    baseHref: NonNullable<InertiaLinkProps['href']>,
): string {
    const { startDate, endDate } = getCurrentMonthFilterRange();
    const query = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
    });

    return `${toUrl(baseHref)}?${query.toString()}`;
}
