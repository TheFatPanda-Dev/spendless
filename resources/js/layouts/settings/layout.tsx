import { Link } from '@inertiajs/react';
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
        icon: null,
    },
    {
        title: 'Security',
        href: '/settings/security',
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="mt-6 flex flex-col gap-6 rounded-2xl border border-brand/25 bg-brand/5 p-4 sm:p-6 lg:flex-row lg:gap-10">
                <aside className="w-full lg:w-56">
                    <nav
                        className="flex flex-col space-y-1 space-x-0 rounded-xl border border-brand/20 bg-background/70 p-2"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn(
                                    'w-full justify-start rounded-lg text-foreground/90 hover:bg-brand/10 hover:text-foreground',
                                    {
                                        'bg-brand/15 text-foreground':
                                            isCurrentOrParentUrl(item.href),
                                    },
                                )}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-1 border-brand/25 lg:hidden" />

                <div className="flex-1 md:max-w-3xl">
                    <section className="space-y-6 rounded-xl border border-brand/20 bg-background/80 p-4 sm:p-6">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
