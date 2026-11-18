import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type IbkrConnectDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Set when updating the token of an existing connection. */
    connectionId?: number;
    initialQueryId?: string | null;
};

export default function IbkrConnectDialog({
    open,
    onOpenChange,
    connectionId,
    initialQueryId,
}: IbkrConnectDialogProps) {
    const isUpdate = connectionId !== undefined;
    const form = useForm({
        query_id: initialQueryId ?? '',
        token: '',
    });

    useEffect(() => {
        if (open) {
            form.setData({ query_id: initialQueryId ?? '', token: '' });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const submit = (): void => {
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (isUpdate) {
            form.patch(`/investments/ibkr/${connectionId}`, options);

            return;
        }

        form.post('/investments/ibkr', options);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {isUpdate
                            ? 'Update IBKR details'
                            : 'Connect Interactive Brokers'}
                    </DialogTitle>
                    <DialogDescription>
                        SpendLess reads your IBKR account once a day through
                        IBKR&apos;s official Flex Web Service. It can only read
                        reports, never trade.
                    </DialogDescription>
                </DialogHeader>

                {!isUpdate ? (
                    <ol className="list-decimal space-y-1 pl-5 text-sm text-muted-foreground">
                        <li>
                            In IBKR&apos;s Client Portal open{' '}
                            <span className="font-medium text-foreground">
                                Performance &amp; Reports → Flex Queries
                            </span>
                            .
                        </li>
                        <li>
                            Create an{' '}
                            <span className="font-medium text-foreground">
                                Activity Flex Query
                            </span>{' '}
                            with: Account Information, Net Asset Value (NAV) in
                            Base, Open Positions, Trades, Cash Transactions,
                            Cash Report. Format XML, period &quot;Last 365
                            Calendar Days&quot;, dates left at the default.
                        </li>
                        <li>
                            Under{' '}
                            <span className="font-medium text-foreground">
                                Flex Web Service Configuration
                            </span>{' '}
                            enable the service and generate a token (choose the
                            longest validity).
                        </li>
                    </ol>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="ibkr-query-id">Flex Query ID</Label>
                        <Input
                            id="ibkr-query-id"
                            inputMode="numeric"
                            value={form.data.query_id}
                            onChange={(event) =>
                                form.setData(
                                    'query_id',
                                    event.target.value.trim(),
                                )
                            }
                            placeholder="e.g. 987654"
                        />
                        {form.errors.query_id ? (
                            <p className="text-xs text-destructive">
                                {form.errors.query_id}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="ibkr-token">
                            Flex token{isUpdate ? ' (leave empty to keep)' : ''}
                        </Label>
                        <Input
                            id="ibkr-token"
                            type="password"
                            autoComplete="off"
                            value={form.data.token}
                            onChange={(event) =>
                                form.setData('token', event.target.value.trim())
                            }
                            placeholder="Long number from IBKR"
                        />
                        {form.errors.token ? (
                            <p className="text-xs text-destructive">
                                {form.errors.token}
                            </p>
                        ) : null}
                    </div>
                </div>
                <p className="text-xs text-muted-foreground">
                    The token is stored encrypted and is never shown again. It
                    expires after the validity you chose; SpendLess tells you
                    when it needs a new one.
                </p>

                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={form.processing}
                        onClick={submit}
                    >
                        {form.processing
                            ? 'Saving…'
                            : isUpdate
                              ? 'Save and sync'
                              : 'Connect'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
