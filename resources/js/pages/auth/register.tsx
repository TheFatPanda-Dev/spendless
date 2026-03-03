import { Form, Head } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { store } from '@/routes/register';

export default function Register() {
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
            title="Create an account"
            description="We only need a few details to get your account ready."
        >
            <Head title="Register" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <Card className="gap-0 py-0">
                            <CardContent className="space-y-5 px-5 py-5 sm:px-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="name"
                                        name="name"
                                        placeholder="Full name"
                                    />
                                    <InputError
                                        message={errors.name}
                                        className="mt-1"
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="nickname">Nickname</Label>
                                    <Input
                                        id="nickname"
                                        type="text"
                                        required
                                        tabIndex={2}
                                        autoComplete="nickname"
                                        name="nickname"
                                        placeholder="How should we call you?"
                                    />
                                    <InputError message={errors.nickname} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        required
                                        tabIndex={3}
                                        autoComplete="email"
                                        name="email"
                                        placeholder="email@example.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <div className="relative">
                                        <Input
                                            id="password"
                                            type={
                                                isPasswordVisible
                                                    ? 'text'
                                                    : 'password'
                                            }
                                            required
                                            tabIndex={4}
                                            autoComplete="new-password"
                                            name="password"
                                            placeholder="Password"
                                            className="pr-10"
                                            onChange={(event) =>
                                                setPasswordValue(
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
                                            type={
                                                isPasswordVisible
                                                    ? 'text'
                                                    : 'password'
                                            }
                                            required
                                            tabIndex={5}
                                            autoComplete="new-password"
                                            name="password_confirmation"
                                            placeholder="Confirm password"
                                            className="pr-10"
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
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full bg-brand text-brand-foreground hover:opacity-90"
                                    tabIndex={6}
                                    data-test="register-user-button"
                                >
                                    {processing && <Spinner />}
                                    Create account
                                </Button>
                            </CardContent>
                        </Card>

                        <div className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink href={login()} tabIndex={7}>
                                Log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
