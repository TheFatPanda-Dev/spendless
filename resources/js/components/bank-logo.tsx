import Landmark from 'lucide-react/dist/esm/icons/landmark.js';
import { useState } from 'react';

export default function BankLogo({
    src,
    name,
    className = 'size-9',
}: {
    src: string | null | undefined;
    name: string;
    className?: string;
}) {
    const [failed, setFailed] = useState(false);

    if (!src || failed) {
        return (
            <span
                className={`flex shrink-0 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground ${className}`}
            >
                <Landmark className="size-5" strokeWidth={1.75} />
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
