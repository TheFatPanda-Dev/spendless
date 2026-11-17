export type BankConnectionStatusValue =
    | 'pending'
    | 'connected'
    | 'syncing'
    | 'sync_failed'
    | 'needs_reauth';

export type BankConnectionStatus = {
    id: number;
    provider: 'enable_banking' | 'manual' | string;
    status: BankConnectionStatusValue | string;
    institution_name: string | null;
    institution_logo: string | null;
    last_synced_at: string | null;
    next_sync_at: string | null;
    consent_valid_until: string | null;
    consent_expires_soon: boolean;
    error_message: string | null;
};
