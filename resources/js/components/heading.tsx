/**
 * Section heading in the kit's type scale: Section title 22/28 Medium, or a smaller
 * body-weight label for groups inside a card.
 */
export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small' | 'page';
}) {
    if (variant === 'page') {
        return (
            <header className="min-w-0 space-y-2">
                <h1 className="text-screen-title font-normal text-foreground tablet:text-page-title">
                    {title}
                </h1>
                {description && (
                    <p className="max-w-[65ch] text-[15px] text-muted-foreground">
                        {description}
                    </p>
                )}
            </header>
        );
    }

    return (
        <header className={variant === 'small' ? 'mb-3' : 'mb-6 space-y-1.5'}>
            <h2
                className={
                    variant === 'small'
                        ? 'text-base font-medium text-foreground'
                        : 'text-section font-medium text-foreground'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="max-w-[65ch] text-[15px] text-muted-foreground">
                    {description}
                </p>
            )}
        </header>
    );
}
