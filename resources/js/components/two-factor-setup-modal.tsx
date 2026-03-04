import { Form } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { Check, Copy, ScanLine } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import {
    ENABLE_2FA_PASSWORD_DESCRIPTION,
    default as SecureAccountVerification,
} from '@/components/secure-account-verification';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useAppearance } from '@/hooks/use-appearance';
import { useClipboard } from '@/hooks/use-clipboard';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { confirm } from '@/routes/two-factor';

function GridScanIcon() {
    return (
        <div className="mb-3 rounded-full border border-brand/25 bg-brand/5 p-0.5 shadow-sm">
            <div className="relative overflow-hidden rounded-full border border-brand/25 bg-background p-2.5">
                <div className="absolute inset-0 grid grid-cols-5 opacity-50">
                    {Array.from({ length: 5 }, (_, i) => (
                        <div
                            key={`col-${i + 1}`}
                            className="border-r border-border last:border-r-0"
                        />
                    ))}
                </div>
                <div className="absolute inset-0 grid grid-rows-5 opacity-50">
                    {Array.from({ length: 5 }, (_, i) => (
                        <div
                            key={`row-${i + 1}`}
                            className="border-b border-border last:border-b-0"
                        />
                    ))}
                </div>
                <ScanLine className="relative z-20 size-6 text-foreground" />
            </div>
        </div>
    );
}

function BrandHeaderIcon() {
    return (
        <div className="mb-3 rounded-full border border-brand/25 bg-brand/5 p-1 shadow-sm">
            <img
                src="/images/spendless_logo.png"
                alt="SpendLess logo"
                className="size-10 rounded-full border border-brand/20 bg-background object-contain p-1"
            />
        </div>
    );
}

function TwoFactorSetupStep({
    qrCodeSvg,
    manualSetupKey,
    buttonText,
    onNextStep,
    errors,
}: {
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    buttonText: string;
    onNextStep: () => void;
    errors: string[];
}) {
    const { resolvedAppearance } = useAppearance();
    const [copiedText, copy] = useClipboard();
    const IconComponent = copiedText === manualSetupKey ? Check : Copy;

    return (
        <>
            {errors?.length ? (
                <AlertError errors={errors} />
            ) : (
                <div className="w-full space-y-4">
                    <div className="relative overflow-hidden rounded-3xl border border-brand/25 bg-brand/5 p-5 shadow-sm backdrop-blur">
                        <div className="pointer-events-none absolute -top-16 -right-16 h-40 w-40 rounded-full bg-brand/15 blur-2xl" />
                        <div className="pointer-events-none absolute -bottom-20 -left-20 h-44 w-44 rounded-full bg-brand/10 blur-2xl" />

                        <div className="relative z-10 space-y-3 text-center">
                            <p className="text-sm font-semibold tracking-tight text-foreground">
                                Scan to link your authenticator app
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Use Google Authenticator, 1Password, or another TOTP app.
                            </p>
                        </div>

                        <div className="relative z-10 mx-auto mt-4 flex aspect-square w-full max-w-64 items-center justify-center rounded-2xl border border-brand/20 bg-card p-4 shadow-md">
                            {qrCodeSvg ? (
                                <div
                                    className="aspect-square w-full overflow-hidden rounded-lg [&_svg]:block [&_svg]:size-full"
                                    dangerouslySetInnerHTML={{
                                        __html: qrCodeSvg,
                                    }}
                                    style={{
                                        filter:
                                            resolvedAppearance === 'dark'
                                                ? 'invert(1) brightness(1.5)'
                                                : undefined,
                                    }}
                                />
                            ) : (
                                <Spinner />
                            )}
                        </div>
                    </div>

                    <div className="rounded-xl border border-brand/20 bg-background/70 p-3">
                        <p className="mb-2 text-center text-xs text-muted-foreground">
                            Prefer manual setup? Use this setup key
                        </p>

                        <div className="flex w-full items-stretch overflow-hidden rounded-lg border border-border">
                            {!manualSetupKey ? (
                                <div className="flex h-full w-full items-center justify-center bg-muted p-3">
                                    <Spinner />
                                </div>
                            ) : (
                                <>
                                    <div className="flex h-full w-full items-center bg-background p-3 text-sm text-foreground">
                                        {manualSetupKey}
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => copy(manualSetupKey)}
                                        className="border-l border-border px-3 hover:bg-muted focus-visible:outline-none"
                                    >
                                        <IconComponent className="w-4" />
                                    </button>
                                </>
                            )}
                        </div>
                    </div>

                    <Button className="w-full" onClick={onNextStep}>
                        {buttonText}
                    </Button>
                </div>
            )}
        </>
    );
}

