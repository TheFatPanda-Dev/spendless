import { Transition } from '@headlessui/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import Cookie from 'lucide-react/dist/esm/icons/cookie.js';
import { useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { readCookieConsent, writeCookieConsent } from '@/lib/site-prefs';
import type { ConsentChoice } from '@/lib/site-prefs';
import { cn } from '@/lib/utils';
import { edit } from '@/routes/profile';
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
    pendingEmail,
    preferredName,
    status,
}: {
    mustVerifyEmail: boolean;
    pendingEmail?: string | null;
    preferredName?: string | null;
    status?: string;
}) {
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const displayName = auth.user.display_name ?? auth.user.name;
    const [isChangingEmail, setIsChangingEmail] = useState(false);
    const [cookieConsent, setCookieConsent] = useState(() =>
        readCookieConsent(),
    );

    const saveCookieConsent = (choice: ConsentChoice): void => {
        const nextConsent = writeCookieConsent(choice);

        setCookieConsent(nextConsent);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="space-y-6 rounded-xl border border-brand/20 bg-linear-to-br from-brand/6 via-card to-card p-4 sm:p-5 dark:from-brand/10">
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
                                            If set, this name is shown in the
                                            app. Otherwise, we use your full
                                            name.
                                        </p>

                                        <InputError
                                            className="mt-1"
                                            message={errors.preferred_name}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            Email address
                                        </Label>

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
                                            className="w-fit text-sm font-medium text-brand underline decoration-brand/40 underline-offset-4 transition-colors hover:text-brand/80"
                                        >
                                            Change email address
                                        </button>

                                        {isChangingEmail ? (
                                            <div className="grid gap-2 rounded-lg border border-brand/20 bg-brand/5 p-3 dark:bg-brand/10">
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
                                                    Your current email is
                                                    updated only after you
                                                    confirm the link we send to
                                                    the new address.
                                                </p>
                                                <InputError
                                                    className="mt-1"
                                                    message={errors.new_email}
                                                />
                                            </div>
                                        ) : null}

                                        {pendingEmail ? (
                                            <p className="text-xs font-medium text-muted-foreground">
                                                Pending confirmation:{' '}
                                                {pendingEmail}
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
                                            <Avatar className="size-12 border border-brand/25 bg-brand/5">
                                                <AvatarImage
                                                    src={auth.user.avatar}
                                                    alt={displayName}
                                                />
                                                <AvatarFallback className="bg-brand/15 text-foreground">
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
                                        auth.user.email_verified_at ===
                                            null && (
                                            <div>
                                                <p className="-mt-4 text-sm text-muted-foreground">
                                                    Your email address is
                                                    unverified.{' '}
                                                    <Link
                                                        href={send()}
                                                        as="button"
                                                        className="text-brand underline decoration-brand/40 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-brand"
                                                    >
                                                        Click here to resend the
                                                        verification email.
                                                    </Link>
                                                </p>

                                                {status ===
                                                    'verification-link-sent' && (
                                                    <div className="mt-2 text-sm font-medium text-success">
                                                        A new verification link
                                                        has been sent to your
                                                        email address.
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

                    <div
                        id="cookie-preferences"
                        className="space-y-6 rounded-xl border border-brand/20 bg-linear-to-br from-brand/6 via-card to-card p-4 sm:p-5 dark:from-brand/10"
                    >
                        <header>
                            <h2 className="mb-0.5 inline-flex items-center gap-2 text-base font-medium">
                                <Cookie className="size-4" aria-hidden="true" />
                                Cookie preferences
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Control optional analytics cookies for this
                                browser
                            </p>
                        </header>

                        <div className="rounded-lg border border-border/70 bg-background/70 p-4">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                <div className="space-y-1">
                                    <p className="text-xs text-muted-foreground">
                                        Choose whether optional analytics
                                        cookies are allowed. Essential cookies
                                        remain enabled for security and login.
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Current choice:{' '}
                                        <span className="font-medium text-foreground">
                                            {cookieConsent?.choice ===
                                            'accepted'
                                                ? 'Accept all'
                                                : cookieConsent?.choice ===
                                                    'essential'
                                                  ? 'Essential only'
                                                  : 'Not set yet'}
                                        </span>
                                    </p>
                                </div>

                                <div className="inline-flex w-full gap-1 rounded-lg border border-brand/20 bg-brand/5 p-1 sm:w-auto sm:self-end dark:bg-brand/10">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            saveCookieConsent('essential')
                                        }
                                        className={cn(
                                            'min-w-0 flex-1 rounded-md px-3.5 py-1.5 text-sm transition-colors sm:min-w-34 sm:flex-none',
                                            cookieConsent?.choice ===
                                                'essential'
                                                ? 'bg-brand text-brand-foreground shadow-xs ring-1 ring-brand/45'
                                                : 'text-muted-foreground hover:bg-brand/15 hover:text-foreground',
                                        )}
                                    >
                                        Essential only
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            saveCookieConsent('accepted')
                                        }
                                        className={cn(
                                            'min-w-0 flex-1 rounded-md px-3.5 py-1.5 text-sm transition-colors sm:min-w-34 sm:flex-none',
                                            cookieConsent?.choice === 'accepted'
                                                ? 'bg-brand text-brand-foreground shadow-xs ring-1 ring-brand/45'
                                                : 'text-muted-foreground hover:bg-brand/15 hover:text-foreground',
                                        )}
                                    >
                                        Accept all
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-6 rounded-xl border border-brand/20 bg-linear-to-br from-brand/6 via-card to-card p-4 sm:p-5 dark:from-brand/10">
                        <Heading
                            variant="small"
                            title="Appearance settings"
                            description="Choose how Spendless looks on this device"
                        />

                        <AppearanceTabs />
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
