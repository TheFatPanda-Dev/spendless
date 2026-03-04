import { Head, Link } from '@inertiajs/react';
import { home } from '@/routes';

export default function Terms() {
    return (
        <>
            <Head title="Terms of Service" />

            <div className="min-h-screen bg-background text-foreground">
                <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-10 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between">
                        <h1 className="text-2xl font-semibold">Terms of Service</h1>
                        <Link href={home()} className="text-sm text-muted-foreground hover:text-foreground">
                            Back to home
                        </Link>
                    </div>

                    <div className="space-y-4 text-sm text-muted-foreground">
                        <p>
                            These terms govern your use of Spendless and its bank-connection features.
                        </p>
                        <p>
                            By using the app, you agree to comply with applicable laws and use connected financial data responsibly.
                        </p>
                        <p>
                            If you need production-ready legal text, replace this placeholder content with your finalized terms.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
