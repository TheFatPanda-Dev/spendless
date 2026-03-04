import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            flash?: {
                success?: string;
                error?: string;
                oauthPrompt?: {
                    provider: string;
                    email: string;
                };
            };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
