export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md bg-sidebar-primary/10">
                <img
                    src="/images/spendless_logo.png"
                    alt="SpendLess logo"
                    className="size-7 object-contain"
                />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    <span>Spend</span>
                    <span className="text-brand">Less</span>
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    Money made simple
                </span>
            </div>
        </>
    );
}
