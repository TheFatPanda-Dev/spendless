import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];
export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <Card className="border-brand/30 bg-brand/5">
                    <CardHeader className="gap-2">
                        <CardTitle className="text-lg">Dashboard</CardTitle>
                        <CardDescription>
                            Welcome back. Open your bank connections to manage syncing.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button asChild className="h-10 w-full md:w-auto">
                            <Link href="/bank-connections" prefetch>
                                Open Bank Connections
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