function TwoFactorVerificationStep({
    onClose,
    onBack,
}: {
    onClose: () => void;
    onBack: () => void;
}) {
    const [code, setCode] = useState<string>('');
    const pinInputContainerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setTimeout(() => {
            pinInputContainerRef.current?.querySelector('input')?.focus();
        }, 0);
    }, []);

    return (
        <Form
            {...confirm.form()}
            onSuccess={() => onClose()}
            resetOnError
            resetOnSuccess
        >
            {({
                processing,
                errors,
            }: {
                processing: boolean;
                errors?: { confirmTwoFactorAuthentication?: { code?: string } };
            }) => (
                <div
                    ref={pinInputContainerRef}
                    className="relative w-full space-y-4 rounded-2xl border border-brand/20 bg-brand/5 p-4"
                >
                    <div className="space-y-1 text-center">
                        <p className="text-sm font-semibold tracking-tight text-foreground">
                            Enter your 6-digit code
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Open your authenticator app and type the current code.
                        </p>
                    </div>

                    <div className="flex w-full flex-col items-center space-y-3 py-1">
                        <InputOTP
                            id="otp"
                            name="code"
                            maxLength={OTP_MAX_LENGTH}
                            onChange={setCode}
                            disabled={processing}
                            pattern={REGEXP_ONLY_DIGITS}
                        >
                            <InputOTPGroup>
                                {Array.from(
                                    { length: OTP_MAX_LENGTH },
                                    (_, index) => (
                                        <InputOTPSlot
                                            key={index}
                                            index={index}
                                        />
                                    ),
                                )}
                            </InputOTPGroup>
                        </InputOTP>
                        <InputError
                            message={
                                errors?.confirmTwoFactorAuthentication?.code
                            }
                        />
                    </div>

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full sm:flex-1"
                            onClick={onBack}
                            disabled={processing}
                        >
                            Back
                        </Button>
                        <Button
                            type="submit"
                            className="w-full sm:flex-1"
                            disabled={
                                processing || code.length < OTP_MAX_LENGTH
                            }
                        >
                            Confirm
                        </Button>
                    </div>
                </div>
            )}
        </Form>
    );
}

function TwoFactorPasswordStep({
    onConfirmed,
}: {
    onConfirmed: () => Promise<void>;
}) {
    const [password, setPassword] = useState('');
    const [isPreparingSetup, setIsPreparingSetup] = useState(false);
    const [passwordError, setPasswordError] = useState<string | undefined>(
        undefined,
    );

    const getCookieValue = (name: string): string | undefined => {
        const match = document.cookie.match(
            new RegExp(`(?:^|; )${name.replace(/[-.$?*|{}()[\]\\/+^]/g, '\\$&')}=([^;]*)`),
        );

        return match ? decodeURIComponent(match[1]) : undefined;
    };

    const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (isPreparingSetup) {
            return;
        }

        setIsPreparingSetup(true);
        setPasswordError(undefined);

        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');
        const xsrfToken = getCookieValue('XSRF-TOKEN');

        const response = await fetch('/user/confirm-password', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
            },
            body: JSON.stringify({ password }),
        });

        if (!response.ok) {
            let message = 'The password confirmation failed.';

            try {
                const payload = (await response.json()) as {
                    message?: string;
                    errors?: { password?: string[] };
                };

                message = payload.errors?.password?.[0] ?? payload.message ?? message;
            } catch {
                message = 'The password confirmation failed.';
            }

            setPasswordError(message);
            setIsPreparingSetup(false);

            return;
        }

        try {
            await onConfirmed();
            setPassword('');
        } catch (error) {
            setPasswordError(
                error instanceof Error
                    ? error.message
                    : 'Unable to enable two-factor authentication. Please try again.',
            );
        } finally {
            setIsPreparingSetup(false);
        }
    };

    return (
        <SecureAccountVerification
            onSubmit={handleSubmit}
            password={password}
            onPasswordChange={setPassword}
            passwordError={passwordError}
            processing={isPreparingSetup}
            submitLabel="Continue"
            submitDisabled={isPreparingSetup || password.trim() === ''}
            description={ENABLE_2FA_PASSWORD_DESCRIPTION}
            autoFocus
        />
    );
}

