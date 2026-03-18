import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type UsePlaidAccountLinkedRefreshOptions = {
    only?: string[];
    onLinked?: () => void;
    onRefreshFinished?: () => void;
};

export function usePlaidAccountLinkedRefresh(
    options: UsePlaidAccountLinkedRefreshOptions = {},
): { isRefreshing: boolean } {
    const isRefreshingRef = useRef(false);
    const lastRefreshAtRef = useRef(0);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const onlyKey = (options.only ?? []).join('|');

    useEffect(() => {
        const only = options.only;
        const onLinked = options.onLinked;
        const onRefreshFinished = options.onRefreshFinished;

        const onMessage = (event: MessageEvent): void => {
            if (
                event.origin !== window.location.origin ||
                !event.data ||
                event.data.type !== 'plaid-account-linked'
            ) {
                return;
            }

            onLinked?.();

            const now = Date.now();

            if (isRefreshingRef.current || now - lastRefreshAtRef.current < 1000) {
                return;
            }

            isRefreshingRef.current = true;
            lastRefreshAtRef.current = now;
            setIsRefreshing(true);

            window.setTimeout(() => {
                router.reload({
                    ...(only ? { only } : {}),
                    onFinish: () => {
                        isRefreshingRef.current = false;
                        setIsRefreshing(false);
                        onRefreshFinished?.();
                    },
                });
            }, 150);
        };

        window.addEventListener('message', onMessage);

        return () => {
            window.removeEventListener('message', onMessage);
        };
    }, [onlyKey, options.onLinked, options.onRefreshFinished, options.only]);

    return { isRefreshing };
}
