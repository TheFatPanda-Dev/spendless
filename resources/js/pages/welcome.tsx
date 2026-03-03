import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { dashboard, login, register } from '@/routes';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    return (
        <>
            <Head title="SpendLess" />

            <div className="relative flex min-h-screen items-center overflow-hidden bg-background text-foreground">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(16,185,129,0.18),transparent_40%),radial-gradient(circle_at_80%_85%,rgba(16,185,129,0.12),transparent_45%)]" />

                <header className="absolute inset-x-0 top-0 z-20 p-4 sm:p-8">
                    <div className="mx-auto flex w-full max-w-7xl items-center justify-between gap-3">
                        <Link href="/" className="flex items-center gap-2.5 sm:gap-3">
                            <img
                                src="/images/spendless_logo.png"
                                alt="SpendLess logo"
                                className="h-10 w-10 rounded-full object-contain ring-1 ring-border/70"
                            />
                            <span className="text-base font-semibold tracking-tight text-foreground sm:text-lg">
                                <span>Spend</span>
                                <span className="text-brand">Less</span>
                            </span>
                        </Link>

                        <nav className="hidden items-center gap-3 md:flex">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="rounded-full border border-border bg-card/70 px-5 py-2 text-sm font-medium text-foreground transition hover:border-brand hover:text-brand"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="rounded-full border border-transparent px-4 py-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                                    >
                                        Log in
                                    </Link>
                                    {canRegister && (
                                        <Link
                                            href={register()}
                                            className="rounded-full border border-border bg-card/70 px-5 py-2 text-sm font-medium text-foreground transition hover:border-brand hover:text-brand"
                                        >
                                            Register
                                        </Link>
                                    )}
                                </>
                            )}
                        </nav>

                        <button
                            type="button"
                            className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-border bg-card/70 text-foreground md:hidden"
                            aria-label="Toggle navigation menu"
                            aria-expanded={isMobileMenuOpen}
                            onClick={() => setIsMobileMenuOpen((open) => !open)}
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                className="h-5 w-5"
                            >
                                {isMobileMenuOpen ? (
                                    <path d="M18 6 6 18M6 6l12 12" />
                                ) : (
                                    <path d="M4 6h16M4 12h16M4 18h16" />
                                )}
                            </svg>
                        </button>
                    </div>

                    <div
                        className={`mx-auto mt-3 w-full max-w-7xl transition-all duration-200 md:hidden ${
                            isMobileMenuOpen
                                ? 'translate-y-0 opacity-100'
                                : '-translate-y-1 opacity-0 pointer-events-none'
                        }`}
                    >
                        <nav className="flex justify-end">
                            <div className="min-w-42.5 rounded-2xl border border-border bg-background/95 p-2 shadow-lg backdrop-blur">
                                {auth.user ? (
                                    <Link
                                        href={dashboard()}
                                        className="block rounded-xl px-3 py-2 text-sm font-medium text-foreground transition hover:bg-accent"
                                        onClick={() => setIsMobileMenuOpen(false)}
                                    >
                                        Dashboard
                                    </Link>
                                ) : (
                                    <>
                                        <Link
                                            href={login()}
                                            className="block rounded-xl px-3 py-2 text-sm font-medium text-foreground transition hover:bg-accent"
                                            onClick={() => setIsMobileMenuOpen(false)}
                                        >
                                            Log in
                                        </Link>
                                        {canRegister && (
                                            <Link
                                                href={register()}
                                                className="mt-1 block rounded-xl bg-brand px-3 py-2 text-sm font-medium text-brand-foreground transition hover:opacity-90"
                                                onClick={() => setIsMobileMenuOpen(false)}
                                            >
                                                Register
                                            </Link>
                                        )}
                                    </>
                                )}
                            </div>
                        </nav>
                    </div>
                </header>

                <main className="relative z-10 mx-auto w-full max-w-7xl px-6 pt-28 pb-16 sm:px-10 sm:pt-32 lg:px-16">
                    <div className="max-w-2xl">
                        <img
                            src="/images/spendless_logo.png"
                            alt="SpendLess logo"
                            className="h-24 w-24 rounded-full object-contain ring-1 ring-border/70 sm:h-28 sm:w-28"
                        />

                        <h1 className="mt-8 text-5xl font-extrabold tracking-tight sm:text-7xl md:text-8xl">
                            <span className="block text-foreground">Spend</span>
                            <span className="block text-brand">Less</span>
                        </h1>

                        <p className="mt-5 max-w-xl text-base text-muted-foreground sm:text-lg">
                            Budget smarter, track every movement, and stay in control
                            with a clean modern money dashboard.
                        </p>
                    </div>
                </main>
            </div>
        </>
    );
}
