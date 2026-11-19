import { cn } from '@/lib/utils';

/**
 * The compact SpendLess lockup from the identity kit (coin tile + two-tone name).
 * The lettering is fixed artwork, so it ships as SVG, one master per background.
 */
export function BrandLockup({ className }: { className?: string }) {
    return (
        <span className={cn('inline-flex items-center', className)}>
            <img
                src="/images/brand/lockup-on-light.svg"
                alt="SpendLess"
                width={179}
                height={48}
                className="h-11 w-auto dark:hidden"
            />
            <img
                src="/images/brand/lockup-on-dark.svg"
                alt="SpendLess"
                width={179}
                height={48}
                className="hidden h-11 w-auto dark:block"
            />
        </span>
    );
}

/** The coin symbol on its tile, for narrow placements. */
export function BrandSymbol({ className }: { className?: string }) {
    return (
        <img
            src="/images/brand/app-icon.svg"
            alt="SpendLess"
            width={48}
            height={48}
            className={cn('size-10 rounded-[22%]', className)}
        />
    );
}
