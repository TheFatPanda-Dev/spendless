import { Link } from '@inertiajs/react';
import Shield from 'lucide-react/dist/esm/icons/shield.js';
import Tags from 'lucide-react/dist/esm/icons/tags.js';
import UserCircle2 from 'lucide-react/dist/esm/icons/user-circle-2.js';
import WalletCards from 'lucide-react/dist/esm/icons/wallet-cards.js';
import type { PropsWithChildren } from 'react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
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
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    return (
        <div className="space-y-6">
            <h1 className="text-screen-title font-normal text-foreground tablet:text-page-title">
                Settings
            </h1>

            <div className="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)] lg:items-start">
                <aside className="w-full">
                    <nav
                        className="flex flex-col gap-1 rounded-card border border-border bg-card p-2"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                variant="ghost"
                                asChild
                                className={cn(
                                    'h-12 w-full justify-start gap-3.5 rounded-xl px-4 text-[15px] font-normal text-muted-foreground hover:bg-muted hover:text-foreground',
                                    {
                                        'bg-accent font-medium text-accent-foreground hover:bg-accent hover:text-accent-foreground':
                                            isCurrentOrParentUrl(item.href),
                                    },
                                )}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon
                                            className="size-5 shrink-0"
                                            strokeWidth={1.75}
                                        />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-1 border-border lg:hidden" />

                <div className="min-w-0">
                    <div className="space-y-6">{children}</div>
                </div>
            </div>
        </div>
    );
}
