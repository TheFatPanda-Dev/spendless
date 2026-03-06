import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type AddNewWalletMenuProps = {
    onConnectBank: () => void;
};

export default function AddNewWalletMenu({
    onConnectBank,
}: AddNewWalletMenuProps) {
    const [open, setOpen] = useState(false);

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    className="border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-200 dark:bg-white dark:text-slate-700"
                >
                    <Plus className="size-4" />
                    Add New Wallet
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-60">
                <DropdownMenuItem
                    onSelect={() => {
                        setOpen(false);
                        onConnectBank();
                    }}
                >
                    Connect a bank account
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        href="/wallets?type=cash"
                        prefetch
                        className="w-full cursor-pointer"
                        onClick={() => setOpen(false)}
                    >
                        Add cash wallet
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        href="/wallets?type=stock"
                        prefetch
                        className="w-full cursor-pointer"
                        onClick={() => setOpen(false)}
                    >
                        Add stock wallet
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
