import { Link, router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import ChartPie from 'lucide-react/dist/esm/icons/chart-pie.js';
import Check from 'lucide-react/dist/esm/icons/check.js';
import CircleAlert from 'lucide-react/dist/esm/icons/circle-alert.js';
import Ellipsis from 'lucide-react/dist/esm/icons/ellipsis.js';
import FileUp from 'lucide-react/dist/esm/icons/file-up.js';
import LayoutGrid from 'lucide-react/dist/esm/icons/layout-grid.js';
import LogOut from 'lucide-react/dist/esm/icons/log-out.js';
import ShieldUser from 'lucide-react/dist/esm/icons/shield-user.js';
import SlidersHorizontal from 'lucide-react/dist/esm/icons/sliders-horizontal.js';
import Tags from 'lucide-react/dist/esm/icons/tags.js';
import TextAlignStart from 'lucide-react/dist/esm/icons/text-align-start.js';
import Wallet from 'lucide-react/dist/esm/icons/wallet.js';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { BrandLockup, BrandSymbol } from '@/components/brand-lockup';
import { Breadcrumbs } from '@/components/breadcrumbs';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { clearInactivityLogoutState } from '@/hooks/use-inactivity-logout';
import { useInactivityLogout } from '@/hooks/use-inactivity-logout';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import type { AppLayoutProps } from '@/types';

type Destination = {
    title: string;
    href: string;
    icon: LucideIcon;
    /** Other path prefixes that belong to this destination. */
    matches?: string[];
};

const PRIMARY: Destination[] = [
    { title: 'Overview', href: '/dashboard', icon: LayoutGrid },
    { title: 'Activity', href: '/activity', icon: TextAlignStart },
    { title: 'Budgets', href: '/budgets', icon: ChartPie },
    {
        title: 'Wallets',
        href: '/wallets',
        icon: Wallet,
        matches: ['/accounts'],
    },
    { title: 'Tags', href: '/tags', icon: Tags },
    {
        title: 'Imports',
        href: '/imports/new',
        icon: FileUp,
        matches: ['/imports'],
    },
];

const SETTINGS: Destination = {
    title: 'Settings',
    href: '/settings/profile',
    icon: SlidersHorizontal,
    matches: ['/settings'],
};

/** Phone bottom tabs: the four most used places, the rest under More. */
const TABS = PRIMARY.slice(0, 4);

function useIsActive(): (destination: Destination) => boolean {
    const { currentUrl } = useCurrentUrl();

    return (destination) =>
        [destination.href, ...(destination.matches ?? [])].some(
            (prefix) =>
                currentUrl === prefix || currentUrl.startsWith(`${prefix}/`),
        );
}

function RailLink({
    destination,
    active,
    compact,
}: {
    destination: Destination;
    active: boolean;
    compact: boolean;
}) {
    const Icon = destination.icon;

    return (
        <Link
            href={destination.href}
            prefetch
            aria-current={active ? 'page' : undefined}
            className={cn(
                'group flex items-center rounded-xl text-[15px] transition-colors duration-150',
                compact
                    ? 'min-h-14 flex-col justify-center gap-1 px-1 py-2 text-xs'
                    : 'min-h-12 gap-3.5 px-4',
                active
                    ? 'bg-sidebar-accent font-medium text-sidebar-accent-foreground'
                    : 'text-muted-foreground hover:bg-sidebar-accent/50 hover:text-foreground',
            )}
        >
            <Icon className="size-[22px] shrink-0" strokeWidth={1.75} />
            <span className={compact ? 'leading-none' : undefined}>
                {destination.title}
            </span>
        </Link>
    );
}

function Rail({ compact }: { compact: boolean }) {
    const isActive = useIsActive();
    const { auth } = usePage<{ auth: { user: { is_admin?: boolean } } }>()
        .props;
    const admin: Destination = {
        title: 'Admin',
        href: '/settings/admin',
        icon: ShieldUser,
    };

    return (
        <aside
            className={cn(
                'sticky top-0 hidden h-dvh shrink-0 flex-col border-r border-sidebar-border bg-sidebar tablet:flex',
                compact
                    ? 'w-[92px] px-2 py-5 desk:hidden'
                    : 'hidden w-[230px] px-4 py-7 desk:flex',
            )}
        >
            <Link
                href="/dashboard"
                className={cn('mb-9 rounded-xl', compact ? 'mx-auto' : 'px-2')}
                aria-label="SpendLess overview"
            >
                {compact ? <BrandSymbol /> : <BrandLockup />}
            </Link>

            <nav aria-label="Main" className="flex flex-col gap-1.5">
                {PRIMARY.map((destination) => (
                    <RailLink
                        key={destination.href}
                        destination={destination}
                        active={isActive(destination)}
                        compact={compact}
                    />
                ))}
            </nav>

            <div className="mt-auto flex flex-col gap-1.5 border-t border-sidebar-border pt-5">
                {auth.user?.is_admin ? (
                    <RailLink
                        destination={admin}
                        active={isActive(admin)}
                        compact={compact}
                    />
                ) : null}
                <RailLink
                    destination={SETTINGS}
                    active={isActive(SETTINGS) && !isActive(admin)}
                    compact={compact}
                />
            </div>
        </aside>
    );
}

function PhoneTabs() {
    const isActive = useIsActive();
    const [moreOpen, setMoreOpen] = useState(false);
    const { auth } = usePage<{ auth: { user: { is_admin?: boolean } } }>()
        .props;
    const extra = [
        ...PRIMARY.slice(4),
        ...(auth.user?.is_admin
            ? [{ title: 'Admin', href: '/settings/admin', icon: ShieldUser }]
            : []),
        SETTINGS,
    ];
    const moreActive = extra.some((destination) => isActive(destination));

    return (
        <>
            <nav
                aria-label="Main"
                className="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-border bg-background/95 px-1 pt-1.5 pb-[max(env(safe-area-inset-bottom),0.5rem)] backdrop-blur tablet:hidden"
            >
                {TABS.map((destination) => {
                    const Icon = destination.icon;
                    const active = isActive(destination);

                    return (
                        <Link
                            key={destination.href}
                            href={destination.href}
                            prefetch
                            aria-current={active ? 'page' : undefined}
                            className="flex min-h-14 flex-col items-center gap-1 text-xs"
                        >
                            <span
                                className={cn(
                                    'flex h-8 w-14 items-center justify-center rounded-full transition-colors',
                                    active
                                        ? 'bg-accent text-accent-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                <Icon
                                    className="size-[22px]"
                                    strokeWidth={1.75}
                                />
                            </span>
                            <span
                                className={
                                    active
                                        ? 'font-medium text-accent-foreground'
                                        : 'text-muted-foreground'
                                }
                            >
                                {destination.title}
                            </span>
                        </Link>
                    );
                })}
                <button
                    type="button"
                    onClick={() => setMoreOpen(true)}
                    className="flex min-h-14 flex-col items-center gap-1 text-xs"
                    aria-haspopup="dialog"
                >
                    <span
                        className={cn(
                            'flex h-8 w-14 items-center justify-center rounded-full',
                            moreActive
                                ? 'bg-accent text-accent-foreground'
                                : 'text-muted-foreground',
                        )}
                    >
                        <Ellipsis className="size-[22px]" strokeWidth={1.75} />
                    </span>
                    <span
                        className={
                            moreActive
                                ? 'font-medium text-accent-foreground'
                                : 'text-muted-foreground'
                        }
                    >
                        More
                    </span>
                </button>
            </nav>

            <Sheet open={moreOpen} onOpenChange={setMoreOpen}>
                <SheetContent
                    side="bottom"
                    className="rounded-t-card px-4 pb-8"
                >
                    <SheetHeader className="px-1">
                        <SheetTitle>More</SheetTitle>
                        <SheetDescription className="sr-only">
                            Other places in SpendLess
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-1">
                        {extra.map((destination) => (
                            <div
                                key={destination.href}
                                onClick={() => setMoreOpen(false)}
                            >
                                <RailLink
                                    destination={destination}
                                    active={isActive(destination)}
                                    compact={false}
                                />
                            </div>
                        ))}
                        <Link
                            href={logout()}
                            as="button"
                            onClick={() => {
                                clearInactivityLogoutState();
                                router.flushAll();
                            }}
                            className="flex min-h-12 items-center gap-3.5 rounded-xl px-4 text-left text-[15px] text-muted-foreground hover:bg-accent/50 hover:text-foreground"
                        >
                            <LogOut
                                className="size-[22px]"
                                strokeWidth={1.75}
                            />
                            Log out
                        </Link>
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

/** Save confirmation / failure, announced to assistive technology (kit page 09). */
function Toast({
    message,
    variant,
}: {
    message: string;
    variant: 'success' | 'error';
}) {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        const timer = window.setTimeout(
            () => setVisible(false),
            variant === 'success' ? 4000 : 6000,
        );

        return () => window.clearTimeout(timer);
    }, [variant]);

    if (!visible) {
        return null;
    }

    const Icon = variant === 'success' ? Check : CircleAlert;

    return (
        <div
            role={variant === 'success' ? 'status' : 'alert'}
            className={cn(
                'fixed right-4 bottom-24 left-4 z-50 flex animate-in items-start gap-3 rounded-2xl px-4 py-3.5 text-[15px] shadow-[0_8px_24px_-12px_rgb(0_0_0/0.35)] duration-200 fade-in-0 slide-in-from-bottom-2 tablet:bottom-6 tablet:left-auto tablet:max-w-md',
                variant === 'success'
                    ? 'bg-hero text-hero-foreground'
                    : 'bg-error-surface text-destructive',
            )}
        >
            <Icon className="mt-0.5 size-5 shrink-0" strokeWidth={1.75} />
            <span>{message}</span>
        </div>
    );
}

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps & { children: ReactNode }) {
    useInactivityLogout();

    const { flash } = usePage<{
        flash?: { success?: string | null; error?: string | null };
    }>().props;

    return (
        <div className="flex min-h-dvh w-full bg-background">
            <Rail compact={false} />
            <Rail compact />

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-border bg-background/95 px-5 backdrop-blur tablet:hidden">
                    <Link href="/dashboard" aria-label="SpendLess overview">
                        <BrandLockup className="[&_img]:h-10" />
                    </Link>
                </header>

                <main className="mx-auto w-full max-w-[1200px] flex-1 px-5 pt-6 pb-28 tablet:px-6 tablet:pt-8 tablet:pb-12 desk:px-10 desk:pt-12">
                    {breadcrumbs.length > 1 ? (
                        <div className="mb-4">
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        </div>
                    ) : null}
                    {children}
                </main>
            </div>

            <PhoneTabs />

            {flash?.success ? (
                <Toast
                    key={`success-${flash.success}`}
                    message={flash.success}
                    variant="success"
                />
            ) : null}
            {flash?.error ? (
                <Toast
                    key={`error-${flash.error}`}
                    message={flash.error}
                    variant="error"
                />
            ) : null}
        </div>
    );
}
