import Landmark from 'lucide-react/dist/esm/icons/landmark.js';
import WalletIcon from 'lucide-react/dist/esm/icons/wallet.js';
import { useState } from 'react';

/**
 * The bank's or company's logo (from the bank, or stored by the server for known
 * institutions), with a drawn fallback: a wallet for cash, a bank otherwise.
 */
export default function BankLogo({
    src,
    name,
    provider,
    className = 'size-9',
}: {
    src: string | null | undefined;
    name: string;
    provider?: string | null;
    className?: string;
}) {
    const [failed, setFailed] = useState(false);

    if (!src || failed) {
        const Icon = provider === 'manual' ? WalletIcon : Landmark;

        return (
            <span
                className={`flex shrink-0 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground ${className}`}
            >
                <Icon className="size-5" strokeWidth={1.75} />
            </span>
        );
    }

    return (
        <img
            src={src}
            alt=""
            aria-hidden="true"
            loading="lazy"
            onError={() => setFailed(true)}
            className={`shrink-0 rounded-xl border border-border bg-white object-contain p-1 ${className}`}
            title={name}
        />
    );
}
