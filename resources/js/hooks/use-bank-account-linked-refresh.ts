import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { buildAjaxHeaders } from '@/lib/csrf';

type UseBankAccountLinkedRefreshOptions = {
    only?: string[];
    onLinked?: () => void;
    onRefreshFinished?: () => void;
};

const POLL_INTERVAL_MS = 2000;
const MAX_POLLS = 90;

/**
 * Listens for the "bank connected" message from the connect popup, reloads the page
 * data, then keeps checking until the first sync has finished and reloads once more.
 */
export function useBankAccountLinkedRefresh(
    options: UseBankAccountLinkedRefreshOptions = {},
): { isRefreshing: boolean } {
    const isRefreshingRef = useRef(false);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const optionsRef = useRef(options);

    useEffect(() => {
        optionsRef.current = options;
    });

    useEffect(() => {
        let pollTimer: number | null = null;
        let cancelled = false;

        const reload = (onFinish?: () => void): void => {
            const only = optionsRef.current.only;

            router.reload({
                ...(only ? { only } : {}),
                onFinish: () => onFinish?.(),
            });
        };

        const finish = (): void => {
            reload(() => {
                isRefreshingRef.current = false;
                setIsRefreshing(false);
                optionsRef.current.onRefreshFinished?.();
            });
        };

        const poll = async (attempt: number): Promise<void> => {
            if (cancelled) {
                return;
            }

            try {
                const response = await fetch('/wallets/sync-status', {
                    headers: buildAjaxHeaders(),
                });
                const payload = (await response.json()) as {
                    is_syncing?: boolean;
                };

                if (payload.is_syncing && attempt < MAX_POLLS) {
                    pollTimer = window.setTimeout(() => {
                        void poll(attempt + 1);
                    }, POLL_INTERVAL_MS);

                    return;
                }
            } catch {
                // Fall through and show whatever has synced so far.
            }

            finish();
        };

        const onMessage = (event: MessageEvent): void => {
            if (
                event.origin !== window.location.origin ||
                !event.data ||
                event.data.type !== 'bank-account-linked'
            ) {
                return;
            }

            if (event.data.success === false) {
                reload();

                return;
            }

            if (isRefreshingRef.current) {
                return;
            }

            isRefreshingRef.current = true;
            setIsRefreshing(true);
            optionsRef.current.onLinked?.();

            // Show the new (still syncing) account right away, then wait for its data.
            reload(() => {
                pollTimer = window.setTimeout(() => {
                    void poll(0);
                }, POLL_INTERVAL_MS);
            });
        };

        window.addEventListener('message', onMessage);

        return () => {
            cancelled = true;
            window.removeEventListener('message', onMessage);

            if (pollTimer !== null) {
                window.clearTimeout(pollTimer);
            }
        };
    }, []);

    return { isRefreshing };
}
