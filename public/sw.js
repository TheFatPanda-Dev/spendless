/*
 * SpendLess service worker.
 *
 * Only static files are cached: hashed build assets, fonts and brand images, plus
 * the offline page. Pages and Inertia responses always go to the network, so no
 * financial data is ever stored on the device; without a connection, a page
 * visit shows /offline.html instead.
 */

const VERSION = 'v1';
const SHELL_CACHE = `spendless-shell-${VERSION}`;
const ASSET_CACHE = `spendless-assets-${VERSION}`;
const ASSET_LIMIT = 80;

const SHELL_FILES = [
    '/offline.html',
    '/images/brand/lockup-on-dark.svg',
    '/fonts/roboto/Roboto-Light.ttf',
    '/fonts/roboto/Roboto-Regular.ttf',
];

const CACHEABLE_PREFIXES = ['/build/assets/', '/fonts/', '/images/brand/'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            .then((cache) => cache.addAll(SHELL_FILES))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key.startsWith('spendless-') &&
                                key !== SHELL_CACHE &&
                                key !== ASSET_CACHE,
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

async function trimAssetCache() {
    const cache = await caches.open(ASSET_CACHE);
    const keys = await cache.keys();

    for (const request of keys.slice(0, Math.max(keys.length - ASSET_LIMIT, 0))) {
        await cache.delete(request);
    }
}

async function cacheFirst(request) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok && response.type === 'basic') {
        const cache = await caches.open(ASSET_CACHE);
        await cache.put(request, response.clone());
        trimAssetCache();
    }

    return response;
}

async function networkOrOffline(request) {
    try {
        return await fetch(request);
    } catch {
        const offline = await caches.match('/offline.html');

        return offline ?? Response.error();
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkOrOffline(request));

        return;
    }

    if (CACHEABLE_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
        event.respondWith(cacheFirst(request));
    }
});
