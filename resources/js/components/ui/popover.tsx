import * as PopoverPrimitive from '@radix-ui/react-popover';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

function Popover(props: ComponentProps<typeof PopoverPrimitive.Root>) {
    return <PopoverPrimitive.Root data-slot="popover" {...props} />;
}

function PopoverTrigger(props: ComponentProps<typeof PopoverPrimitive.Trigger>) {
    return <PopoverPrimitive.Trigger data-slot="popover-trigger" {...props} />;
}

function PopoverContent({
    className,
    align = 'start',
    sideOffset = 6,
    portal = true,
    ...props
}: ComponentProps<typeof PopoverPrimitive.Content> & {
    /**
     * Render at the page root (default). Inside a sheet or dialog pass false: the
     * dialog only lets focus and taps reach its own content.
     */
    portal?: boolean;
}) {
    const content = (
        <PopoverPrimitive.Content
                data-slot="popover-content"
                align={align}
                sideOffset={sideOffset}
                className={cn(
                    'z-50 rounded-xl border border-border bg-popover text-popover-foreground shadow-[0_8px_24px_-12px_rgb(0_0_0/0.35)] outline-hidden data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0',
                    className,
                )}
                {...props}
            />
    );

    return portal ? (
        <PopoverPrimitive.Portal>{content}</PopoverPrimitive.Portal>
    ) : (
        content
    );
}

export { Popover, PopoverContent, PopoverTrigger };
