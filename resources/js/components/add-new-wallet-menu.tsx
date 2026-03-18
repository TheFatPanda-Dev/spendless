import { Link, useForm, usePage } from '@inertiajs/react';
import Plus from 'lucide-react/dist/esm/icons/plus.js';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { walletCurrencyOptions } from '@/lib/currency-options';
import {
    formatLocalizedDecimal,
    normalizeLocalizedNumber,
} from '@/lib/localized-number';

type AddNewWalletMenuProps = {
    onConnectBank: () => void;
};

export default function AddNewWalletMenu({
    onConnectBank,
}: AddNewWalletMenuProps) {
    const [open, setOpen] = useState(false);
    const [manualDialogOpen, setManualDialogOpen] = useState(false);
    const [startingBalanceInput, setStartingBalanceInput] = useState('');
    const page = usePage();
    const numberLocale =
        typeof page.props.number_locale === 'string'
            ? page.props.number_locale
            : 'en-GB';
    const focusClasses =
        'border-input bg-background focus-visible:border-[#39ff14] focus-visible:ring-2 focus-visible:ring-[#39ff14]/35 focus-visible:ring-offset-0';
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        name: '',
        currency: 'EUR',
        starting_balance: '',
    });
    const startingBalancePlaceholder = useMemo(
        () => formatLocalizedDecimal('0', numberLocale),
        [numberLocale],
    );

    const closeManualDialog = (): void => {
        setManualDialogOpen(false);
        setStartingBalanceInput('');
        reset();
        clearErrors();
    };

    const submitManualWallet = (): void => {
        post('/wallets/manual', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                closeManualDialog();
            },
        });
    };

    return (
        <>
            <DropdownMenu open={open} onOpenChange={setOpen}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        className="w-full border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50 sm:w-auto dark:border-slate-200 dark:bg-white dark:text-slate-700"
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
                    <DropdownMenuItem
                        onSelect={(event) => {
                            event.preventDefault();
                            setOpen(false);
                            setManualDialogOpen(true);
                        }}
                    >
                        Add cash wallet
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

            <Dialog
                open={manualDialogOpen}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        closeManualDialog();

                        return;
                    }

                    setManualDialogOpen(true);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create cash wallet</DialogTitle>
                        <DialogDescription>
                            Add a manual wallet with its own currency and optional starting balance.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="manual-wallet-name">Wallet name</Label>
                            <Input
                                id="manual-wallet-name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                placeholder="Cash Wallet"
                                className={focusClasses}
                            />
                            {errors.name ? (
                                <p className="text-xs text-destructive">{errors.name}</p>
                            ) : null}
                        </div>

                        <div className="grid gap-2 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="manual-wallet-currency">Currency</Label>
                                <select
                                    id="manual-wallet-currency"
                                    value={data.currency}
                                    onChange={(event) => setData('currency', event.target.value)}
                                    className={`h-10 rounded-md border px-3 text-sm ${focusClasses}`}
                                >
                                    {walletCurrencyOptions.map((currencyOption) => (
                                        <option
                                            key={currencyOption.code}
                                            value={currencyOption.code}
                                        >
                                            {currencyOption.label}
                                        </option>
                                    ))}
                                </select>
                                {errors.currency ? (
                                    <p className="text-xs text-destructive">{errors.currency}</p>
                                ) : null}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="manual-wallet-starting-balance">
                                    Starting balance (optional)
                                </Label>
                                <Input
                                    id="manual-wallet-starting-balance"
                                    type="text"
                                    inputMode="decimal"
                                    value={startingBalanceInput}
                                    onChange={(event) => {
                                        const nextValue = event.target.value;

                                        setStartingBalanceInput(nextValue);
                                        setData(
                                            'starting_balance',
                                            normalizeLocalizedNumber(
                                                nextValue,
                                                numberLocale,
                                            ),
                                        );
                                    }}
                                    onBlur={(event) => {
                                        const nextValue = event.target.value;

                                        if (nextValue.trim() === '') {
                                            return;
                                        }

                                        setStartingBalanceInput(
                                            formatLocalizedDecimal(
                                                nextValue,
                                                numberLocale,
                                            ),
                                        );
                                    }}
                                    placeholder={startingBalancePlaceholder}
                                    className={focusClasses}
                                />
                                {errors.starting_balance ? (
                                    <p className="text-xs text-destructive">
                                        {errors.starting_balance}
                                    </p>
                                ) : null}
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" type="button" onClick={closeManualDialog}>
                            Cancel
                        </Button>
                        <Button type="button" disabled={processing} onClick={submitManualWallet}>
                            {processing ? 'Creating...' : 'Create'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
