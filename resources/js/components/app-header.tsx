import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Menu } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { buildDashboardHref } from '@/lib/date-filters';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, NavItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const activeItemStyles =
    'bg-brand/12 text-foreground ring-1 ring-brand/35 hover:bg-brand/15';

export function AppHeader({ breadcrumbs = [] }: Props) {
    const page = usePage();
    const { auth } = page.props;
    const dashboardHref = buildDashboardHref(dashboard());
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardHref,
            icon: LayoutGrid,
        },
    ];
    const { isCurrentUrl, whenCurrentUrl } = useCurrentUrl();
    return (
        <>
            <div className="sticky top-0 z-20 border-b border-sidebar-border/80 bg-background/90 backdrop-blur-sm">
                <div className="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                    {/* Mobile Menu */}
                    <div className="lg:hidden">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="mr-2 h-8.5 w-8.5"
                                >
                                    <Menu className="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="left"
                                className="flex h-full w-64 flex-col items-stretch justify-between bg-sidebar"
                            >
                                <SheetTitle className="sr-only">
                                    Navigation menu
                                </SheetTitle>
                                <SheetDescription className="sr-only">
                                    Main navigation links
                                </SheetDescription>
                                <SheetHeader className="flex justify-start text-left">
                                    <div className="flex items-center gap-3">
                                        <img
                                            src="/images/spendless_logo.png"
                                            alt="SpendLess logo"
                                            className="h-7 w-7 object-contain"
                                        />
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-foreground">
                                                {auth.user.display_name ??
                                                    auth.user.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {auth.user.email}
                                            </p>
                                        </div>
                                    </div>
                                </SheetHeader>
                                <div className="flex h-full flex-1 flex-col space-y-4 p-4">
                                    <div className="flex flex-col gap-2 text-sm">
                                        {mainNavItems.map((item) => (
                                            <Link
                                                key={item.title}
                                                href={item.href}
                                                className={cn(
                                                    'flex items-center space-x-2 rounded-md px-3 py-2 font-medium transition-colors',
                                                    isCurrentUrl(item.href)
                                                        ? 'bg-brand/15 text-foreground ring-1 ring-brand/35'
                                                        : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                                                )}
                                            >
                                                {item.icon && (
                                                    <item.icon className="h-5 w-5" />
                                                )}
                                                <span>{item.title}</span>
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>

                    <Link
                        href={dashboardHref}
                        className="flex items-center space-x-2"
                    >
                        <AppLogo />
                    </Link>

                    {/* Desktop Navigation */}
                    <div className="ml-6 hidden h-full items-center space-x-6 lg:flex">
                        <NavigationMenu className="flex h-full items-stretch">
                            <NavigationMenuList className="flex h-full items-stretch space-x-2">
                                {mainNavItems.map((item, index) => (
                                    <NavigationMenuItem
                                        key={index}
                                        className="relative flex h-full items-center"
                                    >
                                        <Link
                                            href={item.href}
                                            className={cn(
                                                navigationMenuTriggerStyle(),
                                                whenCurrentUrl(
                                                    item.href,
                                                    activeItemStyles,
                                                ),
                                                'h-9 cursor-pointer px-3',
                                            )}
                                        >
                                            {item.icon && (
                                                <item.icon className="mr-2 h-4 w-4" />
                                            )}
                                            {item.title}
                                        </Link>
                                        {isCurrentUrl(item.href) && (
                                            <div className="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-brand"></div>
                                        )}
                                    </NavigationMenuItem>
                                ))}
                            </NavigationMenuList>
                        </NavigationMenu>
                    </div>

                    <div className="ml-auto flex items-center space-x-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    className="h-auto max-w-60 justify-start rounded-full px-1 py-1 text-left sm:px-2 sm:py-1.5"
                                >
                                    <UserInfo
                                        user={auth.user}
                                        showEmail={true}
                                        detailsClassName="hidden sm:grid"
                                    />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-48" align="end">
                                <UserMenuContent />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>
            {breadcrumbs.length > 1 && (
                <div className="flex w-full border-b border-sidebar-border/70">
                    <div className="mx-auto flex h-12 w-full items-center justify-start px-4 text-muted-foreground md:max-w-7xl">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}
        </>
    );
}
