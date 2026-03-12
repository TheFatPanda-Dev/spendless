import { Transition } from '@headlessui/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
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
