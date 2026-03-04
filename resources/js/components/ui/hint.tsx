import * as React from 'react';
import { cn } from '@/lib/utils';

type HintProps = React.ComponentProps<'p'>;

export function Hint({ className, ...props }: HintProps) {
    return (
        <p
            className={cn(
                'rounded-md border border-yellow-300/40 bg-yellow-100/40 px-2.5 py-2 text-xs text-yellow-800 dark:border-yellow-500/30 dark:bg-yellow-500/6 dark:text-yellow-300',
                className,
            )}
            {...props}
        />
    );
}