type Props = {
    isOpen: boolean;
    onClose: () => void;
    requirePasswordStep?: boolean;
    onPasswordConfirmed?: () => Promise<void>;
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    clearSetupData: () => void;
    fetchSetupData: () => Promise<void>;
    errors: string[];
};

export default function TwoFactorSetupModal({
    isOpen,
    onClose,
    requirePasswordStep = false,
    onPasswordConfirmed,
    requiresConfirmation,
    twoFactorEnabled,
    qrCodeSvg,
    manualSetupKey,
    clearSetupData,
    fetchSetupData,
    errors,
}: Props) {
    const [showVerificationStep, setShowVerificationStep] =
        useState<boolean>(false);
    const [hasConfirmedPassword, setHasConfirmedPassword] =
        useState<boolean>(false);
    const passwordConfirmed = !requirePasswordStep || hasConfirmedPassword;

    const modalConfig = useMemo<{
        title: string;
        description: string;
        buttonText: string;
    }>(() => {
        if (twoFactorEnabled) {
            return {
                title: 'Two-factor authentication enabled',
                description:
                    'Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.',
                buttonText: 'Close',
            };
        }

        if (showVerificationStep) {
            return {
                title: 'Verify authentication code',
                description:
                    'Enter the 6-digit code from your authenticator app',
                buttonText: 'Continue',
            };
        }

        if (!passwordConfirmed) {
            return {
                title: 'Confirm your password',
                description: ENABLE_2FA_PASSWORD_DESCRIPTION,
                buttonText: 'Continue',
            };
        }

        return {
            title: 'Enable two-factor authentication',
            description:
                'To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app',
            buttonText: 'Continue',
        };
    }, [twoFactorEnabled, showVerificationStep, passwordConfirmed]);

    const resetModalState = useCallback(() => {
        setShowVerificationStep(false);
        setHasConfirmedPassword(false);

        if (twoFactorEnabled) {
            clearSetupData();
        }
    }, [twoFactorEnabled, clearSetupData]);

    useEffect(() => {
        if (isOpen && passwordConfirmed && !qrCodeSvg) {
            fetchSetupData();
        }
    }, [isOpen, passwordConfirmed, qrCodeSvg, fetchSetupData]);

    const handleClose = useCallback(() => {
        resetModalState();
        onClose();
    }, [onClose, resetModalState]);

    const handleModalNextStep = useCallback(() => {
        if (requiresConfirmation) {
            setShowVerificationStep(true);
            return;
        }

        resetModalState();
        onClose();
    }, [requiresConfirmation, resetModalState, onClose]);

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && handleClose()}>
            <DialogContent className="border-brand/25 bg-background outline-none focus:outline-none focus-visible:outline-none sm:max-w-md">
                <DialogHeader className="flex items-center justify-center">
                    {!passwordConfirmed ? <BrandHeaderIcon /> : <GridScanIcon />}

                    {!passwordConfirmed ? (
                        <div className="mb-2 flex items-center gap-2 rounded-full border border-brand/20 bg-brand/5 px-3 py-1">
                            <span className="text-sm font-semibold tracking-tight text-foreground">
                                <span>Spend</span>
                                <span className="text-brand">Less</span>
                            </span>
                        </div>
                    ) : null}

                    <DialogTitle>{modalConfig.title}</DialogTitle>
                    <DialogDescription className="text-center">
                        {modalConfig.description}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col items-center space-y-5">
                    {!passwordConfirmed ? (
                        <TwoFactorPasswordStep
                            onConfirmed={async () => {
                                if (onPasswordConfirmed) {
                                    await onPasswordConfirmed();
                                }

                                setHasConfirmedPassword(true);
                            }}
                        />
                    ) : showVerificationStep ? (
                        <TwoFactorVerificationStep
                            onClose={handleClose}
                            onBack={() => setShowVerificationStep(false)}
                        />
                    ) : (
                        <TwoFactorSetupStep
                            qrCodeSvg={qrCodeSvg}
                            manualSetupKey={manualSetupKey}
                            buttonText={modalConfig.buttonText}
                            onNextStep={handleModalNextStep}
                            errors={errors}
                        />
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
