import { Form, Head } from '@inertiajs/react';
import { Eye, EyeOff, Github } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { redirect as githubRedirect } from '@/routes/github';
import { redirect as googleRedirect } from '@/routes/google';
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
                        <div className="grid gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                asChild
                            >
                                <a
                                    href={googleRedirect({
                                        query: { register: 1 },
                                    }).url}
                                    tabIndex={1}
                                >
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
                                </a>
                            </Button>

                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                asChild
                            >
                                <a
                                    href={githubRedirect({
                                        query: { register: 1 },
                                    }).url}
                                    tabIndex={2}
                                >
                                    <Github
                                        className="mr-2 size-4"
                                        aria-hidden="true"
                                    />
                                    Continue with GitHub
                                </a>
                            </Button>

                        </div>

                        <div className="relative text-center text-xs text-muted-foreground uppercase">
                            <span className="bg-card px-2">
                                or continue with manual registration
                            </span>
                            <div className="absolute top-1/2 -z-10 w-full border-t" />
                        </div>

                        <Card className="gap-0 py-0">
                            <CardContent className="space-y-5 px-5 py-5 sm:px-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        required
                                        autoFocus
                                        tabIndex={3}
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
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        required
                                        tabIndex={4}
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
                                            tabIndex={5}
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
                                            tabIndex={6}
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
                                    tabIndex={7}
                                    data-test="register-user-button"
                                >
                                    {processing && <Spinner />}
                                    Create account
                                </Button>
                            </CardContent>
                        </Card>

                        <div className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink href={login()} tabIndex={8}>
                                Log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
