import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
    detailsClassName,
}: {
    user: User;
    showEmail?: boolean;
    detailsClassName?: string;
}) {
    const getInitials = useInitials();
    const displayName = user.display_name ?? user.name;

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-full">
                <AvatarImage src={user.avatar} alt={displayName} />
                <AvatarFallback className="rounded-lg bg-muted text-foreground">
                    {getInitials(displayName)}
                </AvatarFallback>
            </Avatar>
            <div
                className={`grid flex-1 text-left text-sm leading-tight ${detailsClassName ?? ''}`.trim()}
            >
                <span className="truncate font-medium">{displayName}</span>
                {showEmail && (
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                )}
            </div>
        </>
    );
}
