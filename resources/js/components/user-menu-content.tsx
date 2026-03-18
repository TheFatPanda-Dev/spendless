import { Link, router } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
} from '@/components/ui/dropdown-menu';
import { clearInactivityLogoutState } from '@/hooks/use-inactivity-logout';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';

export function UserMenuContent() {
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        clearInactivityLogoutState();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Settings
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={logout()}
                        as="button"
                        onClick={handleLogout}
                        data-test="logout-button"
                    >
                        <LogOut className="mr-2" />
                        Log out
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
        </>
    );
}
