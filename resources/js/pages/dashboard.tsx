import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import banking from '@/routes/banking';
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
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Connect a bank account</CardTitle>
                        <CardDescription>
                            Sandbox setup for Slovenia with Delavska hranilnica and Revolut.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form {...banking.start.form()} className="grid gap-4 sm:max-w-md">
                            {({ processing, errors }) => (
                                <>
                                    <input type="hidden" name="aspsp_country" value="SI" />
                                    <input type="hidden" name="psu_type" value="personal" />

                                    <div className="grid gap-2">
                                        <Label htmlFor="aspsp_name">Institution</Label>
                                        <select
                                            id="aspsp_name"
                                            name="aspsp_name"
                                            defaultValue="Delavska hranilnica d.d."
                                            className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                        >
                                            <option value="Delavska hranilnica d.d.">Delavska hranilnica d.d.</option>
                                            <option value="Revolut">Revolut</option>
                                        </select>
                                        {errors.aspsp_name ? (
                                            <p className="text-sm text-destructive">{errors.aspsp_name}</p>
                                        ) : null}
                                    </div>

                                    <Button type="submit" disabled={processing} className="w-full sm:w-auto">
                                        {processing ? 'Redirecting...' : 'Connect bank'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-foreground/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-foreground/20" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-foreground/20" />
                    </div>
                </div>
                <div className="relative min-h-screen flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-foreground/20" />
                </div>
            </div>
        </AppLayout>
    );
}
