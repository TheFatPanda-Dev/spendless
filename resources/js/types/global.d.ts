import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            flash?: {
                success?: string;
            };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
