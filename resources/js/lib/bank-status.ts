export function bankStatusLabel(status: string): string {
    switch (status) {
        case 'connected':
            return 'Connected';
        case 'syncing':
            return 'Syncing';
        case 'sync_failed':
            return 'Sync failed';
        case 'needs_reauth':
            return 'Reconnect needed';
        case 'pending':
            return 'Waiting for bank';
        default:
            return status;
    }
}

export function bankStatusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'connected') {
        return 'default';
    }

    if (status === 'syncing') {
        return 'secondary';
    }

    if (status === 'needs_reauth' || status === 'sync_failed') {
        return 'destructive';
    }

    return 'outline';
}
