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
                className={`flex shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300 ${className}`}
            >
                <Landmark className="size-4" />
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
            className={`shrink-0 rounded-lg border border-slate-200 bg-white object-contain p-1 dark:border-slate-700 ${className}`}
            title={name}
        />
    );
}
