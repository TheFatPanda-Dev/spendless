export type ConsentChoice = 'accepted' | 'essential';

export type ConsentRecord = {
    choice: ConsentChoice;
    updatedAt: string;
    version: string;
};

export const CONSENT_COOKIE = 'eu_cookie_consent';
export const CONSENT_VERSION = 'v1';
export const CONSENT_MAX_AGE_SECONDS = 180 * 24 * 60 * 60;

const getCookieValue = (name: string): string | undefined => {
    if (typeof document === 'undefined') {
        return undefined;
    }

    const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const match = document.cookie.match(
        new RegExp(`(?:^|; )${escapedName}=([^;]*)`),
    );

    return match ? decodeURIComponent(match[1]) : undefined;
};

const buildCookieString = (value: string): string => {
    const secure = window.location.protocol === 'https:' ? ';Secure' : '';

    return `${CONSENT_COOKIE}=${encodeURIComponent(value)};path=/;max-age=${CONSENT_MAX_AGE_SECONDS};SameSite=Lax${secure}`;
};

export const readCookieConsent = (): ConsentRecord | null => {
    const rawCookie = getCookieValue(CONSENT_COOKIE);

    if (!rawCookie) {
        return null;
    }

    try {
        const parsed = JSON.parse(rawCookie) as Partial<ConsentRecord>;

        if (
            (parsed.choice !== 'accepted' && parsed.choice !== 'essential') ||
            parsed.version !== CONSENT_VERSION ||
            typeof parsed.updatedAt !== 'string'
        ) {
            return null;
        }

        return {
            choice: parsed.choice,
            updatedAt: parsed.updatedAt,
            version: parsed.version,
        };
    } catch {
        return null;
    }
};

export const writeCookieConsent = (choice: ConsentChoice): ConsentRecord => {
    if (typeof document === 'undefined') {
        return {
            choice,
            updatedAt: new Date().toISOString(),
            version: CONSENT_VERSION,
        };
    }

    const payload: ConsentRecord = {
        choice,
        updatedAt: new Date().toISOString(),
        version: CONSENT_VERSION,
    };

    document.cookie = buildCookieString(JSON.stringify(payload));

    return payload;
};

export const hasStoredCookieConsent = (): boolean => {
    return readCookieConsent() !== null;
};

export const hasAcceptedOptionalCookies = (): boolean => {
    const consent = readCookieConsent();

    return consent?.choice === 'accepted';
};
