import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';

function FlashBubble({
    message,
    variant,
    duration,
}: {
    message: string;
    variant: 'success' | 'error';
    duration: number;
}) {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        const timeoutId = window.setTimeout(() => {
            setVisible(false);
        }, duration);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [duration]);

    if (!visible) {
        return null;
    }

    const classes =
        variant === 'success'
            ? 'pointer-events-none fixed right-4 bottom-4 z-50 rounded-lg border border-success/40 bg-success/15 px-4 py-3 text-sm font-medium text-success shadow-lg transition-all duration-300 animate-in fade-in-0 slide-in-from-bottom-2'
            : 'pointer-events-none fixed right-4 bottom-4 z-50 rounded-lg border border-destructive/40 bg-destructive/15 px-4 py-3 text-sm font-medium text-destructive shadow-lg transition-all duration-300 animate-in fade-in-0 slide-in-from-bottom-2';

    return <div className={classes}>{message}</div>;
}

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { flash } = usePage<{
        flash?: { success?: string; error?: string };
    }>().props;

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>

            {flash?.success && (
                <FlashBubble
                    key={`success-${flash.success}`}
                    message={flash.success}
                    variant="success"
                    duration={4000}
                />
            )}

            {flash?.error && (
                <FlashBubble
                    key={`error-${flash.error}`}
                    message={flash.error}
                    variant="error"
                    duration={5000}
                />
            )}
        </AppShell>
    );
}
