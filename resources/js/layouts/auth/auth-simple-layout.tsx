import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * Sign-in, sign-up and recovery: the kit's full signature (coin, two-tone name and
 * "Money made simple") as the first-touch brand surface, then one focused form.
 */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-background px-5 py-10 text-foreground">
            <div className="w-full max-w-[26rem]">
                <Link
                    href={home()}
                    className="mb-10 flex justify-center"
                    aria-label="SpendLess home"
                >
                    <img
                        src="/images/brand/lockup-on-light-tagline.svg"
                        alt="SpendLess — Money made simple"
                        className="h-16 w-auto dark:hidden"
                    />
                    <img
                        src="/images/brand/lockup-on-dark-tagline.svg"
                        alt="SpendLess — Money made simple"
                        className="hidden h-16 w-auto dark:block"
                    />
                </Link>

                <div className="rounded-card border border-border bg-card p-6 tablet:p-8">
                    <div className="mb-6 space-y-2">
                        <h1 className="text-[1.75rem] leading-tight font-normal">
                            {title}
                        </h1>
                        {description ? (
                            <p className="text-[15px] text-muted-foreground">
                                {description}
                            </p>
                        ) : null}
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
