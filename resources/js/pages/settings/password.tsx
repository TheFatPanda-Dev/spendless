import { Transition } from '@headlessui/react';
import { Form, Head } from '@inertiajs/react';
import EyeOff from 'lucide-react/dist/esm/icons/eye-off.js';
import Eye from 'lucide-react/dist/esm/icons/eye.js';
import { useMemo, useRef, useState } from 'react';
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { edit } from '@/routes/user-password';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Password settings',
        href: edit(),
    },
];

export default function Password() {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const [isPasswordVisible, setIsPasswordVisible] = useState(false);
    const [newPasswordValue, setNewPasswordValue] = useState('');
    const [confirmPasswordValue, setConfirmPasswordValue] = useState('');
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Password settings" />

            <h1 className="sr-only">Password settings</h1>

            <SettingsLayout>
                <div className="space-y-6 rounded-xl border border-brand/20 bg-linear-to-br from-brand/6 via-card to-card p-4 sm:p-5 dark:from-brand/10">
                    <Heading
                        variant="small"
                        title="Update password"
                        description="Use at least 8 characters with uppercase, lowercase, number, and special character"
                    />

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
                        }}
                        onError={(errors) => {
                            if (errors.password) {
                                passwordInput.current?.focus();
                            }

                            if (errors.current_password) {
                                currentPasswordInput.current?.focus();
                            }
                        }}
                        className="space-y-6"
                    >
                        {({ errors, processing, recentlySuccessful }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="current_password">
                                        Current password
                                    </Label>

                                    <Input
                                        id="current_password"
                                        ref={currentPasswordInput}
                                        name="current_password"
                                        type="password"
                                        className="mt-1 block w-full"
                                        autoComplete="current-password"
                                        placeholder="Current password"
                                    />

                                    <InputError
                                        message={errors.current_password}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">
                                        New password
                                    </Label>

                                    <div className="relative">
                                        <Input
                                            id="password"
                                            ref={passwordInput}
                                            name="password"
                                            type={
                                                isPasswordVisible
                                                    ? 'text'
                                                    : 'password'
                                            }
                                            className={cn(
                                                'mt-1 block w-full pr-10',
                                                passwordMatchBorderClass,
                                            )}
                                            autoComplete="new-password"
                                            placeholder="New password"
                                            value={newPasswordValue}
                                            onChange={(event) =>
                                                setNewPasswordValue(
                                                    event.target.value,
                                                )
                                            }
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

                                <div className="grid gap-2">
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
                                            className={cn(
                                                'mt-1 block w-full pr-10',
                                                passwordMatchBorderClass,
                                            )}
                                            autoComplete="new-password"
                                            placeholder="Confirm password"
                                            value={confirmPasswordValue}
                                            onChange={(event) =>
                                                setConfirmPasswordValue(
                                                    event.target.value,
                                                )
                                            }
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
                                    <Button
                                        disabled={processing}
                                        data-test="update-password-button"
                                    >
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
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
