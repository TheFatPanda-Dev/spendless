import { Form, Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Bank Connections',
        href: '/bank-connections',
    },
];

type ConnectionAccount = {
    id: number;
    name: string | null;
    account_type: string | null;
    iban: string | null;
    currency: string | null;
};

type BankConnection = {
    id: number;
    aspsp_name: string;
    aspsp_country: string;
    status: string;
    authorized_at: string | null;
    accounts: ConnectionAccount[];
};

export default function BankConnections({ connections = [] }: { connections?: BankConnection[] }) {
    const [institutions, setInstitutions] = useState<Array<{ name: string; country: string }>>([]);
    const [selectedInstitution, setSelectedInstitution] = useState<string>('');

    useEffect(() => {
        let isMounted = true;

        const loadInstitutions = async () => {
            try {
                const response = await fetch('/banking/institutions', {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                const resolvedInstitutions: Array<{ name: string; country: string }> =
                    payload?.preferred?.length > 0 ? payload.preferred : payload?.institutions ?? [];

                if (!isMounted) {
                    return;
                }

                setInstitutions(resolvedInstitutions);

                if (resolvedInstitutions.length > 0) {
                    setSelectedInstitution(resolvedInstitutions[0].name);
                }
            } catch {
                if (!isMounted) {
                    return;
                }

                setInstitutions([]);
            }
        };

        loadInstitutions();

        return () => {
            isMounted = false;
        };
    }, []);

    const selectedCountry = useMemo(() => {
        const match = institutions.find((institution) => institution.name === selectedInstitution);

        return match?.country ?? 'SI';
    }, [institutions, selectedInstitution]);

    const getAccountType = (account: ConnectionAccount): string => {
        if (account.account_type && account.account_type.trim() !== '') {
            return account.account_type.toUpperCase();
        }

        return 'ACCOUNT';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bank Connections" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <Card className="border-brand/30 bg-brand/5">
                    <CardHeader className="gap-2">
                        <CardTitle className="text-lg">Bank Connections</CardTitle>
                        <CardDescription>
                            Connect your bank and keep accounts synced in one place.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form action="/banking/connect" method="post" className="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                            {({ processing, errors }) => (
                                <>
                                    <input type="hidden" name="aspsp_country" value={selectedCountry} />
                                    <input type="hidden" name="psu_type" value="personal" />

                                    <div className="grid gap-2">
                                        <Label htmlFor="aspsp_name">Institution</Label>
                                        <select
                                            id="aspsp_name"
                                            name="aspsp_name"
                                            value={selectedInstitution}
                                            onChange={(event) => setSelectedInstitution(event.target.value)}
                                            className="border-input bg-background h-10 rounded-md border px-3 text-sm"
                                            disabled={institutions.length === 0 || processing}
                                        >
                                            {institutions.length === 0 ? (
                                                <option value="">No institutions available</option>
                                            ) : (
                                                institutions.map((institution) => (
                                                    <option key={institution.name} value={institution.name}>
                                                        {institution.name}
                                                    </option>
                                                ))
                                            )}
                                        </select>
                                        {errors.aspsp_name ? (
                                            <p className="text-sm text-destructive">{errors.aspsp_name}</p>
                                        ) : null}
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={processing || institutions.length === 0 || selectedInstitution === ''}
                                        className="h-10 w-full md:w-auto"
                                    >
                                        {processing ? 'Redirecting...' : 'Connect bank'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Connected Accounts</CardTitle>
                        <CardDescription>
                            Active connections and synced accounts.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {connections.length === 0 ? (
                            <div className="rounded-lg border border-dashed border-border bg-muted/30 px-4 py-6 text-center text-sm text-muted-foreground">
                                No connected accounts yet.
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {connections.map((connection) => (
                                    <div key={connection.id} className="rounded-lg border border-border bg-card p-4">
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div className="space-y-1">
                                                <p className="font-semibold text-foreground">
                                                    {connection.aspsp_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground uppercase tracking-wide">
                                                    {connection.aspsp_country} • {connection.status}
                                                </p>
                                            </div>

                                            <Button
                                                type="button"
                                                variant="destructive"
                                                size="sm"
                                                onClick={() => {
                                                    router.delete(`/banking/connections/${connection.id}`, {
                                                        preserveScroll: true,
                                                        preserveState: true,
                                                    });
                                                }}
                                            >
                                                Delete connection
                                            </Button>
                                        </div>

                                        {connection.accounts.length > 0 ? (
                                            <div className="mt-3 grid gap-2">
                                                {connection.accounts.map((account) => (
                                                    <div
                                                        key={account.id}
                                                        className="rounded-md border border-border/70 bg-muted/30 px-3 py-2 text-sm"
                                                    >
                                                        <p className="font-medium text-foreground">
                                                            {getAccountType(account)}
                                                        </p>
                                                        <p className="text-muted-foreground">
                                                            {account.iban ?? 'No IBAN available'}
                                                            {account.currency ? ` • ${account.currency}` : ''}
                                                        </p>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="mt-3 text-sm text-muted-foreground">
                                                No accounts synced yet.
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
