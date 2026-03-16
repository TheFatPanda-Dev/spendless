import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            support_email?: string;
            auth: Auth;
            number_locale?: string;
            flash?: {
                success?: string;
                error?: string;
                oauthPrompt?: {
                    provider: string;
                    email: string;
                };
            };
            [key: string]: unknown;
        };
    }
}
