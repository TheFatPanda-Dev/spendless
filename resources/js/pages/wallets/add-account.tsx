import { Head, router, usePage } from '@inertiajs/react';
import Loader2 from 'lucide-react/dist/esm/icons/loader-2.js';
import Search from 'lucide-react/dist/esm/icons/search.js';
import { useMemo, useState } from 'react';
import BankLogo from '@/components/bank-logo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Institution = {
    name: string;
    logo: string | null;
    beta: boolean;
    psu_types: string[];
};

type Country = {
    code: string;
    name: string;
};

type Reconnect = {
    id: number;
    institution_name: string | null;
    institution_logo: string | null;
};

type AddAccountProps = {
    countries: Country[];
    country: string;
    institutions: Institution[];
    reconnect: Reconnect | null;
    error: string | null;
};

export default function AddAccount({
    countries,
    country,
    institutions,
    reconnect,
    error,
}: AddAccountProps) {
    const page = usePage<{ flash?: { error?: string | null } }>();
    const flashError = page.props.flash?.error ?? null;
    const [query, setQuery] = useState('');
    const [connectingTo, setConnectingTo] = useState<string | null>(null);
    const [isSwitchingCountry, setIsSwitchingCountry] = useState(false);

    const filteredInstitutions = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (needle === '') {
            return institutions;
        }

        return institutions.filter((institution) =>
            institution.name.toLowerCase().includes(needle),
        );
    }, [institutions, query]);

    const switchCountry = (code: string): void => {
        if (code === country || isSwitchingCountry) {
            return;
        }

        setQuery('');
        router.get(
            '/wallets/add-account',
            { country: code },
            {
                only: ['country', 'institutions', 'error'],
                preserveState: true,
                replace: true,
                onStart: () => setIsSwitchingCountry(true),
                onFinish: () => setIsSwitchingCountry(false),
            },
        );
    };

    const connect = (institution: Institution): void => {
        if (connectingTo !== null) {
            return;
        }

        setConnectingTo(institution.name);
        router.post(
            '/banking/connect',
            {
                aspsp_name: institution.name,
                aspsp_country: country,
                psu_type: institution.psu_types.includes('personal')
                    ? 'personal'
                    : (institution.psu_types[0] ?? 'personal'),
            },
            {
                onError: () => setConnectingTo(null),
                onCancel: () => setConnectingTo(null),
                onSuccess: () => setConnectingTo(null),
            },
        );
    };

    const startReconnect = (): void => {
        if (!reconnect || connectingTo !== null) {
            return;
        }

        setConnectingTo(reconnect.institution_name ?? 'your bank');
        router.post(
            `/banking/connections/${reconnect.id}/reconnect`,
            {},
            {
                onError: () => setConnectingTo(null),
                onCancel: () => setConnectingTo(null),
                onSuccess: () => setConnectingTo(null),
            },
        );
    };

    const errorMessage = flashError ?? error;
    const reconnectName = reconnect?.institution_name ?? 'your bank';

    return (
        <>
            <Head
                title={reconnect ? 'Reconnect bank' : 'Connect bank account'}
            />

            <div className="flex min-h-dvh items-center justify-center bg-slate-50 p-4 dark:bg-slate-950">
                <div className="flex max-h-[calc(100dvh-2rem)] w-full max-w-md flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {reconnect
                            ? `Reconnect ${reconnectName}`
                            : 'Connect your bank account'}
                    </h1>

                    <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        {reconnect
                            ? 'Approve access again at your bank. Your existing transactions and categories are kept.'
                            : 'Pick your bank. You will sign in at your bank and approve read-only access to your balances and transactions. SpendLess never sees your bank password.'}
                    </p>

                    {errorMessage ? (
                        <p className="mt-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            {errorMessage}
                        </p>
                    ) : null}

                    {reconnect ? (
                        <div className="mt-5 flex items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                            <BankLogo
                                src={reconnect.institution_logo}
                                name={reconnectName}
                            />
                            <span className="font-medium text-slate-900 dark:text-slate-100">
                                {reconnectName}
                            </span>
                        </div>
                    ) : (
                        <>
                            {countries.length > 1 ? (
                                <div
                                    className="mt-5 grid gap-1 rounded-lg bg-slate-100 p-1 dark:bg-slate-800"
                                    style={{
                                        gridTemplateColumns: `repeat(${countries.length}, minmax(0, 1fr))`,
                                    }}
                                    role="tablist"
                                    aria-label="Country"
                                >
                                    {countries.map((option) => (
                                        <button
                                            key={option.code}
                                            type="button"
                                            role="tab"
                                            aria-selected={
                                                option.code === country
                                            }
                                            onClick={() =>
                                                switchCountry(option.code)
                                            }
                                            className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                                option.code === country
                                                    ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-950 dark:text-slate-100'
                                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-100'
                                            }`}
                                        >
                                            {option.name}
                                        </button>
                                    ))}
                                </div>
                            ) : null}

                            <div className="relative mt-3">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                <Input
                                    type="search"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Search banks"
                                    className="pl-9"
                                    aria-label="Search banks"
                                />
                            </div>

                            <div className="mt-3 min-h-40 flex-1 overflow-y-auto">
                                {isSwitchingCountry ? (
                                    <div className="flex items-center justify-center gap-2 py-10 text-sm text-slate-500">
                                        <Loader2 className="size-4 animate-spin" />
                                        Loading banks…
                                    </div>
                                ) : filteredInstitutions.length === 0 ? (
                                    <p className="py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                        {institutions.length === 0
                                            ? 'No banks available.'
                                            : 'No bank matches your search.'}
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {filteredInstitutions.map(
                                            (institution) => (
                                                <li key={institution.name}>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            connect(institution)
                                                        }
                                                        disabled={
                                                            connectingTo !==
                                                            null
                                                        }
                                                        className="flex w-full items-center gap-3 rounded-lg px-2 py-2.5 text-left transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 dark:hover:bg-slate-800/60"
                                                    >
                                                        <BankLogo
                                                            src={
                                                                institution.logo
                                                            }
                                                            name={
                                                                institution.name
                                                            }
                                                        />
                                                        <span className="min-w-0 flex-1 truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                                                            {institution.name}
                                                        </span>
                                                        {institution.beta ? (
                                                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                                Beta
                                                            </span>
                                                        ) : null}
                                                        {connectingTo ===
                                                        institution.name ? (
                                                            <Loader2 className="size-4 animate-spin text-slate-500" />
                                                        ) : null}
                                                    </button>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </div>
                        </>
                    )}

                    <div className="mt-5 flex gap-2">
                        {reconnect ? (
                            <Button
                                type="button"
                                onClick={startReconnect}
                                disabled={connectingTo !== null}
                            >
                                {connectingTo !== null ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin" />
                                        Opening your bank…
                                    </>
                                ) : (
                                    `Continue to ${reconnectName}`
                                )}
                            </Button>
                        ) : null}

                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => window.close()}
                        >
                            Close
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
