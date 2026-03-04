import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } }>().props;
    const [showToast, setShowToast] = useState(Boolean(flash?.success));
    const [showErrorToast, setShowErrorToast] = useState(Boolean(flash?.error));

    useEffect(() => {
        if (!flash?.success) {
            setShowToast(false);

            return;
        }

        setShowToast(true);

        const timeout = window.setTimeout(() => setShowToast(false), 4000);

        return () => window.clearTimeout(timeout);
    }, [flash?.success]);

    useEffect(() => {
        if (!flash?.error) {
            setShowErrorToast(false);

            return;
        }

        setShowErrorToast(true);

        const timeout = window.setTimeout(() => setShowErrorToast(false), 6000);

        return () => window.clearTimeout(timeout);
    }, [flash?.error]);

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>

            {showToast && flash?.success && (
                <div className="pointer-events-none fixed right-4 bottom-4 z-50 rounded-lg border border-success/40 bg-success/15 px-4 py-3 text-sm font-medium text-success shadow-lg">
                    {flash.success}
                </div>
            )}

            {showErrorToast && flash?.error && (
                <div className="pointer-events-none fixed right-4 bottom-4 z-50 rounded-lg border border-destructive/40 bg-destructive/15 px-4 py-3 text-sm font-medium text-destructive shadow-lg">
                    {flash.error}
                </div>
            )}
        </AppShell>
    );
}
