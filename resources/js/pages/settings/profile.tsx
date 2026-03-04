import { Transition } from '@headlessui/react';
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, EyeOff, ShieldBan, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    DISABLE_2FA_PASSWORD_DESCRIPTION,
    default as SecureAccountVerification,
} from '@/components/secure-account-verification';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Hint } from '@/components/ui/hint';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/hooks/use-initials';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { edit } from '@/routes/profile';
import { redirect as githubSettingsRedirect } from '@/routes/settings/github';
import { redirect as googleSettingsRedirect } from '@/routes/settings/google';
import { disable, enable } from '@/routes/two-factor';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit(),
    },
];

export default function Profile({
    mustVerifyEmail,
    oauth,
    password,
    twoFactor,
    pendingEmail,
    preferredName,
    status,
}: {
    mustVerifyEmail: boolean;
    oauth: {
        googleLinked: boolean;
        githubLinked: boolean;
    };
    password: {
        hasPasswordSet: boolean;
    };
    twoFactor: {
        enabled: boolean;
        requiresConfirmation: boolean;
    };
    pendingEmail?: string | null;
    preferredName?: string | null;
    status?: string;
}) {
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const displayName = auth.user.display_name ?? auth.user.name;
    const [isChangingEmail, setIsChangingEmail] = useState(false);
    const [isChangingPassword, setIsChangingPassword] = useState(false);
    const [isPasswordVisible, setIsPasswordVisible] = useState(false);
    const [newPasswordValue, setNewPasswordValue] = useState('');
    const [confirmPasswordValue, setConfirmPasswordValue] = useState('');
    const [oauthState, setOauthState] = useState(oauth);
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const [requireTwoFactorPasswordStep, setRequireTwoFactorPasswordStep] =
        useState<boolean>(false);
    const [isDisableTwoFactorModalOpen, setIsDisableTwoFactorModalOpen] =
        useState(false);
    const [disableTwoFactorPassword, setDisableTwoFactorPassword] =
        useState('');
    const [disableTwoFactorPasswordError, setDisableTwoFactorPasswordError] =
        useState<string | undefined>(undefined);
    const [isDisablingTwoFactor, setIsDisablingTwoFactor] = useState(false);
    const {
        qrCodeSvg,
        manualSetupKey,
        clearSetupData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors: twoFactorErrors,
    } = useTwoFactorAuth();
    const canUnlinkGoogle = password.hasPasswordSet || oauthState.githubLinked;
    const canUnlinkGithub = password.hasPasswordSet || oauthState.googleLinked;
    const canEnableTwoFactor = password.hasPasswordSet;
    const passwordsMatch =
        newPasswordValue !== '' &&
        confirmPasswordValue !== '' &&
        newPasswordValue === confirmPasswordValue;
    const passwordsMismatch =
        newPasswordValue !== '' &&
        confirmPasswordValue !== '' &&
        newPasswordValue !== confirmPasswordValue;
    const passwordMatchBorderClass = passwordsMatch
        ? 'border-2 border-success focus-visible:ring-success/40'
        : passwordsMismatch
          ? 'border-2 border-destructive focus-visible:ring-destructive/40'
          : '';
    const passwordChecks = useMemo(
        () => [
            {
                label: 'At least 8 characters',
                met: newPasswordValue.length >= 8,
            },
            {
                label: 'At least one uppercase letter',
                met: /[A-Z]/.test(newPasswordValue),
            },
            {
                label: 'At least one lowercase letter',
                met: /[a-z]/.test(newPasswordValue),
            },
            {
                label: 'At least one number',
                met: /\d/.test(newPasswordValue),
            },
            {
                label: 'At least one special character',
                met: /[^A-Za-z0-9]/.test(newPasswordValue),
            },
        ],
        [newPasswordValue],
    );

    const getCookieValue = (name: string): string | undefined => {
        const match = document.cookie.match(
            new RegExp(
                `(?:^|; )${name.replace(/[-.$?*|{}()[\]\\/+^]/g, '\\$&')}=([^;]*)`,
            ),
        );

        return match ? decodeURIComponent(match[1]) : undefined;
    };

    const getCsrfData = (): { csrfToken?: string; xsrfToken?: string } => {
        const csrfToken =
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? undefined;
        const xsrfToken = getCookieValue('XSRF-TOKEN');

        return { csrfToken, xsrfToken };
    };

    const confirmPasswordForSensitiveAction = async (
        passwordToConfirm: string,
    ): Promise<string | null> => {
        const { csrfToken, xsrfToken } = getCsrfData();
        const payload = new URLSearchParams();

        payload.set('password', passwordToConfirm);

        if (csrfToken) {
            payload.set('_token', csrfToken);
        }

        const response = await fetch('/user/confirm-password', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type':
                    'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
            },
            body: payload.toString(),
        });

        if (response.ok) {
            return null;
        }

        try {
            const payload = (await response.json()) as {
                message?: string;
                errors?: { password?: string[] };
            };

            return (
                payload.errors?.password?.[0] ??
                payload.message ??
                'Password confirmation failed.'
            );
        } catch {
            return 'Password confirmation failed.';
        }
    };

    const handleDisableTwoFactor = async (
        event: React.FormEvent<HTMLFormElement>,
    ): Promise<void> => {
        event.preventDefault();

        if (isDisablingTwoFactor) {
            return;
        }

        setDisableTwoFactorPasswordError(undefined);
        setIsDisablingTwoFactor(true);

        const confirmationError = await confirmPasswordForSensitiveAction(
            disableTwoFactorPassword,
        );

        if (confirmationError) {
            setDisableTwoFactorPasswordError(confirmationError);
            setIsDisablingTwoFactor(false);

            return;
        }

        const { csrfToken, xsrfToken } = getCsrfData();
        const payload = new URLSearchParams();

        payload.set('_method', 'DELETE');

        if (csrfToken) {
            payload.set('_token', csrfToken);
        }

        try {
            const response = await fetch(disable.url(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type':
                        'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                },
                body: payload.toString(),
            });

            if (!response.ok) {
                setDisableTwoFactorPasswordError(
                    'Unable to disable two-factor authentication. Please try again.',
                );

                return;
            }

            setIsDisableTwoFactorModalOpen(false);
            setDisableTwoFactorPassword('');
            setDisableTwoFactorPasswordError(undefined);
            router.reload({ only: ['twoFactor'] });
        } finally {
            setIsDisablingTwoFactor(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <SettingsLayout>
                <div
                    id="oauth-authentication"
                    className="space-y-6 rounded-xl border border-brand/20 bg-brand/5 p-4 sm:p-5"
                >
                    <Heading
                        variant="small"
                        title="Profile information"
                        description="Update your name and email address"
                    />

                    <Form
                        action={ProfileController.update.url()}
                        method="post"
                        options={{
                            preserveScroll: true,
                        }}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="_method"
                                    value="patch"
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>

                                    <Input
                                        id="name"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.name}
                                        name="name"
                                        required
                                        autoComplete="name"
                                        placeholder="Full name"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="preferred_name">
                                        What should we call you?
                                    </Label>

                                    <Input
                                        id="preferred_name"
                                        className="mt-1 block w-full"
                                        defaultValue={preferredName ?? ''}
                                        name="preferred_name"
                                        autoComplete="given-name"
                                        placeholder="Optional display name"
                                    />

                                    <p className="text-xs text-muted-foreground">
                                        If set, this name is shown in the app.
                                        Otherwise, we use your full name.
                                    </p>

                                    <InputError
                                        className="mt-1"
                                        message={errors.preferred_name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        className="mt-1 block w-full"
                                        value={auth.user.email}
                                        readOnly
                                        disabled
                                        autoComplete="email"
                                        placeholder="Email address"
                                    />

                                    <button
                                        type="button"
                                        onClick={() =>
                                            setIsChangingEmail(
                                                (value) => !value,
                                            )
                                        }
                                        className="w-fit text-sm font-medium text-foreground underline underline-offset-4"
                                    >
                                        Change email address
                                    </button>

                                    {isChangingEmail ? (
                                        <div className="grid gap-2 rounded-lg border border-brand/20 bg-background/70 p-3">
                                            <Label htmlFor="new_email">
                                                New email address
                                            </Label>
                                            <Input
                                                id="new_email"
                                                type="email"
                                                name="new_email"
                                                autoComplete="email"
                                                placeholder="new-email@example.com"
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Your current email is updated
                                                only after you confirm the link
                                                we send to the new address.
                                            </p>
                                            <InputError
                                                className="mt-1"
                                                message={errors.new_email}
                                            />
                                        </div>
                                    ) : null}

                                    {pendingEmail ? (
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Pending confirmation: {pendingEmail}
                                        </p>
                                    ) : null}

                                    <InputError
                                        className="mt-2"
                                        message={errors.email}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="avatar">Avatar</Label>

                                    <div className="flex items-center gap-4">
                                        <Avatar className="size-12 border border-brand/30">
                                            <AvatarImage
                                                src={auth.user.avatar}
                                                alt={displayName}
                                            />
                                            <AvatarFallback className="bg-brand/10 text-foreground">
                                                {getInitials(displayName)}
                                            </AvatarFallback>
                                        </Avatar>

                                        <Input
                                            id="avatar"
                                            type="file"
                                            name="avatar"
                                            accept="image/png,image/jpeg,image/jpg,image/webp"
                                            className="block w-full"
                                        />
                                    </div>

                                    <p className="text-xs text-muted-foreground">
                                        Upload JPG, PNG, or WEBP up to 2MB.
                                    </p>

                                    <InputError
                                        className="mt-1"
                                        message={errors.avatar}
                                    />
                                </div>

                                {mustVerifyEmail &&
                                    auth.user.email_verified_at === null && (
                                        <div>
                                            <p className="-mt-4 text-sm text-muted-foreground">
                                                Your email address is
                                                unverified.{' '}
                                                <Link
                                                    href={send()}
                                                    as="button"
                                                    className="text-foreground underline decoration-border underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                                                >
                                                    Click here to resend the
                                                    verification email.
                                                </Link>
                                            </p>

                                            {status ===
                                                'verification-link-sent' && (
                                                <div className="mt-2 text-sm font-medium text-success">
                                                    A new verification link has
                                                    been sent to your email
                                                    address.
                                                </div>
                                            )}
                                        </div>
                                    )}

                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        data-test="update-profile-button"
                                    >
                                        Save
                                    </Button>

                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-muted-foreground">
                                            Saved
                                        </p>
                                    </Transition>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                <div className="space-y-6 rounded-xl border border-brand/20 bg-brand/5 p-4 sm:p-5">
                    <Heading
                        variant="small"
                        title="Password"
                        description="Manage your password used for email sign-in"
                    />

                    {password.hasPasswordSet ? (
                        <p className="text-xs text-muted-foreground">
                            Your account password is set.
                        </p>
                    ) : (
                        <p className="text-xs text-muted-foreground">
                            Password not set yet. You signed up with OAuth. Set
                            a password to enable email and password sign-in.
                        </p>
                    )}

                    <button
                        type="button"
                        onClick={() => setIsChangingPassword((value) => !value)}
                        className="w-fit text-sm font-medium text-foreground underline underline-offset-4"
                    >
                        {password.hasPasswordSet
                            ? 'Change password'
                            : 'Set password'}
                    </button>

                    {isChangingPassword ? (
                        <Form
                            {...PasswordController.update.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            resetOnError={[
                                'password',
                                'password_confirmation',
                                'current_password',
                            ]}
                            resetOnSuccess
                            onSuccess={() => {
                                setNewPasswordValue('');
                                setConfirmPasswordValue('');
                                setIsPasswordVisible(false);
                                setIsChangingPassword(false);
                            }}
                            className="space-y-4"
                        >
                            {({ errors, processing, recentlySuccessful }) => (
                                <>
                                    {password.hasPasswordSet ? (
                                        <div className="grid gap-2 rounded-lg border border-brand/20 bg-background/70 p-3">
                                            <Label htmlFor="current_password">
                                                Current password
                                            </Label>
                                            <Input
                                                id="current_password"
                                                name="current_password"
                                                type="password"
                                                autoComplete="current-password"
                                                placeholder="Current password"
                                            />
                                            <InputError
                                                message={
                                                    errors.current_password
                                                }
                                            />
                                        </div>
                                    ) : null}

                                    <div className="grid gap-2 rounded-lg border border-brand/20 bg-background/70 p-3">
                                        <Label htmlFor="password">
                                            New password
                                        </Label>
                                        <div className="relative">
                                            <Input
                                                id="password"
                                                name="password"
                                                type={
                                                    isPasswordVisible
                                                        ? 'text'
                                                        : 'password'
                                                }
                                                autoComplete="new-password"
                                                placeholder="New password"
                                                value={newPasswordValue}
                                                onChange={(event) =>
                                                    setNewPasswordValue(
                                                        event.target.value,
                                                    )
                                                }
                                                className={cn(
                                                    'pr-10',
                                                    passwordMatchBorderClass,
                                                )}
                                            />
                                            <button
                                                type="button"
                                                tabIndex={-1}
                                                onClick={() =>
                                                    setIsPasswordVisible(
                                                        (visible) => !visible,
                                                    )
                                                }
                                                className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground transition hover:text-foreground"
                                                aria-label={
                                                    isPasswordVisible
                                                        ? 'Hide password'
                                                        : 'Show password'
                                                }
                                            >
                                                {isPasswordVisible ? (
                                                    <EyeOff
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                ) : (
                                                    <Eye
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                            </button>
                                        </div>
                                        <ul className="list-disc space-y-1 pl-5 text-xs">
                                            {passwordChecks.map((check) => (
                                                <li
                                                    key={check.label}
                                                    className={
                                                        check.met
                                                            ? 'text-success'
                                                            : 'text-destructive'
                                                    }
                                                >
                                                    {check.label}
                                                </li>
                                            ))}
                                        </ul>
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2 rounded-lg border border-brand/20 bg-background/70 p-3">
                                        <Label htmlFor="password_confirmation">
                                            Confirm password
                                        </Label>
                                        <div className="relative">
                                            <Input
                                                id="password_confirmation"
                                                name="password_confirmation"
                                                type={
                                                    isPasswordVisible
                                                        ? 'text'
                                                        : 'password'
                                                }
                                                autoComplete="new-password"
                                                placeholder="Confirm password"
                                                value={confirmPasswordValue}
                                                onChange={(event) =>
                                                    setConfirmPasswordValue(
                                                        event.target.value,
                                                    )
                                                }
                                                className={cn(
                                                    'pr-10',
                                                    passwordMatchBorderClass,
                                                )}
                                            />
                                            <button
                                                type="button"
                                                tabIndex={-1}
                                                onClick={() =>
                                                    setIsPasswordVisible(
                                                        (visible) => !visible,
                                                    )
                                                }
                                                className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground transition hover:text-foreground"
                                                aria-label={
                                                    isPasswordVisible
                                                        ? 'Hide password'
                                                        : 'Show password'
                                                }
                                            >
                                                {isPasswordVisible ? (
                                                    <EyeOff
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                ) : (
                                                    <Eye
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                            </button>
                                        </div>
                                        <InputError
                                            message={
                                                typeof errors.password_confirmation ===
                                                    'string' &&
                                                /match/i.test(
                                                    errors.password_confirmation,
                                                )
                                                    ? undefined
                                                    : errors.password_confirmation
                                            }
                                        />
                                    </div>

                                    <div className="flex items-center gap-4">
                                        <Button disabled={processing}>
                                            Save password
                                        </Button>

                                        <Transition
                                            show={recentlySuccessful}
                                            enter="transition ease-in-out"
                                            enterFrom="opacity-0"
                                            leave="transition ease-in-out"
                                            leaveTo="opacity-0"
                                        >
                                            <p className="text-sm text-muted-foreground">
                                                Saved
                                            </p>
                                        </Transition>
                                    </div>
                                </>
                            )}
                        </Form>
                    ) : null}
                </div>

                <div className="space-y-6 rounded-xl border border-brand/20 bg-brand/5 p-4 sm:p-5">
                    <Heading
                        variant="small"
                        title="OAuth Authentication"
                        description="Connect Google or GitHub to sign in faster and keep your account linked"
                    />

                    <div className="space-y-3">
                        <div className="flex flex-col gap-3 rounded-lg border border-brand/20 bg-background/70 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm font-medium text-foreground">
                                    Google
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Use your Google account to sign in.
                                </p>
                            </div>

                            <div className="flex items-center gap-3">
                                <span
                                    className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                                        oauthState.googleLinked
                                            ? 'bg-brand/15 text-brand'
                                            : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {oauthState.googleLinked
                                        ? 'Connected'
                                        : 'Not connected'}
                                </span>

                                {oauthState.googleLinked ? (
                                    canUnlinkGoogle ? (
                                        <Button
                                            variant="outline"
                                            type="button"
                                            onClick={() =>
                                                router.delete(
                                                    '/settings/oauth/google',
                                                    {
                                                        preserveScroll: true,
                                                        preserveState: true,
                                                        onSuccess: (page) => {
                                                            const flash = page
                                                                .props.flash as
                                                                | {
                                                                      error?: string;
                                                                  }
                                                                | undefined;

                                                            if (!flash?.error) {
                                                                setOauthState(
                                                                    (
                                                                        current,
                                                                    ) => ({
                                                                        ...current,
                                                                        googleLinked: false,
                                                                    }),
                                                                );
                                                            }
                                                        },
                                                    },
                                                )
                                            }
                                        >
                                            Unlink
                                        </Button>
                                    ) : (
                                        <Button variant="outline" disabled>
                                            Unlink
                                        </Button>
                                    )
                                ) : (
                                    <Button
                                        type="button"
                                        onClick={() =>
                                            window.location.assign(
                                                googleSettingsRedirect().url,
                                            )
                                        }
                                    >
                                        Link Google
                                    </Button>
                                )}
                            </div>
                        </div>

                        {oauthState.googleLinked && !canUnlinkGoogle ? (
                            <p className="-mt-1 inline-block w-fit rounded-md border border-yellow-300/40 bg-yellow-100/40 px-2.5 py-2 text-xs text-yellow-800 dark:border-yellow-500/30 dark:bg-yellow-500/6 dark:text-yellow-300">
                                Password not set. Set a password before
                                disconnecting Google. It is currently your only
                                sign-in method.
                            </p>
                        ) : null}

                        <div className="flex flex-col gap-3 rounded-lg border border-brand/20 bg-background/70 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm font-medium text-foreground">
                                    GitHub
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Use your GitHub account to sign in.
                                </p>
                            </div>

                            <div className="flex items-center gap-3">
                                <span
                                    className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                                        oauthState.githubLinked
                                            ? 'bg-brand/15 text-brand'
                                            : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {oauthState.githubLinked
                                        ? 'Connected'
                                        : 'Not connected'}
                                </span>

                                {oauthState.githubLinked ? (
                                    canUnlinkGithub ? (
                                        <Button
                                            variant="outline"
                                            type="button"
                                            onClick={() =>
                                                router.delete(
                                                    '/settings/oauth/github',
                                                    {
                                                        preserveScroll: true,
                                                        preserveState: true,
                                                        onSuccess: (page) => {
                                                            const flash = page
                                                                .props.flash as
                                                                | {
                                                                      error?: string;
                                                                  }
                                                                | undefined;

                                                            if (!flash?.error) {
                                                                setOauthState(
                                                                    (
                                                                        current,
                                                                    ) => ({
                                                                        ...current,
                                                                        githubLinked: false,
                                                                    }),
                                                                );
                                                            }
                                                        },
                                                    },
                                                )
                                            }
                                        >
                                            Unlink
                                        </Button>
                                    ) : (
                                        <Button variant="outline" disabled>
                                            Unlink
                                        </Button>
                                    )
                                ) : (
                                    <Button
                                        type="button"
                                        onClick={() =>
                                            window.location.assign(
                                                githubSettingsRedirect().url,
                                            )
                                        }
                                    >
                                        Link GitHub
                                    </Button>
                                )}
                            </div>
                        </div>

                        {oauthState.githubLinked && !canUnlinkGithub ? (
                            <p className="-mt-1 inline-block w-fit rounded-md border border-yellow-300/40 bg-yellow-100/40 px-2.5 py-2 text-xs text-yellow-800 dark:border-yellow-500/30 dark:bg-yellow-500/6 dark:text-yellow-300">
                                Password not set. Set a password before
                                disconnecting GitHub. It is currently your only
                                sign-in method.
                            </p>
                        ) : null}
                    </div>
                </div>

                <div className="space-y-6 rounded-xl border border-brand/20 bg-brand/5 p-4 sm:p-5">
                    <Heading
                        variant="small"
                        title="Two-factor authentication"
                        description="Add an extra security layer to your account"
                    />

                    {twoFactor.enabled ? (
                        <div className="flex flex-col items-start justify-start space-y-4">
                            <Badge
                                variant="default"
                                className="bg-brand text-brand-foreground"
                            >
                                Enabled
                            </Badge>
                            <p className="text-muted-foreground">
                                With two-factor authentication enabled, you will
                                be prompted for a secure pin during login from
                                your authenticator app.
                            </p>

                            <TwoFactorRecoveryCodes
                                recoveryCodesList={recoveryCodesList}
                                fetchRecoveryCodes={fetchRecoveryCodes}
                                errors={twoFactorErrors}
                            />

                            <Button
                                variant="destructive"
                                type="button"
                                onClick={() => {
                                    setDisableTwoFactorPassword('');
                                    setDisableTwoFactorPasswordError(undefined);
                                    setIsDisableTwoFactorModalOpen(true);
                                }}
                            >
                                <ShieldBan /> Disable 2FA
                            </Button>
                        </div>
                    ) : (
                        <div className="flex flex-col items-start justify-start space-y-4">
                            <Badge
                                variant="outline"
                                className="border-brand/40 bg-brand/10 text-foreground"
                            >
                                Disabled
                            </Badge>
                            <p className="text-muted-foreground">
                                When you enable two-factor authentication, you
                                will be prompted for a secure pin during login.
                            </p>

                            {canEnableTwoFactor ? (
                                <Button
                                    type="button"
                                    onClick={() => {
                                        clearSetupData();
                                        setRequireTwoFactorPasswordStep(true);
                                        setShowSetupModal(true);
                                    }}
                                >
                                    <ShieldCheck />
                                    Enable 2FA
                                </Button>
                            ) : (
                                <>
                                    <div className="group relative inline-flex">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled
                                        >
                                            <ShieldCheck />
                                            Enable 2FA
                                        </Button>

                                        <Hint className="pointer-events-none absolute top-1/2 left-full z-10 ml-2 hidden w-max -translate-y-1/2 md:group-hover:inline-block">
                                            Password not set. Set a password
                                            before enabling two-factor
                                            authentication.
                                        </Hint>
                                    </div>

                                    <Hint className="mt-2 md:hidden">
                                        Password not set. Set a password before
                                        enabling two-factor authentication.
                                    </Hint>
                                </>
                            )}
                        </div>
                    )}

                    <TwoFactorSetupModal
                        isOpen={showSetupModal}
                        onClose={() => setShowSetupModal(false)}
                        requirePasswordStep={requireTwoFactorPasswordStep}
                        onPasswordConfirmed={() =>
                            new Promise<void>((resolve, reject) => {
                                router.post(
                                    enable.url(),
                                    {},
                                    {
                                        preserveScroll: true,
                                        preserveState: true,
                                        onSuccess: () => {
                                            setRequireTwoFactorPasswordStep(
                                                false,
                                            );
                                            resolve();
                                        },
                                        onError: () => {
                                            reject(
                                                new Error(
                                                    'Failed to enable two-factor authentication.',
                                                ),
                                            );
                                        },
                                    },
                                );
                            })
                        }
                        requiresConfirmation={twoFactor.requiresConfirmation}
                        twoFactorEnabled={twoFactor.enabled}
                        qrCodeSvg={qrCodeSvg}
                        manualSetupKey={manualSetupKey}
                        clearSetupData={clearSetupData}
                        fetchSetupData={fetchSetupData}
                        errors={twoFactorErrors}
                    />

                    <Dialog
                        open={isDisableTwoFactorModalOpen}
                        onOpenChange={(open) => {
                            if (!open) {
                                setDisableTwoFactorPassword('');
                                setDisableTwoFactorPasswordError(undefined);
                            }

                            setIsDisableTwoFactorModalOpen(open);
                        }}
                    >
                        <DialogContent className="border-brand/25 bg-background outline-none focus:outline-none focus-visible:outline-none sm:max-w-md">
                            <DialogHeader className="items-center text-center">
                                <div className="mb-1 rounded-full border border-brand/25 bg-brand/5 p-1">
                                    <img
                                        src="/images/spendless_logo.png"
                                        alt="SpendLess logo"
                                        className="size-10 rounded-full border border-brand/20 bg-background object-contain p-1"
                                    />
                                </div>
                                <div className="mb-2 inline-flex items-center rounded-full border border-brand/20 bg-brand/5 px-3 py-1">
                                    <span className="text-sm font-semibold tracking-tight text-foreground">
                                        <span>Spend</span>
                                        <span className="text-brand">Less</span>
                                    </span>
                                </div>
                                <DialogTitle>Confirm your password</DialogTitle>
                                <DialogDescription>
                                    {DISABLE_2FA_PASSWORD_DESCRIPTION}
                                </DialogDescription>
                            </DialogHeader>

                            <SecureAccountVerification
                                onSubmit={handleDisableTwoFactor}
                                password={disableTwoFactorPassword}
                                onPasswordChange={setDisableTwoFactorPassword}
                                passwordError={disableTwoFactorPasswordError}
                                processing={isDisablingTwoFactor}
                                submitLabel="Disable 2FA"
                                submitVariant="destructive"
                                submitDisabled={
                                    isDisablingTwoFactor ||
                                    disableTwoFactorPassword.trim() === ''
                                }
                                description={DISABLE_2FA_PASSWORD_DESCRIPTION}
                                passwordId="disable_two_factor_password"
                                autoFocus
                                cancelLabel="Cancel"
                                onCancel={() =>
                                    setIsDisableTwoFactorModalOpen(false)
                                }
                            />
                        </DialogContent>
                    </Dialog>
                </div>

                <DeleteUser hasPasswordSet={password.hasPasswordSet} />
            </SettingsLayout>
        </AppLayout>
    );
}
