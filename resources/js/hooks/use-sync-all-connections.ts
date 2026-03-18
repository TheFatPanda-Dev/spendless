import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { buildAjaxHeaders } from '@/lib/csrf';

type UseSyncAllConnectionsOptions = {
    only?: string[];
};

type SyncStatusResponse = {
    is_syncing: boolean;
    syncing_connections: number;
    connected_connections: number;
    total_connections: number;
};

export function useSyncAllConnections(options: UseSyncAllConnectionsOptions = {}) {
    const [isSyncing, setIsSyncing] = useState(false);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const timerRef = useRef<number | null>(null);
    const completedOnceRef = useRef(false);

    const stopPolling = useCallback((): void => {
        if (timerRef.current !== null) {
            window.clearTimeout(timerRef.current);
            timerRef.current = null;
        }
    }, []);

    const pollSyncStatus = useCallback(async (): Promise<void> => {
        try {
            const response = await fetch('/wallets/sync-status', {
                method: 'GET',
                headers: buildAjaxHeaders(),
            });

            if (!response.ok) {
                throw new Error('Unable to read sync status.');
            }

            const payload = (await response.json()) as SyncStatusResponse;

            if (payload.is_syncing) {
                setIsSyncing(true);
                timerRef.current = window.setTimeout(() => {
                    void pollSyncStatus();
                }, 2000);

                return;
            }

            setIsSyncing(false);

            if (completedOnceRef.current) {
                setSuccessMessage('Sync completed. Latest transactions were fetched.');
                router.reload({
                    ...(options.only ? { only: options.only } : {}),
                });
            }

            completedOnceRef.current = false;
            stopPolling();
        } catch {
            setIsSyncing(false);
            setErrorMessage('Could not check sync status.');
            completedOnceRef.current = false;
            stopPolling();
        }
    }, [options.only, stopPolling]);

    const startSyncAll = useCallback(async (): Promise<void> => {
        if (isSyncing) {
            return;
        }

        setErrorMessage(null);
        setSuccessMessage(null);

        try {
            const response = await fetch('/wallets/refresh-all', {
                method: 'POST',
                headers: buildAjaxHeaders({
                    'Content-Type': 'application/json',
                }),
                body: JSON.stringify({}),
            });

            if (!response.ok) {
                throw new Error('Unable to queue sync for all connections.');
            }

            completedOnceRef.current = true;
            setIsSyncing(true);
            stopPolling();
            timerRef.current = window.setTimeout(() => {
                void pollSyncStatus();
            }, 500);
        } catch {
            setIsSyncing(false);
            completedOnceRef.current = false;
            setErrorMessage('Could not start syncing all connections.');
        }
    }, [isSyncing, pollSyncStatus, stopPolling]);

    useEffect(() => {
        return () => {
            stopPolling();
        };
    }, [stopPolling]);

    return {
        isSyncing,
        successMessage,
        errorMessage,
        startSyncAll,
        clearSuccessMessage: () => setSuccessMessage(null),
        clearErrorMessage: () => setErrorMessage(null),
    };
}
