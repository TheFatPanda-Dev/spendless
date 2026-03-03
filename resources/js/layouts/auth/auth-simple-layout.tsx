import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden bg-background p-6 text-foreground md:p-10">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(16,185,129,0.18),transparent_40%),radial-gradient(circle_at_80%_85%,rgba(16,185,129,0.12),transparent_45%)]" />

            <div className="relative z-10 w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex items-center gap-3 font-medium"
                        >
                            <div className="flex h-13 w-13 items-center justify-center rounded-md">
                                <img
                                    src="/images/spendless_logo.png"
                                    alt="SpendLess logo"
                                    className="h-13 w-13 rounded-full object-contain ring-1 ring-border/70"
                                />
                            </div>
                            <span className="text-base font-semibold tracking-tight text-foreground">
                                <span>Spend</span>
                                <span className="text-brand">Less</span>
                            </span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
