import { Form, Head, router, usePage } from '@inertiajs/react';
import AlertCircle from 'lucide-react/dist/esm/icons/alert-circle.js';
import Github from 'lucide-react/dist/esm/icons/github.js';
import { useEffect, useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { register } from '@/routes';
import { redirect as githubRedirect } from '@/routes/github';
import { redirect as googleRedirect } from '@/routes/google';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canRegister: boolean;
};

type OauthPrompt = {
    provider: string;
    email: string;
};

type OauthPopupMessage = {
    source: 'spendless-oauth-popup';
    type: 'success' | 'prompt' | 'error';
    provider?: string;
    email?: string;
    redirect?: string;
    message?: string;
};

export default function Login({ status, canRegister }: Props) {
    const { flash } = usePage<{
        flash?: {
            error?: string;
            oauthPrompt?: OauthPrompt;
        };
    }>().props;

    const [oauthPrompt, setOauthPrompt] = useState<OauthPrompt | null>(
        flash?.oauthPrompt ?? null,
    );
    const [oauthError, setOauthError] = useState<string | undefined>(
        flash?.error,
    );
    const [isPromptOpen, setIsPromptOpen] = useState<boolean>(
        Boolean(flash?.oauthPrompt),
    );
    const oauthPromptCardRef = useRef<HTMLDivElement | null>(null);
    const googleButtonRef = useRef<HTMLButtonElement | null>(null);
    const githubButtonRef = useRef<HTMLButtonElement | null>(null);
    const closeTimerRef = useRef<number | null>(null);

    const promptProvider = oauthPrompt?.provider?.toLowerCase();
    const googleUrl = googleRedirect().url;
    const githubUrl = githubRedirect().url;

    const toPopupUrl = (url: string): string => {
        return `${url}${url.includes('?') ? '&' : '?'}popup=1`;
    };

    const openOauthPopup = (provider: 'google' | 'github'): void => {
        setOauthError(undefined);

        if (oauthPrompt && promptProvider && promptProvider !== provider) {
            closePrompt();
        }

        const targetUrl =
            provider === 'google'
                ? toPopupUrl(googleUrl)
                : toPopupUrl(githubUrl);
        const width = 520;
        const height = 700;
        const left = Math.max(
            0,
            Math.floor(window.screenX + (window.outerWidth - width) / 2),
        );
        const top = Math.max(
            0,
            Math.floor(window.screenY + (window.outerHeight - height) / 2),
        );

        const popup = window.open(
            targetUrl,
            `spendless-oauth-${provider}`,
            `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`,
        );

        if (!popup) {
            setOauthError(
                'Your browser blocked the sign-in popup. Please allow popups and try again.',
            );

            return;
        }

        popup.focus();
    };

    const closePrompt = (): void => {
        setIsPromptOpen(false);

        if (closeTimerRef.current !== null) {
            window.clearTimeout(closeTimerRef.current);
        }

        closeTimerRef.current = window.setTimeout(() => {
            setOauthPrompt(null);
        }, 300);
    };

    useEffect(() => {
        return () => {
            if (closeTimerRef.current !== null) {
                window.clearTimeout(closeTimerRef.current);
            }
        };
    }, []);

    useEffect(() => {
        const onMessage = (event: MessageEvent): void => {
            if (event.origin !== window.location.origin) {
                return;
            }

            const data = event.data as OauthPopupMessage;

            if (!data || data.source !== 'spendless-oauth-popup') {
                return;
            }

            if (data.type === 'success') {
                const redirectUrl =
                    data.redirect && data.redirect !== ''
                        ? data.redirect
                        : '/dashboard';
                router.visit(redirectUrl, {
                    replace: true,
                });

                return;
            }

            if (data.type === 'prompt') {
                if (data.provider && data.email) {
                    setOauthPrompt({
                        provider: data.provider,
                        email: data.email,
                    });
                    setIsPromptOpen(true);
                    setOauthError(undefined);
                }

                return;
            }

            if (data.type === 'error' && data.message) {
                closePrompt();
                setOauthError(data.message);
            }
        };

        window.addEventListener('message', onMessage);

        return () => {
            window.removeEventListener('message', onMessage);
        };
    }, []);

    useEffect(() => {
        if (!oauthPrompt || !isPromptOpen) {
            return;
        }

        const handleOutsideClick = (event: MouseEvent): void => {
            const card = oauthPromptCardRef.current;
            const googleButton = googleButtonRef.current;
            const githubButton = githubButtonRef.current;
            const target = event.target as Node;

            if (!card) {
                return;
            }

            if (
                googleButton?.contains(target) ||
                githubButton?.contains(target)
            ) {
                return;
            }

            if (!card.contains(target)) {
                closePrompt();
            }
        };

        document.addEventListener('mousedown', handleOutsideClick);

        return () => {
            document.removeEventListener('mousedown', handleOutsideClick);
        };
    }, [oauthPrompt, isPromptOpen]);

    return (
        <AuthLayout
            title="Log in to your account"
            description="Enter your email and password below to log in"
        >
            <Head title="Log in" />

            {oauthError ? (
                <AlertError
                    title="OAuth sign-in unavailable"
                    errors={[oauthError]}
                />
            ) : null}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-3">
                                <div className="space-y-2">
                                    <Button
                                        ref={googleButtonRef}
                                        type="button"
                                        variant="outline"
                                        className="w-full"
                                        onClick={() => openOauthPopup('google')}
                                    >
                                        <span className="inline-flex items-center">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                className="mr-2 size-4"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    fill="#EA4335"
                                                    d="M12 10.2v3.9h5.5c-.2 1.3-1.5 3.9-5.5 3.9-3.3 0-6-2.8-6-6.2s2.7-6.2 6-6.2c1.9 0 3.1.8 3.8 1.5l2.6-2.6C16.8 2.8 14.6 2 12 2 6.8 2 2.6 6.5 2.6 12s4.2 10 9.4 10c5.4 0 9-3.8 9-9.2 0-.6-.1-1.1-.2-1.6H12z"
                                                />
                                            </svg>
                                            Continue with Google
                                        </span>
                                    </Button>

                                    <div
                                        className={`grid overflow-hidden transition-all duration-300 ease-out ${promptProvider === 'google' && isPromptOpen ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'}`}
                                        aria-live="polite"
                                    >
                                        <div className="min-h-0">
                                            {oauthPrompt &&
                                            promptProvider === 'google' ? (
                                                <div
                                                    ref={oauthPromptCardRef}
                                                    className="space-y-3 rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-center text-foreground"
                                                >
                                                    <p className="flex items-center justify-center gap-2 text-sm font-semibold text-red-500 dark:text-red-400">
                                                        <AlertCircle
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        No account is linked to
                                                        this Google sign-in yet
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Continue as{' '}
                                                        <span className="font-semibold text-foreground">
                                                            {oauthPrompt.email}
                                                        </span>
                                                        ? We can create your
                                                        SpendLess account from
                                                        this Google profile and
                                                        then take you to profile
                                                        settings.
                                                    </p>
                                                    <div className="flex justify-center">
                                                        <Button
                                                            type="button"
                                                            className="w-full sm:w-auto"
                                                            onClick={() =>
                                                                router.post(
                                                                    '/auth/oauth/register',
                                                                )
                                                            }
                                                        >
                                                            Yes, create account
                                                        </Button>
                                                    </div>
                                                </div>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Button
                                        ref={githubButtonRef}
                                        type="button"
                                        variant="outline"
                                        className="w-full"
                                        onClick={() => openOauthPopup('github')}
                                    >
                                        <span className="inline-flex items-center">
                                            <Github
                                                className="mr-2 size-4"
                                                aria-hidden="true"
                                            />
                                            Continue with GitHub
                                        </span>
                                    </Button>

                                    <div
                                        className={`grid overflow-hidden transition-all duration-300 ease-out ${promptProvider === 'github' && isPromptOpen ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'}`}
                                        aria-live="polite"
                                    >
                                        <div className="min-h-0">
                                            {oauthPrompt &&
                                            promptProvider === 'github' ? (
                                                <div
                                                    ref={oauthPromptCardRef}
                                                    className="space-y-3 rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-center text-foreground"
                                                >
                                                    <p className="flex items-center justify-center gap-2 text-sm font-semibold text-red-500 dark:text-red-400">
                                                        <AlertCircle
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        No account is linked to
                                                        this GitHub sign-in yet
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Continue as{' '}
                                                        <span className="font-semibold text-foreground">
                                                            {oauthPrompt.email}
                                                        </span>
                                                        ? We can create your
                                                        SpendLess account from
                                                        this GitHub profile and
                                                        then take you to profile
                                                        settings.
                                                    </p>
                                                    <div className="flex justify-center">
                                                        <Button
                                                            type="button"
                                                            className="w-full sm:w-auto"
                                                            onClick={() =>
                                                                router.post(
                                                                    '/auth/oauth/register',
                                                                )
                                                            }
                                                        >
                                                            Yes, create account
                                                        </Button>
                                                    </div>
                                                </div>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="relative text-center text-xs text-muted-foreground uppercase">
                                <span className="bg-card px-2">
                                    or continue with
                                </span>
                                <div className="absolute top-1/2 -z-10 w-full border-t" />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="text"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={3}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Password</Label>
                                    <TextLink
                                        href={request()}
                                        className="ml-auto text-sm"
                                        tabIndex={7}
                                    >
                                        Forgot password?
                                    </TextLink>
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    tabIndex={4}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={5}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={6}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </Button>
                        </div>

                        {canRegister && (
                            <div className="text-center text-sm text-muted-foreground">
                                Don't have an account?{' '}
                                <TextLink href={register()} tabIndex={8}>
                                    Sign up
                                </TextLink>
                            </div>
                        )}
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-success">
                    {status}
                </div>
            )}
        </AuthLayout>
    );
}
