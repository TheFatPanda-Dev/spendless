import { Link } from '@inertiajs/react';
import {
    Palette,
    Shield,
    Tags,
    UserCircle2,
    WalletCards,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: UserCircle2,
    },
    {
        title: 'Security',
        href: '/settings/security',
        icon: Shield,
    },
    {
        title: 'Wallets',
        href: '/settings/wallets',
        icon: WalletCards,
    },
    {
        title: 'All Categories',
        href: '/settings/all-categories',
        icon: Tags,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: Palette,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    return (
        <div className="space-y-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)] lg:items-start">
                <aside className="w-full">
                    <nav
                        className="flex flex-col space-y-1 space-x-0 rounded-xl border border-brand/20 bg-linear-to-br from-brand/5 via-card to-card p-2 dark:from-brand/10"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn(
                                    'w-full justify-start gap-2 rounded-lg text-foreground/90 hover:bg-brand/10 hover:text-foreground',
                                    {
                                        'bg-brand/15 text-foreground ring-1 ring-brand/30':
                                            isCurrentOrParentUrl(item.href),
                                    },
                                )}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="size-[18px] shrink-0" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-1 border-border lg:hidden" />

                <div className="min-w-0">
                    <section className="space-y-6 rounded-xl border border-brand/20 bg-linear-to-br from-brand/5 via-card to-card p-4 sm:p-6 dark:from-brand/10">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
