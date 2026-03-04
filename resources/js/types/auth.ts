export type User = {
    id: number;
    name: string;
    preferred_name?: string | null;
    display_name?: string;
    email: string;
    avatar?: string;
    has_password_set?: boolean;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
