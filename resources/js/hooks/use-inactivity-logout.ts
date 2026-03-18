import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { getCsrfData } from '@/lib/csrf';
import { login, logout } from '@/routes';

const AUTO_LOGOUT_TIMEOUT_MS = 15 * 60 * 1000;
const LAST_ACTIVITY_STORAGE_KEY = 'auth:last-activity-at';
const TAB_BOOTSTRAPPED_STORAGE_KEY = 'auth:inactivity-tab-bootstrapped';

function getStoredLastActivity(): number | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const rawValue = window.localStorage.getItem(LAST_ACTIVITY_STORAGE_KEY);

    if (rawValue === null) {
        return null;
    }

    const timestamp = Number.parseInt(rawValue, 10);

    return Number.isFinite(timestamp) ? timestamp : null;
}

function storeLastActivity(timestamp: number): void {
    window.localStorage.setItem(LAST_ACTIVITY_STORAGE_KEY, String(timestamp));
}

export function clearInactivityLogoutState(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.removeItem(LAST_ACTIVITY_STORAGE_KEY);
    window.sessionStorage.removeItem(TAB_BOOTSTRAPPED_STORAGE_KEY);
}

export function useInactivityLogout(
    timeoutMs: number = AUTO_LOGOUT_TIMEOUT_MS,
): void {
    const timeoutIdRef = useRef<number | null>(null);
    const hasLoggedOutRef = useRef(false);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        hasLoggedOutRef.current = false;

        const clearLogoutTimer = (): void => {
            if (timeoutIdRef.current !== null) {
                window.clearTimeout(timeoutIdRef.current);
                timeoutIdRef.current = null;
            }
        };

        const logoutNow = (): void => {
            if (hasLoggedOutRef.current) {
                return;
            }

            hasLoggedOutRef.current = true;
            clearLogoutTimer();
            clearInactivityLogoutState();
            router.flushAll();

            const redirectToLogin = (): void => {
                window.location.assign(login.url());
            };

            const { csrfToken, xsrfToken } = getCsrfData();

            void fetch(logout.url(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                },
            })
                .then((response) => {
                    if (response.status === 401 || response.status === 419) {
                        redirectToLogin();

                        return;
                    }

                    if (response.redirected) {
                        window.location.assign(response.url);

                        return;
                    }

                    if (response.ok) {
                        redirectToLogin();

                        return;
                    }

                    redirectToLogin();
                })
                .catch(() => {
                    redirectToLogin();
                });
        };

        const syncLoggedOutState = (): void => {
            if (hasLoggedOutRef.current) {
                return;
            }

            hasLoggedOutRef.current = true;
            clearLogoutTimer();
            router.flushAll();
            window.location.reload();
        };

        const scheduleLogout = (lastActivityAt: number): void => {
            clearLogoutTimer();

            const elapsed = Date.now() - lastActivityAt;

            if (elapsed >= timeoutMs) {
                logoutNow();

                return;
            }

            timeoutIdRef.current = window.setTimeout(
                logoutNow,
                timeoutMs - elapsed,
            );
        };

        const markActivity = (timestamp: number = Date.now()): void => {
            if (hasLoggedOutRef.current) {
                return;
            }

            storeLastActivity(timestamp);
            scheduleLogout(timestamp);
        };

        const syncFromStoredActivity = (): void => {
            const lastActivityAt = getStoredLastActivity();

            if (lastActivityAt === null) {
                markActivity();

                return;
            }

            scheduleLogout(lastActivityAt);
        };

        const handleActivity = (): void => {
            if (document.visibilityState === 'hidden') {
                return;
            }

            markActivity();
        };

        const handleVisibilityChange = (): void => {
            if (document.visibilityState !== 'visible') {
                return;
            }

            syncFromStoredActivity();
        };

        const handleStorage = (event: StorageEvent): void => {
            if (event.key !== LAST_ACTIVITY_STORAGE_KEY) {
                return;
            }

            if (event.newValue === null) {
                syncLoggedOutState();

                return;
            }

            syncFromStoredActivity();
        };

        const isTabBootstrapped =
            window.sessionStorage.getItem(TAB_BOOTSTRAPPED_STORAGE_KEY) ===
            'true';

        if (isTabBootstrapped) {
            syncFromStoredActivity();
        } else {
            markActivity();
            window.sessionStorage.setItem(TAB_BOOTSTRAPPED_STORAGE_KEY, 'true');
        }

        const activityEvents: Array<keyof WindowEventMap> = [
            'focus',
            'keydown',
            'pointerdown',
            'scroll',
            'touchstart',
        ];

        for (const eventName of activityEvents) {
            window.addEventListener(eventName, handleActivity);
        }

        window.addEventListener('storage', handleStorage);
        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            clearLogoutTimer();

            for (const eventName of activityEvents) {
                window.removeEventListener(eventName, handleActivity);
            }

            window.removeEventListener('storage', handleStorage);
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
        };
    }, [timeoutMs]);
}
