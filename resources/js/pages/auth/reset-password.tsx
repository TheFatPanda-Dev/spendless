import { Form, Head } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
};

export default function ResetPassword({ token, email }: Props) {
    const [passwordValue, setPasswordValue] = useState('');
    const [isPasswordVisible, setIsPasswordVisible] = useState(false);

    const passwordChecks = useMemo(
        () => [
            {
                label: 'At least 8 characters',
                met: passwordValue.length >= 8,
            },
            {
                label: 'At least one uppercase letter',
                met: /[A-Z]/.test(passwordValue),
            },
            {
                label: 'At least one lowercase letter',
                met: /[a-z]/.test(passwordValue),
            },
            {
                label: 'At least one number',
                met: /\d/.test(passwordValue),
            },
            {
                label: 'At least one special character',
                met: /[^A-Za-z0-9]/.test(passwordValue),
            },
        ],
        [passwordValue],
    );

    return (
        <AuthLayout
            title="Reset password"
            description="Set a new password for your account."
        >
            <Head title="Reset password" />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label>Email</Label>
                            <p className="rounded-md border bg-muted/30 px-3 py-2 text-sm text-foreground">
                                {email}
                            </p>
                            <InputError
                                message={errors.email}
                                className="mt-2"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <div className="relative">
                                <Input
                                    id="password"
                                    type={isPasswordVisible ? 'text' : 'password'}
                                    name="password"
                                    autoComplete="new-password"
                                    className="mt-1 block w-full pr-10"
                                    autoFocus
                                    placeholder="Password"
                                    onChange={(event) =>
                                        setPasswordValue(event.target.value)
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
                                    type={isPasswordVisible ? 'text' : 'password'}
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                    className="mt-1 block w-full pr-10"
                                    placeholder="Confirm password"
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
                                message={errors.password_confirmation}
                                className="mt-2"
                            />
                        </div>

                        <Button
                            type="submit"
                            className="mt-4 w-full"
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            Reset password
                        </Button>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
