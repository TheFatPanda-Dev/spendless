/**
 * Registers /sw.js so SpendLess installs as an app and shows an offline page
 * without a connection. Production builds only: in development the worker would
 * cache Vite's assets and get in the way of hot reloading.
 */
export function registerServiceWorker(): void {
    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .catch(() => undefined);
    });
}
