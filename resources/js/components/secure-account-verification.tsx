import type { SubmitEventHandler } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export const ENABLE_2FA_PASSWORD_DESCRIPTION =
    'To enable two-factor authentication, enter your current password.';

export const DISABLE_2FA_PASSWORD_DESCRIPTION =
    'To disable two-factor authentication, enter your current password.';

type SecureAccountVerificationProps = {
    onSubmit: SubmitEventHandler<HTMLFormElement>;
    password: string;
    onPasswordChange: (password: string) => void;
    passwordError?: string;
    processing: boolean;
    submitLabel: string;
    submitVariant?: 'default' | 'destructive';
    submitDisabled: boolean;
    description?: string;
    passwordId?: string;
    autoFocus?: boolean;
    cancelLabel?: string;
    onCancel?: () => void;
};

export default function SecureAccountVerification({
    onSubmit,
    password,
    onPasswordChange,
    passwordError,
    processing,
    submitLabel,
    submitVariant = 'default',
    submitDisabled,
    description = ENABLE_2FA_PASSWORD_DESCRIPTION,
    passwordId = 'password',
    autoFocus = false,
    cancelLabel,
    onCancel,
}: SecureAccountVerificationProps) {
    return (
        <form
            onSubmit={onSubmit}
            className="space-y-5 rounded-xl border border-brand/20 bg-brand/5 p-4"
        >
            <div className="space-y-1 text-center">
                <p className="text-sm font-medium text-foreground">
                    Secure Account Verification
                </p>
                <p className="text-xs text-muted-foreground">
                    {description}
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor={passwordId}>Password</Label>
                <Input
                    id={passwordId}
                    type="password"
                    name="password"
                    placeholder="Password"
                    autoComplete="current-password"
                    autoFocus={autoFocus}
                    value={password}
                    onChange={(event) => onPasswordChange(event.target.value)}
                />
                <InputError message={passwordError} />
            </div>

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                {cancelLabel && onCancel ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="w-full sm:w-auto"
                        onClick={onCancel}
                        disabled={processing}
                    >
                        {cancelLabel}
                    </Button>
                ) : null}

                <Button
                    type="submit"
                    variant={submitVariant}
                    className="w-full sm:w-auto"
                    disabled={submitDisabled}
                >
                    {processing ? <Spinner /> : null}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
